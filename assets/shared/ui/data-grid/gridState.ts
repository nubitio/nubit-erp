import type { SortRule } from './gridProtocol';

/**
 * UI state of a server-driven list: what the user asked for, not what the server returned.
 *
 * Kept as a reducer on purpose. Narrowing the result set (search, filter, sort, page size) must
 * send the reader back to the first page **in the same transition**: resetting the page in an
 * effect afterwards issues a request for (new filter, old page) first, which is wasted work and
 * can ask for a page that does not exist in the filtered set.
 */
export interface GridState {
  search: string;
  /** Named filters as the screen exposes them (e.g. `status: 'active'`); `''` means "not set". */
  filters: Readonly<Record<string, string>>;
  sort: readonly SortRule[];
  /** 0-based. */
  page: number;
  pageSize: number;
}

export type GridAction =
  | { type: 'search'; value: string }
  | { type: 'filter'; name: string; value: string }
  | { type: 'sort'; sort: readonly SortRule[] }
  | { type: 'page'; page: number }
  | { type: 'pageSize'; size: number }
  | { type: 'reset'; state: GridState };

export function initialGridState(overrides: Partial<GridState> = {}): GridState {
  return { search: '', filters: {}, sort: [], page: 0, pageSize: 20, ...overrides };
}

function sameSort(a: readonly SortRule[], b: readonly SortRule[]): boolean {
  return (
    a.length === b.length &&
    a.every((r, i) => r.selector === b[i]?.selector && r.desc === b[i]?.desc)
  );
}

/** Returns the same object when nothing changes, so an unchanged state never refetches. */
export function gridReducer(state: GridState, action: GridAction): GridState {
  switch (action.type) {
    case 'search':
      return action.value === state.search ? state : { ...state, search: action.value, page: 0 };

    case 'filter': {
      if ((state.filters[action.name] ?? '') === action.value) return state;
      const filters = { ...state.filters };
      if (action.value === '') delete filters[action.name];
      else filters[action.name] = action.value;
      return { ...state, filters, page: 0 };
    }

    case 'sort':
      return sameSort(state.sort, action.sort) ? state : { ...state, sort: action.sort, page: 0 };

    case 'page': {
      const page = Math.max(0, Math.floor(action.page));
      return page === state.page ? state : { ...state, page };
    }

    case 'pageSize':
      return action.size === state.pageSize ? state : { ...state, pageSize: action.size, page: 0 };

    case 'reset':
      return action.state;
  }
}
