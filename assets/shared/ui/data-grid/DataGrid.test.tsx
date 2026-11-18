import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DataGrid, type DataGridProps, type GridColumn } from './DataGrid';

type Row = { id: string; ruc: string; name: string };
const rows: Row[] = [
  { id: '1', ruc: '20100000001', name: 'Andina SAC' },
  { id: '2', ruc: '20100000002', name: 'Titicaca SRL' },
];

const columns: GridColumn<Row>[] = [
  { id: 'ruc', header: 'RUC/DNI', cell: (r) => r.ruc, sortable: true },
  {
    id: 'name',
    header: 'Razón social',
    cell: (r) => r.name,
    sortable: true,
    sortKey: 'businessName',
  },
];

function renderGrid(overrides: Partial<DataGridProps<Row>> = {}) {
  const props: DataGridProps<Row> = {
    label: 'Clientes',
    columns,
    rows,
    getRowId: (r) => r.id,
    sort: [],
    onSortChange: vi.fn(),
    isPending: false,
    isFetching: false,
    isStale: false,
    emptyState: <p>Sin clientes</p>,
    mode: 'table',
    pagination: {
      page: 0,
      pageSize: 20,
      totalCount: 2,
      onPageChange: vi.fn(),
      onPageSizeChange: vi.fn(),
    },
    ...overrides,
  };
  return { props, ...render(<DataGrid {...props} />) };
}

describe('DataGrid', () => {
  afterEach(cleanup);

  describe('table', () => {
    it('is an accessible grid with a column header per column and a row per record', () => {
      renderGrid();

      const grid = screen.getByRole('grid', { name: 'Clientes' });
      expect(
        within(grid)
          .getAllByRole('columnheader')
          .map((h) => h.textContent),
      ).toEqual(['RUC/DNI', 'Razón social']);
      expect(within(grid).getAllByRole('row')).toHaveLength(3); // header + 2
      expect(within(grid).getByText('Titicaca SRL')).toBeTruthy();
    });

    it('adds and removes columns on the fly without breaking the rows (basic/full view)', () => {
      const phone: GridColumn<Row> = { id: 'phone', header: 'Teléfono', cell: () => '999' };
      const { props, rerender } = renderGrid();

      rerender(<DataGrid {...props} columns={[...columns, phone]} />);
      expect(screen.getAllByRole('columnheader').map((h) => h.textContent)).toEqual([
        'RUC/DNI',
        'Razón social',
        'Teléfono',
      ]);
      expect(
        screen.getAllByRole('row')[1]?.querySelectorAll('[role="gridcell"], [role="rowheader"]'),
      ).toHaveLength(3);

      rerender(<DataGrid {...props} columns={columns} />);
      expect(screen.getAllByRole('columnheader')).toHaveLength(2);
    });

    it('marks the sorted column with aria-sort', () => {
      renderGrid({ sort: [{ selector: 'businessName', desc: true }] });

      expect(
        screen.getByRole('columnheader', { name: /Razón social/ }).getAttribute('aria-sort'),
      ).toBe('descending');
      expect(screen.getByRole('columnheader', { name: /RUC/ }).getAttribute('aria-sort')).toBe(
        'none',
      );
    });

    it('sorts by the column sortKey (what the server knows it as), ascending first', () => {
      const { props } = renderGrid();

      fireEvent.click(screen.getByRole('columnheader', { name: /Razón social/ }));

      expect(props.onSortChange).toHaveBeenCalledWith([{ selector: 'businessName', desc: false }]);
    });

    it('flips to descending when the sorted column is clicked again', () => {
      const { props } = renderGrid({ sort: [{ selector: 'businessName', desc: false }] });

      fireEvent.click(screen.getByRole('columnheader', { name: /Razón social/ }));

      expect(props.onSortChange).toHaveBeenCalledWith([{ selector: 'businessName', desc: true }]);
    });

    it('exposes a per-row actions menu with a row-specific label', () => {
      renderGrid({
        rowActions: () => [{ label: 'Editar' }],
        rowActionsLabel: (r) => `Acciones de ${r.name}`,
      });

      expect(screen.getByRole('button', { name: 'Acciones de Andina SAC' })).toBeTruthy();
      expect(screen.getByRole('button', { name: 'Acciones de Titicaca SRL' })).toBeTruthy();
    });
  });

  describe('states', () => {
    it('shows a loading status instead of a table on the first load', () => {
      renderGrid({ isPending: true, rows: [] });

      expect(screen.getByRole('status', { name: 'Cargando…' })).toBeTruthy();
      expect(screen.queryByRole('grid')).toBeNull();
    });

    it('shows the empty state when there is nothing and no filter', () => {
      renderGrid({ rows: [] });

      expect(screen.getByText('Sin clientes')).toBeTruthy();
    });

    it('shows "no results" rather than the empty state when filters are active', () => {
      renderGrid({ rows: [], hasActiveFilters: true });

      expect(screen.getByText('Sin resultados')).toBeTruthy();
      expect(screen.queryByText('Sin clientes')).toBeNull();
    });

    it('shows an alert with a working retry when loading failed and nothing is on screen', () => {
      const onRetry = vi.fn();
      renderGrid({ rows: [], error: new Error('boom'), onRetry });

      expect(screen.getByRole('alert').textContent).toContain('No se pudo cargar la información');
      fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
      expect(onRetry).toHaveBeenCalledOnce();
    });

    it('keeps the rows when a refetch fails: stale data beats an empty screen', () => {
      renderGrid({ error: new Error('boom') });

      expect(screen.queryByRole('alert')).toBeNull();
      expect(screen.getByText('Andina SAC')).toBeTruthy();
    });

    it('flags a refetch with aria-busy and dims stale rows so they cannot be clicked', () => {
      const { container } = renderGrid({ isFetching: true, isStale: true });

      expect(container.querySelector('section')?.getAttribute('aria-busy')).toBe('true');
      expect(container.querySelector('.erp-grid__body')?.hasAttribute('data-stale')).toBe(true);
    });

    it('is not busy and not stale at rest', () => {
      const { container } = renderGrid();

      expect(container.querySelector('section')?.getAttribute('aria-busy')).toBe('false');
      expect(container.querySelector('.erp-grid__body')?.hasAttribute('data-stale')).toBe(false);
    });
  });

  describe('cards (small screens)', () => {
    it('renders one card per row using renderCard, with the actions menu', () => {
      renderGrid({
        mode: 'cards',
        renderCard: (r) => <strong>{r.name}</strong>,
        rowActions: () => [{ label: 'Editar' }],
        rowActionsLabel: (r) => `Acciones de ${r.name}`,
      });

      expect(screen.getAllByTestId('grid-card')).toHaveLength(2);
      expect(screen.queryByRole('grid')).toBeNull();
      expect(screen.getByRole('button', { name: 'Acciones de Andina SAC' })).toBeTruthy();
    });

    it('falls back to the table when no card renderer is given', () => {
      renderGrid({ mode: 'auto' });

      expect(screen.getByRole('grid')).toBeTruthy();
    });
  });

  describe('pagination', () => {
    const paged = {
      page: 1,
      pageSize: 20,
      totalCount: 41,
      onPageChange: vi.fn(),
      onPageSizeChange: vi.fn(),
    };

    it('summarises the visible range', () => {
      renderGrid({ pagination: paged });

      expect(screen.getByTestId('grid-summary').textContent).toBe('21-40 de 41 registros');
    });

    it('hands back 0-based pages even though ui/Pagination is 1-based', () => {
      const onPageChange = vi.fn();
      renderGrid({ pagination: { ...paged, page: 0, onPageChange } });

      fireEvent.click(screen.getByRole('button', { name: '3' })); // human page 3

      expect(onPageChange).toHaveBeenCalledWith(2);
    });

    it('shows the current page as current, not the page after it', () => {
      renderGrid({ pagination: { ...paged, page: 1 } });

      expect(screen.getByRole('button', { name: '2' }).getAttribute('aria-current')).toBe('page');
    });

    it('reports a new page size', () => {
      const onPageSizeChange = vi.fn();
      renderGrid({ pagination: { ...paged, onPageSizeChange } });

      fireEvent.change(screen.getByRole('combobox', { name: 'Filas por página' }), {
        target: { value: '50' },
      });

      expect(onPageSizeChange).toHaveBeenCalledWith(50);
    });

    it('says "0 registros" for an empty result', () => {
      renderGrid({ rows: [], pagination: { ...paged, page: 0, totalCount: 0 } });

      expect(screen.getByTestId('grid-summary').textContent).toBe('0 registros');
    });
  });
});
