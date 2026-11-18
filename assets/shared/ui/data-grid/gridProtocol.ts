/**
 * Query protocol of the server-driven list screens (the `x-grid-protocol` that
 * `Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter` implements):
 *
 *   sort         JSON `[{"selector":"name","desc":false}]`
 *   filter       JSON `["field","operator",value]`, several leaves joined with `"and"`
 *   searchValue  free text, matched against the columns named by `searchExpr`
 *   searchExpr   the columns to scan (sent as `searchExpr[0]=…`, which Symfony reads as a list)
 *   page         **1-based** (API Platform)
 *   itemsPerPage page size
 *
 * Response: the rows in `hydra:member` / `member` (or a bare array), the total in the
 * `X-Total-Count` header.
 *
 * Everything here is pure so the wire format can be tested without a browser.
 */

export interface SortRule {
  selector: string;
  desc: boolean;
}

export type FilterValue = string | number | boolean | null;
export type FilterLeaf = readonly [field: string, operator: string, value: FilterValue];

export interface GridQuery {
  /** 0-based, as the UI thinks of it; converted to API Platform's 1-based `page` here. */
  page: number;
  pageSize: number;
  search: string;
  searchFields: readonly string[];
  sort: readonly SortRule[];
  filter: readonly FilterLeaf[];
}

/** Longest search text the server accepts; it truncates beyond this, so we do not send more. */
export const MAX_SEARCH_LENGTH = 200;

function filterExpression(leaves: readonly FilterLeaf[]): unknown[] | undefined {
  if (leaves.length === 0) return undefined;
  if (leaves.length === 1) return [...(leaves[0] as FilterLeaf)];

  const expression: unknown[] = [];
  leaves.forEach((leaf, index) => {
    if (index > 0) expression.push('and');
    expression.push([...leaf]);
  });
  return expression;
}

/** Parameters for one page of results. */
export function toGridParams(query: GridQuery): Record<string, unknown> {
  const params: Record<string, unknown> = {
    page: query.page + 1,
    itemsPerPage: query.pageSize,
  };

  if (query.sort.length > 0) {
    params['sort'] = JSON.stringify(query.sort.map(({ selector, desc }) => ({ selector, desc })));
  }

  const filter = filterExpression(query.filter);
  if (filter) params['filter'] = JSON.stringify(filter);

  const search = query.search.trim().slice(0, MAX_SEARCH_LENGTH);
  if (search && query.searchFields.length > 0) {
    params['searchValue'] = search;
    params['searchExpr'] = [...query.searchFields];
  }

  return params;
}

/** Parameters for an export: every row matching the filters, so no pagination. */
export function toExportParams(query: GridQuery): Record<string, unknown> {
  const { page: _page, itemsPerPage: _itemsPerPage, ...params } = toGridParams(query);
  void _page;
  void _itemsPerPage;
  return params;
}

export interface GridPage<T> {
  rows: T[];
  totalCount: number;
}

function header(headers: Record<string, string> | undefined, name: string): string | undefined {
  if (!headers) return undefined;
  const wanted = name.toLowerCase();
  for (const [key, value] of Object.entries(headers)) {
    if (key.toLowerCase() === wanted) return value;
  }
  return undefined;
}

function finiteNumber(value: unknown): number | undefined {
  const n = typeof value === 'string' && value.trim() === '' ? Number.NaN : Number(value);
  return Number.isFinite(n) ? n : undefined;
}

/** Reads rows and the total from a list response, whichever JSON-LD flavour the server used. */
export function parseGridResponse<T>(body: unknown, headers?: Record<string, string>): GridPage<T> {
  const record = (body ?? {}) as Record<string, unknown>;
  const rows = (
    Array.isArray(body) ? body : (record['hydra:member'] ?? record['member'] ?? [])
  ) as T[];

  const total =
    finiteNumber(header(headers, 'x-total-count')) ??
    finiteNumber(record['hydra:totalItems'] ?? record['totalItems']) ??
    rows.length;

  return { rows: Array.isArray(rows) ? rows : [], totalCount: total };
}
