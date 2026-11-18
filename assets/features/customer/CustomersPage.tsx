import { useScreenSize } from '@nubitio/admin';
import {
  Badge,
  Button,
  ConfirmDialog,
  EmptyState,
  Page,
  SegmentedControl,
  TextField,
  type ContextMenuItem,
} from '@nubitio/ui';
import { useEffect, useMemo, useRef, useState } from 'react';
import { hasPermission } from '@shared/permissions';
import toast from '@shared/lib/SharedUtils';
import { DataGrid, type GridColumn } from '@shared/ui/data-grid/DataGrid';
import {
  exportCustomers,
  useCustomerGrid,
  useDeleteCustomer,
  type CustomerStatusFilter,
} from './api/customerData';
import { CustomerAccountPanel } from './account/CustomerAccountPanel';
import { CustomerDrawer, type CustomerDrawerState } from './CustomerDrawer';
import { priceListOf, type Customer } from './form/customerForm';
import './CustomersPage.scss';

type ColumnSet = 'basic' | 'full';
const COLUMN_SET_KEY = 'customers.columns';

function readColumnSet(): ColumnSet {
  try {
    return localStorage.getItem(COLUMN_SET_KEY) === 'full' ? 'full' : 'basic';
  } catch {
    return 'basic'; // storage can be blocked; the screen works without remembering
  }
}

const SEARCH_DEBOUNCE_MS = 250;

function StatusBadge({ active }: { active: boolean }) {
  return (
    <Badge variant={active ? 'success' : 'secondary'} size="sm">
      {active ? 'Activo' : 'Inactivo'}
    </Badge>
  );
}

function CustomerCard({ customer }: { customer: Customer }) {
  const rows: Array<[string, string | null]> = [
    ['Razón social', customer.businessName],
    ['Teléfono', customer.phone],
    ['Correo', customer.email],
  ];

  return (
    <div className="customers__card">
      <strong className="customers__card-title">{customer.ruc}</strong>
      <dl className="customers__card-list">
        {rows.map(([label, value]) => (
          <div key={label}>
            <dt>{label}</dt>
            <dd>{value || '—'}</dd>
          </div>
        ))}
        <div>
          <dt>Estado</dt>
          <dd>
            <StatusBadge active={customer.isActive} />
          </dd>
        </div>
      </dl>
    </div>
  );
}

export function CustomersPage() {
  const grid = useCustomerGrid();
  const remove = useDeleteCustomer();
  const { isXSmall } = useScreenSize();

  const canAdd = hasPermission('customers.create');
  const canEdit = hasPermission('customers.edit');
  const canDelete = hasPermission('customers.delete');
  const canViewAccount = hasPermission('sales.view');

  const [drawer, setDrawer] = useState<CustomerDrawerState>({ mode: 'closed' });
  const openings = useRef(0);
  const openCreate = () => setDrawer({ mode: 'create', key: ++openings.current });
  const [toDelete, setToDelete] = useState<Customer | null>(null);
  const [accountOf, setAccountOf] = useState<Customer | null>(null);
  const [exporting, setExporting] = useState(false);
  const [columnSet, setColumnSet] = useState<ColumnSet>(readColumnSet);

  // Typing is debounced into the query: one request per pause, not per keystroke.
  const [searchInput, setSearchInput] = useState('');
  const { setSearch } = grid;
  useEffect(() => {
    const timer = setTimeout(() => setSearch(searchInput), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [searchInput, setSearch]);

  const changeColumnSet = (next: ColumnSet) => {
    setColumnSet(next);
    try {
      localStorage.setItem(COLUMN_SET_KEY, next);
    } catch {
      /* not remembered, still applied */
    }
  };

  const openEdit = (customer: Customer) =>
    setDrawer({ mode: 'edit', customer, key: ++openings.current });

  const columns = useMemo<GridColumn<Customer>[]>(() => {
    const base: GridColumn<Customer>[] = [
      {
        id: 'ruc',
        header: 'RUC/DNI',
        sortable: true,
        width: 150,
        cell: (c) =>
          canEdit ? (
            <button type="button" className="erp-link-button" onClick={() => openEdit(c)}>
              {c.ruc}
            </button>
          ) : (
            c.ruc
          ),
      },
      { id: 'businessName', header: 'Razón social', sortable: true, cell: (c) => c.businessName },
      { id: 'phone', header: 'Teléfono', cell: (c) => c.phone ?? '' },
      { id: 'email', header: 'Correo', cell: (c) => c.email ?? '' },
    ];
    const full: GridColumn<Customer>[] =
      columnSet === 'full'
        ? [
            { id: 'fiscalAddress', header: 'Dirección fiscal', cell: (c) => c.fiscalAddress ?? '' },
            { id: 'priceList', header: 'Tarifa', cell: (c) => priceListOf(c)?.label ?? '' },
          ]
        : [];

    return [
      ...base,
      ...full,
      {
        id: 'isActive',
        header: 'Cliente activo',
        sortable: true,
        width: 140,
        cell: (c) => <StatusBadge active={c.isActive} />,
      },
    ];
  }, [canEdit, columnSet]);

  const rowActions = useMemo(() => {
    if (!canEdit && !canDelete && !canViewAccount) return undefined;
    return (customer: Customer): ContextMenuItem[] => {
      const items: ContextMenuItem[] = [];
      if (canViewAccount)
        items.push({ label: 'Ver cuenta', onClick: () => setAccountOf(customer) });
      if (canEdit) items.push({ label: 'Editar', onClick: () => openEdit(customer) });
      if (canDelete)
        items.push({
          label: 'Eliminar',
          danger: true,
          separator: canEdit || canViewAccount,
          onClick: () => setToDelete(customer),
        });
      return items;
    };
  }, [canEdit, canDelete, canViewAccount]);

  const confirmDelete = async () => {
    if (!toDelete) return;
    const target = toDelete;
    try {
      await remove.mutateAsync(target.id);
      toast('Cliente eliminado', 'success');
    } catch (error) {
      const data = (error as { data?: { detail?: string; message?: string } } | null)?.data;
      toast(data?.detail ?? data?.message ?? 'No se pudo eliminar el cliente', 'error', 6000);
    } finally {
      setToDelete(null);
    }
  };

  const runExport = async () => {
    setExporting(true);
    try {
      await exportCustomers(grid.exportParams);
    } finally {
      setExporting(false);
    }
  };

  const status = (grid.state.filters['status'] ?? '') as CustomerStatusFilter;
  const hasActiveFilters = grid.state.search.trim() !== '' || status !== '';

  return (
    <Page wide fill className="customers">
      <h1 className="customers__title">Clientes</h1>

      <div className="customers__toolbar" role="search" data-testid="customers-toolbar">
        <TextField
          type="search"
          aria-label="Buscar clientes"
          placeholder="Buscar por RUC/DNI, razón social, teléfono o correo"
          value={searchInput}
          onChange={(event) => setSearchInput(event.target.value)}
        />
        <SegmentedControl<CustomerStatusFilter>
          ariaLabel="Estado del cliente"
          size="sm"
          value={status}
          onChange={(value) => grid.setFilter('status', value)}
          options={[
            { value: '', label: 'Todos' },
            { value: 'active', label: 'Activos' },
            { value: 'inactive', label: 'Inactivos' },
          ]}
        />
        {!isXSmall && (
          <SegmentedControl<ColumnSet>
            ariaLabel="Columnas"
            size="sm"
            value={columnSet}
            onChange={changeColumnSet}
            options={[
              { value: 'basic', label: 'Básico' },
              { value: 'full', label: 'Completo' },
            ]}
          />
        )}
        <div className="customers__actions">
          <Button
            variant="secondary"
            icon="ph ph-download-simple"
            loading={exporting}
            disabled={exporting}
            onClick={runExport}
          >
            Exportar
          </Button>
          <Button
            variant="secondary"
            icon="ph ph-arrow-clockwise"
            aria-label="Actualizar"
            onClick={() => grid.refetch()}
          />
          {canAdd && !isXSmall && (
            <Button icon="ph ph-plus" onClick={openCreate}>
              Nuevo
            </Button>
          )}
        </div>
      </div>

      <DataGrid<Customer>
        testId="customers-grid"
        label="Clientes"
        columns={columns}
        rows={grid.rows}
        getRowId={(c) => String(c.id)}
        sort={grid.state.sort}
        onSortChange={grid.setSort}
        isPending={grid.isPending}
        isFetching={grid.isFetching}
        isStale={grid.isStale}
        error={grid.error}
        onRetry={() => grid.refetch()}
        hasActiveFilters={hasActiveFilters}
        emptyState={
          <EmptyState
            icon="users"
            title="Sin clientes"
            description="Crea un cliente o impórtalos desde Inicio → Importar Excel."
            action={canAdd ? <Button onClick={openCreate}>Nuevo cliente</Button> : undefined}
          />
        }
        rowActions={rowActions}
        rowActionsLabel={(c) => `Acciones de ${c.businessName}`}
        renderCard={(c) => <CustomerCard customer={c} />}
        pagination={{
          page: grid.state.page,
          pageSize: grid.state.pageSize,
          totalCount: grid.totalCount,
          onPageChange: grid.setPage,
          onPageSizeChange: grid.setPageSize,
        }}
      />

      {canAdd && isXSmall && (
        <button type="button" className="erp-fab" aria-label="Nuevo cliente" onClick={openCreate}>
          <i className="ph ph-plus" aria-hidden />
        </button>
      )}

      <CustomerDrawer state={drawer} onClose={() => setDrawer({ mode: 'closed' })} />
      <CustomerAccountPanel customer={accountOf} onClose={() => setAccountOf(null)} />

      <ConfirmDialog
        open={toDelete !== null}
        title="Eliminar cliente"
        message={
          toDelete ? `¿Eliminar a ${toDelete.businessName}? Esta acción no se puede deshacer.` : ''
        }
        confirmLabel="Eliminar"
        variant="danger"
        onConfirm={confirmDelete}
        onCancel={() => setToDelete(null)}
      />
    </Page>
  );
}
