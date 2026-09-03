import { useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  DataGridView,
  HydraAdapter,
  currencyField,
  dateField,
  entityField,
  identityField,
  textField,
  type DataRecord,
  type GridCellContext,
} from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { saleEndpoints } from './api/saleEndpoints';
import { dateCell, entityNameCell, moneyCell, receivableStatusCell } from '@shared/ui/crudFormatters';
import { OwnerRowButtons } from '@shared/ui/OwnerRowButtons';
import { formatInvoiceNumber } from '@shared/lib/displayFormat';
import NumberUtils from '@shared/lib/NumberUtils';

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

  const fields = useMemo(
    () => [
      identityField().visible(false).visibleOnForm(false).build(),
      entityField(API + 'customers', 'id', 'businessName')
        .name('customer')
        .label('Cliente')
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
      currencyField().name('balanceDue').label('Saldo').align('right').formatter(moneyCell).build(),
      textField()
        .name('payments')
        .label('Pagos realizados')
        .filterable(false)
        .sortable(false)
        .minWidth(220)
        .formatter((cell: GridCellContext) => {
          const payments = (cell.value ?? []) as AccountsReceivableRecord['payments'];
          if (!payments?.length) return 'Sin pagos';
          return payments
            .map((payment) => {
              const date = payment.paid_at ? ` · ${String(payment.paid_at).slice(0, 10)}` : '';
              const reference = payment.reference ? ` · Ref. ${payment.reference}` : '';
              const currency = String((cell.data as AccountsReceivableRecord | undefined)?.currency ?? 'PEN');
              return `${payment.method_name ?? 'Pago'} ${NumberUtils.formatCurrency(Number(payment.amount ?? 0), currency)}${date}${reference}`;
            })
            .join(' | ');
        })
        .build(),
      textField().name('status').label('Estado').filterable(false).formatter(receivableStatusCell).build(),
      textField()
        .name('_collect')
        .label('')
        .width(200)
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as AccountsReceivableRecord | undefined;
          if (!row?.id) return null;
          const phone = customerPhone(row.customer ?? null);
          const name = entityNameCell(cell);
          const actions: Array<{ label: string; onClick: () => void; variant?: 'primary' | 'secondary' }> = [
            {
              label: 'Cobrar',
              onClick: () => navigate(`/sale/${row.id}/collect`),
            },
          ];
          if (phone) {
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
          return <OwnerRowButtons actions={actions} />;
        })
        .build(),
    ],
    [navigate],
  );

  return (
    <div id="wrapper">
      <DataGridView
        id="accounts-receivable-actions"
        title="Cuentas por cobrar"
        url={saleEndpoints.list()}
        fields={fields}
        adapter={HydraAdapter}
        filter={[{ field: 'balanceDue', operator: '>', value: 0 }]}
        filterRow
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        sort={[{ selector: 'dueDate', desc: false }]}
        emptyState={{
          title: 'Nadie te debe en este momento',
          description: 'Las ventas a crédito y los saldos importados desde Excel aparecen aquí.',
          icon: 'hand-coins',
        }}
      />
    </div>
  );
};
