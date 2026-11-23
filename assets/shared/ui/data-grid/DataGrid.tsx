import { useScreenSize } from '@nubitio/admin';
import {
  Button,
  EmptyState,
  Pagination,
  RowActions,
  Combobox,
  Skeleton,
  type ContextMenuItem,
} from '@nubitio/ui';
import type { ReactNode } from 'react';
import {
  Cell,
  Column,
  Row,
  Table,
  TableBody,
  TableHeader,
  type SortDescriptor,
} from 'react-aria-components';
import type { SortRule } from './gridProtocol';
import './DataGrid.scss';

/** What React Aria accepts: pixels, a percentage or a fraction of the free space. */
export type ColumnWidth = number | `${number}%` | `${number}fr`;

export interface GridColumn<T> {
  id: string;
  header: string;
  cell: (row: T) => ReactNode;
  /** Server-side sort on this column (by `sortKey`, which defaults to `id`). */
  sortable?: boolean;
  sortKey?: string;
  align?: 'start' | 'end';
  /** Columns without one share the remaining space. */
  width?: ColumnWidth;
  minWidth?: number;
}

export interface GridPagination {
  /** 0-based. */
  page: number;
  pageSize: number;
  totalCount: number;
  onPageChange: (page: number) => void;
  onPageSizeChange: (size: number) => void;
  pageSizes?: readonly number[];
}

export interface DataGridProps<T> {
  /** Accessible name of the table. */
  label: string;
  columns: readonly GridColumn<T>[];
  rows: readonly T[];
  getRowId: (row: T) => string;
  sort: readonly SortRule[];
  onSortChange: (sort: SortRule[]) => void;
  /** First load: nothing to show yet. */
  isPending: boolean;
  isFetching: boolean;
  /** The rows shown belong to the previous query; interaction is disabled until the next arrives. */
  isStale: boolean;
  error?: unknown;
  onRetry?: () => void;
  /** No rows and no filter applied. */
  emptyState: ReactNode;
  /** No rows because of the search or filters. */
  noResultsState?: ReactNode;
  hasActiveFilters?: boolean;
  rowActions?: (row: T) => ContextMenuItem[];
  rowActionsLabel?: (row: T) => string;
  /** Content of one card; used instead of the table on small screens. */
  renderCard?: (row: T) => ReactNode;
  /** `auto` follows the screen size; the others force a layout (tests, embedding). */
  mode?: 'auto' | 'table' | 'cards';
  pagination: GridPagination;
  testId?: string;
}

const ACTIONS_COLUMN = '__actions';
const DEFAULT_PAGE_SIZES = [10, 20, 50, 100] as const;

interface Labels {
  actions: string;
  loadError: string;
  loadErrorHint: string;
  retry: string;
  rowsPerPage: string;
  noResults: string;
  noResultsHint: string;
  loading: string;
}

const ES: Labels = {
  actions: 'Acciones',
  loadError: 'No se pudo cargar la información',
  loadErrorHint: 'Ocurrió un error al consultar los datos. Inténtalo de nuevo.',
  retry: 'Reintentar',
  rowsPerPage: 'Filas por página',
  noResults: 'Sin resultados',
  noResultsHint: 'Prueba con otra búsqueda o quita los filtros.',
  loading: 'Cargando…',
};

function summary(p: GridPagination): string {
  if (p.totalCount === 0) return '0 registros';
  const from = p.page * p.pageSize + 1;
  const to = Math.min(p.totalCount, (p.page + 1) * p.pageSize);
  return `${from}-${to} de ${p.totalCount} registros`;
}

function Footer({ pagination }: { pagination: GridPagination }) {
  const { page, pageSize, totalCount, onPageChange, onPageSizeChange } = pagination;
  const sizes = pagination.pageSizes ?? DEFAULT_PAGE_SIZES;
  const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));

  return (
    <footer className="erp-grid__footer">
      <span className="erp-grid__summary" data-testid="grid-summary">
        {summary(pagination)}
      </span>
      {/* ui/Pagination is 1-based; the grid owns the conversion so callers only see 0-based pages. */}
      <Pagination
        page={page + 1}
        totalPages={totalPages}
        onPageChange={(p) => onPageChange(p - 1)}
        ariaLabel="Paginación"
      />
      <label className="erp-grid__page-size">
        <span>{ES.rowsPerPage}</span>
        <Combobox
          aria-label={ES.rowsPerPage}
          clearable={false}
          options={sizes.map((size) => ({ value: String(size), label: String(size) }))}
          value={String(pageSize)}
          onChange={(size) => size && onPageSizeChange(Number(size))}
        />
      </label>
    </footer>
  );
}

function LoadingRows() {
  return (
    <div className="erp-grid__loading" role="status" aria-label={ES.loading}>
      {Array.from({ length: 5 }, (_, i) => (
        <Skeleton key={i} variant="rect" height={44} />
      ))}
    </div>
  );
}

export function DataGrid<T>({
  label,
  columns,
  rows,
  getRowId,
  sort,
  onSortChange,
  isPending,
  isFetching,
  isStale,
  error,
  onRetry,
  emptyState,
  noResultsState,
  hasActiveFilters = false,
  rowActions,
  rowActionsLabel,
  renderCard,
  mode = 'auto',
  pagination,
  testId,
}: DataGridProps<T>) {
  const { isXSmall } = useScreenSize();
  const asCards = mode === 'cards' || (mode === 'auto' && isXSmall && renderCard !== undefined);

  const columnById = new Map(columns.map((column) => [column.id, column]));
  const sortKeyOf = (columnId: string) => columnById.get(columnId)?.sortKey ?? columnId;
  const first = sort[0];
  const sortColumn = first
    ? columns.find((c) => (c.sortKey ?? c.id) === first.selector)?.id
    : undefined;
  const sortDescriptor: SortDescriptor | undefined =
    first && sortColumn
      ? { column: sortColumn, direction: first.desc ? 'descending' : 'ascending' }
      : undefined;

  const handleSort = (descriptor: SortDescriptor) => {
    onSortChange([
      {
        selector: sortKeyOf(String(descriptor.column)),
        desc: descriptor.direction === 'descending',
      },
    ]);
  };

  const tableColumns = rowActions
    ? [...columns, { id: ACTIONS_COLUMN, header: ES.actions, cell: () => null } as GridColumn<T>]
    : columns;

  const showError = error !== undefined && error !== null && rows.length === 0 && !isPending;
  const showEmpty = !showError && !isPending && rows.length === 0;
  const empty = hasActiveFilters
    ? (noResultsState ?? (
        <EmptyState title={ES.noResults} description={ES.noResultsHint} icon="magnifying-glass" />
      ))
    : emptyState;

  let body: ReactNode;
  if (isPending) {
    body = <LoadingRows />;
  } else if (showError) {
    body = (
      <div role="alert" className="erp-grid__error">
        <EmptyState
          icon="warning-circle"
          variant="danger"
          title={ES.loadError}
          description={ES.loadErrorHint}
          action={
            onRetry ? (
              <Button variant="secondary" onClick={onRetry}>
                {ES.retry}
              </Button>
            ) : undefined
          }
        />
      </div>
    );
  } else if (showEmpty) {
    body = <div className="erp-grid__empty">{empty}</div>;
  } else if (asCards && renderCard) {
    body = (
      <ul className="erp-grid__cards" aria-label={label}>
        {rows.map((row) => (
          <li key={getRowId(row)} className="erp-grid__card" data-testid="grid-card">
            <div className="erp-grid__card-body">{renderCard(row)}</div>
            {rowActions && (
              <div className="erp-grid__card-actions">
                <RowActions
                  items={rowActions(row)}
                  triggerLabel={rowActionsLabel?.(row) ?? ES.actions}
                />
              </div>
            )}
          </li>
        ))}
      </ul>
    );
  } else {
    body = (
      <div className="nb-data-table-wrap erp-grid__table-wrap">
        <Table
          aria-label={label}
          className="nb-data-table erp-grid__table"
          {...(sortDescriptor ? { sortDescriptor } : {})}
          onSortChange={handleSort}
        >
          {/* `dependencies`: React Aria caches what a dynamic collection renders per item, so a
              change of columns (basic/full view) must be declared or rows keep the old cell count. */}
          <TableHeader columns={tableColumns} dependencies={[tableColumns, sortDescriptor]}>
            {(column) =>
              column.id === ACTIONS_COLUMN ? (
                <Column id={ACTIONS_COLUMN} width={56} minWidth={56}>
                  <span className="erp-grid__sr-only">{ES.actions}</span>
                </Column>
              ) : (
                <Column
                  id={column.id}
                  isRowHeader={column.id === columns[0]?.id}
                  allowsSorting={column.sortable ?? false}
                  {...(column.width !== undefined ? { width: column.width } : {})}
                  {...(column.minWidth !== undefined ? { minWidth: column.minWidth } : {})}
                  className={column.align === 'end' ? 'erp-grid__col--end' : undefined}
                >
                  {({ sortDirection }) => (
                    <span className="erp-grid__header">
                      {column.header}
                      {sortDirection && (
                        <span className="erp-grid__sort" aria-hidden>
                          {sortDirection === 'ascending' ? '▲' : '▼'}
                        </span>
                      )}
                    </span>
                  )}
                </Column>
              )
            }
          </TableHeader>
          <TableBody items={rows as T[]} dependencies={[tableColumns, rowActions, rowActionsLabel]}>
            {(row) => (
              <Row
                id={getRowId(row)}
                columns={tableColumns}
                dependencies={[tableColumns]}
                className="erp-grid__row"
              >
                {(column) =>
                  column.id === ACTIONS_COLUMN ? (
                    <Cell className="erp-grid__actions">
                      <RowActions
                        items={rowActions ? rowActions(row) : []}
                        triggerLabel={rowActionsLabel?.(row) ?? ES.actions}
                      />
                    </Cell>
                  ) : (
                    <Cell className={column.align === 'end' ? 'erp-grid__col--end' : undefined}>
                      {column.cell(row)}
                    </Cell>
                  )
                }
              </Row>
            )}
          </TableBody>
        </Table>
      </div>
    );
  }

  return (
    <section className="erp-grid" data-testid={testId} aria-busy={isFetching}>
      <div
        className="erp-grid__body"
        data-stale={isStale || undefined}
        data-fetching={isFetching || undefined}
      >
        {body}
      </div>
      {!isPending && !showError && <Footer pagination={pagination} />}
    </section>
  );
}
