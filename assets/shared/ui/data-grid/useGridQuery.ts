import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { useCallback, useMemo, useReducer } from 'react';
import { httpClient } from '@shared/api/HttpClient';
import {
  parseGridResponse,
  toExportParams,
  toGridParams,
  type FilterLeaf,
  type GridPage,
  type GridQuery,
  type SortRule,
} from './gridProtocol';
import { gridReducer, initialGridState, type GridState } from './gridState';

export interface UseGridQueryOptions {
  /** Collection URL, e.g. `${API}customers`. */
  url: string;
  /** Cache key prefix; the screen's scope (branch, tenant…) belongs here. */
  queryKey: readonly unknown[];
  /** Columns the free-text search scans (`searchExpr`). */
  searchFields: readonly string[];
  /** Turns a named UI filter into a server filter leaf; `null` means "no restriction". */
  filterOf?: (name: string, value: string) => FilterLeaf | null;
  initial?: Partial<GridState>;
  enabled?: boolean;
}

export interface GridQueryResult<T> {
  state: GridState;
  /** What goes on the wire for this state (page included). */
  query: GridQuery;
  exportParams: Record<string, unknown>;
  rows: T[];
  totalCount: number;
  /** First load, nothing to show yet. */
  isPending: boolean;
  /** Any request in flight, including refetches. */
  isFetching: boolean;
  /** The rows on screen belong to the previous query while the new one loads. */
  isStale: boolean;
  error: unknown;
  refetch: () => Promise<unknown>;
  setSearch: (value: string) => void;
  setFilter: (name: string, value: string) => void;
  setSort: (sort: readonly SortRule[]) => void;
  setPage: (page: number) => void;
  setPageSize: (size: number) => void;
}

/**
 * Server-driven list state + data.
 *
 * - Narrowing resets the page atomically (see `gridReducer`).
 * - The request carries TanStack Query's `AbortSignal`, so a superseded request is cancelled
 *   rather than merely ignored.
 * - The previous page stays visible while the next loads (`isStale`), so the table does not
 *   flash empty; the grid disables interaction with those rows meanwhile.
 * - Errors are shown inline by the grid, not as a toast: the grid has a retry action.
 */
export function useGridQuery<T>({
  url,
  queryKey,
  searchFields,
  filterOf,
  initial,
  enabled = true,
}: UseGridQueryOptions): GridQueryResult<T> {
  const [state, dispatch] = useReducer(gridReducer, initial, initialGridState);

  const query = useMemo<GridQuery>(() => {
    const filter = Object.entries(state.filters)
      .map(([name, value]) => filterOf?.(name, value) ?? null)
      .filter((leaf): leaf is FilterLeaf => leaf !== null);

    return {
      page: state.page,
      pageSize: state.pageSize,
      search: state.search,
      searchFields,
      sort: state.sort,
      filter,
    };
  }, [state, searchFields, filterOf]);

  const params = useMemo(() => toGridParams(query), [query]);
  const exportParams = useMemo(() => toExportParams(query), [query]);

  const result = useQuery({
    queryKey: [...queryKey, 'list', params],
    queryFn: async ({ signal }): Promise<GridPage<T>> => {
      const response = await httpClient.get<unknown>(url, {
        params,
        signal,
        errorPresentation: 'inline',
      });
      return parseGridResponse<T>(response.data, response.headers);
    },
    placeholderData: keepPreviousData,
    enabled,
  });

  const setSearch = useCallback((value: string) => dispatch({ type: 'search', value }), []);
  const setFilter = useCallback(
    (name: string, value: string) => dispatch({ type: 'filter', name, value }),
    [],
  );
  const setSort = useCallback((sort: readonly SortRule[]) => dispatch({ type: 'sort', sort }), []);
  const setPage = useCallback((page: number) => dispatch({ type: 'page', page }), []);
  const setPageSize = useCallback((size: number) => dispatch({ type: 'pageSize', size }), []);

  return {
    state,
    query,
    exportParams,
    rows: result.data?.rows ?? [],
    totalCount: result.data?.totalCount ?? 0,
    isPending: result.isPending,
    isFetching: result.isFetching,
    isStale: result.isPlaceholderData,
    error: result.error,
    refetch: result.refetch,
    setSearch,
    setFilter,
    setSort,
    setPage,
    setPageSize,
  };
}
