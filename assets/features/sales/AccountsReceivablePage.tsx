import { useMemo, useState } from 'react';
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
import { Button } from '@nubitio/ui';
import { saleEndpoints } from './api/saleEndpoints';
import { dateCell, entityNameCell, moneyCell, receivableStatusCell } from '@shared/ui/crudFormatters';
import { OwnerRowButtons } from '@shared/ui/OwnerRowButtons';
import { addDays, formatInvoiceNumber, startOfToday, toIsoDate } from '@shared/lib/displayFormat';

const API = getCoreApiBaseUrl();

interface AccountsReceivableRecord extends DataRecord {
  id: string;
  customer: { businessName?: string; phone?: string } | string | null;
  issuedDate: string;
  dueDate: string | null;
  grandTotal: string;
  paidTotal: string;
  balanceDue: string;
  invoiceFullNumber?: string | null;
  status: string;
}

type AgingKey = 'all' | 'overdue' | 'week';

function customerPhone(customer: AccountsReceivableRecord['customer']): string {
  if (customer && typeof customer === 'object' && typeof customer.phone === 'string') {
    return customer.phone.replace(/\D/g, '');
  }
  return '';
}

export const AccountsReceivablePage = () => {
  const navigate = useNavigate();
  const [aging, setAging] = useState<AgingKey>('all');

  const filter = useMemo(() => {
    const rules: Array<{ field: string; operator: string; value: string | number }> = [
      { field: 'balanceDue', operator: '>', value: 0 },
    ];
    const today = startOfToday();
    if (aging === 'overdue') {
      rules.push({ field: 'dueDate', operator: '<', value: toIsoDate(today) });
    }
    if (aging === 'week') {
      rules.push({ field: 'dueDate', operator: '>=', value: toIsoDate(today) });
      rules.push({ field: 'dueDate', operator: '<=', value: toIsoDate(addDays(today, 7)) });
    }
    return rules;
  }, [aging]);

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
      currencyField().name('balanceDue').label('Saldo').align('right').formatter(moneyCell).build(),
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
        filter={filter}
        filterRow={false}
        allowAdd={false}
        allowEdit={false}
        allowDelete={false}
        sort={[{ selector: 'dueDate', desc: false }]}
        emptyState={{
          title: 'Nadie te debe en este momento',
          description: 'Las ventas a crédito y los saldos importados desde Excel aparecen aquí.',
          icon: 'hand-coins',
        }}
        aboveGrid={
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, margin: '0 0 12px' }}>
            {(
              [
                ['all', 'Todas'],
                ['overdue', 'Vencidas'],
                ['week', 'Vencen en 7 días'],
              ] as const
            ).map(([key, label]) => (
              <Button
                key={key}
                size="sm"
                variant={aging === key ? 'primary' : 'secondary'}
                onClick={() => setAging(key)}
              >
                {label}
              </Button>
            ))}
          </div>
        }
      />
    </div>
  );
};
