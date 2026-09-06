import './AccountsReceivablePage.scss';
import { SaleCollectPayment } from './SaleCollectPayment';
import { useMemo, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  DataGridView,
  HydraAdapter,
  currencyField,
  dateField,
  entityField,
  identityField,
  textField,
  type DataRecord,
  type FilterRule,
  type GridHandle,
  type GridCellContext,
} from '@nubitio/crud';
import { AppDropdown } from '@nubitio/ui';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { saleEndpoints } from './api/saleEndpoints';
import { dateCell, entityNameCell, moneyCell, receivableStatusCell } from '@shared/ui/crudFormatters';
import { OwnerRowButtons } from '@shared/ui/OwnerRowButtons';
import { formatInvoiceNumber } from '@shared/lib/displayFormat';
import NumberUtils from '@shared/lib/NumberUtils';
import { hasPermission } from '@shared/permissions';

const API = getCoreApiBaseUrl();

interface AccountsReceivableRecord extends DataRecord {
  id: string;
  customer: { businessName?: string; phone?: string } | string | null;
  issuedDate: string;
  dueDate: string | null;
  grandTotal: string;
  paidTotal: string;
  balanceDue: string;
  currency?: string;
  payments?: Array<{ method_name?: string | null; amount?: string; paid_at?: string | null; reference?: string | null }>;
  invoiceFullNumber?: string | null;
  status: string;
}

function customerPhone(customer: AccountsReceivableRecord['customer']): string {
  if (customer && typeof customer === 'object' && typeof customer.phone === 'string') {
    return customer.phone.replace(/\D/g, '');
  }
  return '';
}

export const AccountsReceivablePage = () => {
  const navigate = useNavigate();
  const { id: routeSaleId } = useParams<{ id: string }>();
  const [selectedSaleId, setSelectedSaleId] = useState<string | null>(null);
  const gridRef = useRef<GridHandle>(null);
  const [paymentHistoryVersion, setPaymentHistoryVersion] = useState(0);
  const collectSaleId = selectedSaleId ?? routeSaleId;
  const closeCollection = () => {
    setSelectedSaleId(null);
    if (routeSaleId) navigate('/accounts-receivable');
  };
  const [accountView, setAccountView] = useState('pending');
  const accountFilters = useMemo<FilterRule[]>(() => [
    { field: 'receivableAccount', operator: '=', value: 1 },
    { field: 'status', operator: '<>', value: 'VOIDED' },
    ...(accountView === 'all' ? [] : [{ field: 'balanceDue', operator: accountView === 'paid' ? '=' : '>', value: 0 }]),
  ], [accountView]);
  const canCollect = hasPermission('sales.collect');

  const fields = useMemo(
    () => [
      identityField().visible(false).visibleOnForm(false).build(),
      entityField(API + 'customers', 'id', 'businessName')
        .name('customer')
        .label('Cliente')
        .cardRole('title')
        .formatter(entityNameCell)
        .build(),
      textField()
        .name('invoiceFullNumber')
        .label('Comprobante')
        .formatter((cell: GridCellContext) => formatInvoiceNumber(cell.value) || 'Saldo inicial')
        .build(),
      dateField().name('issuedDate').label('Fecha de emisión').formatter(dateCell).build(),
      dateField().name('dueDate').label('Fecha de vencimiento').formatter(dateCell).build(),
      currencyField().name('grandTotal').label('Total').align('right').formatter(moneyCell).build(),
      currencyField().name('paidTotal').label('Pagado').align('right').formatter(moneyCell).build(),
      currencyField().name('balanceDue').label('Saldo').cardRole('primary').align('right').formatter(moneyCell).build(),
      textField().name('status').label('Estado').filterable(false).formatter(receivableStatusCell).build(),
      textField()
        .name('_collect')
        .label('Acciones')
        .hideable(false)
        .cardRole('primary')
        .width(200)
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as AccountsReceivableRecord | undefined;
          if (!row?.id) return null;
          const phone = customerPhone(row.customer ?? null);
          const name = entityNameCell({ ...cell, value: row.customer });
          const actions: Array<{ label: string; onClick: () => void; variant?: 'primary' | 'secondary' }> = [];
          if (canCollect && Number(row.balanceDue) > 0) {
            actions.push({
              label: 'Cobrar',
              onClick: () => setSelectedSaleId(row.id),
            });
          }
          if (phone && Number(row.balanceDue) > 0) {
            actions.push({
              label: 'WhatsApp',
              variant: 'secondary',
              onClick: () => {
                const text = encodeURIComponent(
                  `Hola ${name}, te escribimos por el saldo pendiente ${formatInvoiceNumber(row.invoiceFullNumber) || ''}.`.trim(),
                );
                window.open(`https://wa.me/${phone}?text=${text}`, '_blank', 'noopener,noreferrer');
              },
            });
          }
          return <span data-sale-id={row.id}>{actions.length > 0 ? <OwnerRowButtons actions={actions} /> : null}</span>;
        })
        .build(),
    ],
    [canCollect],
  );

  const paymentFields = useMemo(() => (parent: DataRecord) => [
    dateField().name('paid_at').label('Fecha').formatter(dateCell).build(),
    textField().name('method_name').label('Método de pago').build(),
    currencyField().name('amount').label('Monto').align('right').formatter((cell: GridCellContext) =>
      NumberUtils.formatCurrency(Number(cell.value ?? 0), String(parent.currency ?? 'PEN'))).build(),
    textField().name('reference').label('Referencia').build(),
  ], []);

  return (
    <div id="wrapper" className="accounts-receivable-page">
      <DataGridView
        id="accounts-receivable-actions"
        ref={gridRef}
        detailUrl={`${saleEndpoints.list()}/{id}/payments?revision=${paymentHistoryVersion}`}
        detailFields={paymentFields}
        title="Cuentas por cobrar"
        url={saleEndpoints.list()}
        fields={fields}
        adapter={HydraAdapter}
        filter={accountFilters}
        beforeToolbar={() => (
          <AppDropdown
            id="receivable-account-view"
            label="Estado de cuenta"
            variant="toolbar"
            value={accountView}
            options={[{ value: 'pending', label: 'Pendientes' }, { value: 'paid', label: 'Pagadas' }, { value: 'all', label: 'Todas' }]}
            onChange={setAccountView}
          />
        )}
        filterRow
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        sort={[{ selector: 'dueDate', desc: false }, { selector: 'createdAt', desc: true }]}
        emptyState={{
          title: accountView === 'pending' ? 'Nadie te debe en este momento' : accountView === 'paid' ? 'No hay cuentas pagadas' : 'No hay cuentas por cobrar',
          description: 'Consulta Pendientes, Pagadas o Todas. Despliega cada cuenta para ver sus abonos.',
          icon: 'hand-coins',
        }}
      />
      {collectSaleId && canCollect && (
        <SaleCollectPayment
          key={collectSaleId}
          id={collectSaleId}
          onClose={closeCollection}
          onCollected={() => {
            closeCollection();
            setPaymentHistoryVersion((version) => version + 1);
            gridRef.current?.refresh();
          }}
        />
      )}
    </div>
  );
};
