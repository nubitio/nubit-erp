import { useEffect, useMemo, useRef } from 'react';
import {
  DataGridView,
  HydraAdapter,
  currencyField,
  dateField,
  identityField,
  numberField,
  textField,
  type DataRecord,
  type GridCellContext,
  type GridHandle,
} from '@nubitio/crud';
import { Badge, Button, Stack, Text } from '@nubitio/ui';
import { moneyCell } from '@shared/ui/crudFormatters';
import { hasPermission } from '@shared/permissions';
import { saleEndpoints } from './api/saleEndpoints';
import type { ReceivableCustomer } from './CustomerReceivableDrawer';

export interface CustomerReceivableRecord extends DataRecord {
  id: string;
  customerId: number;
  customerName: string;
  customerPhone: string | null;
  totalBalance: string;
  currency: string;
  earliestDueDate: string | null;
  overdueDays: number | null;
  agingBucket: string;
  pendingCount: number;
}

interface Props {
  refreshKey?: number;
  onOpenDetail: (customer: ReceivableCustomer) => void;
  onCollect: (customer: ReceivableCustomer) => void;
}

const AGING_TONE: Record<string, 'success' | 'warning' | 'danger'> = {
  current: 'success',
  '1-30': 'warning',
  '31-60': 'warning',
  '61-90': 'danger',
  '90+': 'danger',
};

function toCustomer(row: CustomerReceivableRecord): ReceivableCustomer {
  return {
    id: row.customerId,
    name: row.customerName,
    totalBalance: row.totalBalance,
    currency: row.currency,
  };
}

function dueStatusLabel(row: CustomerReceivableRecord): string {
  if (row.earliestDueDate == null) return 'Al día';
  const days = row.overdueDays ?? 0;
  if (days <= 0) {
    const daysUntil = -days;
    return daysUntil === 0
      ? 'Vence hoy'
      : `Vence en ${daysUntil} ${daysUntil === 1 ? 'día' : 'días'}`;
  }
  return `Vencido hace ${days} ${days === 1 ? 'día' : 'días'}`;
}

function formatDateHuman(value: string | null): string {
  if (!value) return '';
  return new Intl.DateTimeFormat('es-PE', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`));
}

export const CustomerReceivablesGrid = ({ refreshKey = 0, onOpenDetail, onCollect }: Props) => {
  const gridRef = useRef<GridHandle>(null);
  const canCollect = hasPermission('sales.collect');
  const isFirstRender = useRef(true);

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    gridRef.current?.refresh();
  }, [refreshKey]);

  const fields = useMemo(
    () => [
      identityField().visible(false).visibleOnForm(false).build(),
      textField()
        .name('customerName')
        .label('Cliente')
        .cardRole('title')
        .filterable(true)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as CustomerReceivableRecord | undefined;
          if (!row) return null;
          return (
            <button
              type="button"
              className="accounts-receivable-page__customer-link"
              onClick={(event) => {
                event.stopPropagation();
                onOpenDetail(toCustomer(row));
              }}
            >
              {row.customerName}
            </button>
          );
        })
        .build(),
      currencyField()
        .name('totalBalance')
        .label('Saldo')
        .cardRole('primary')
        .align('right')
        .width(120)
        .filterable(false)
        .formatter(moneyCell)
        .build(),
      dateField()
        .name('earliestDueDate')
        .label('Vencimiento')
        .width(180)
        .filterable(false)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as CustomerReceivableRecord | undefined;
          if (!row) return null;
          const tone = AGING_TONE[row.agingBucket] ?? 'secondary';
          return (
            <Stack gap={1}>
              <Badge variant={tone} outlined>
                {dueStatusLabel(row)}
              </Badge>
              <Text tone="muted">{formatDateHuman(row.earliestDueDate)}</Text>
            </Stack>
          );
        })
        .build(),
      numberField()
        .name('pendingCount')
        .label('Comprobantes')
        .align('right')
        .width(110)
        .filterable(false)
        .build(),
      textField()
        .name('_collect')
        .label('Acciones')
        .hideable(false)
        .width(110)
        .filterable(false)
        .sortable(false)
        .formatter((cell: GridCellContext) => {
          const row = cell.data as CustomerReceivableRecord | undefined;
          if (!row || !canCollect) return null;
          return (
            <span className="owner-row-buttons" onClick={(event) => event.stopPropagation()}>
              <Button size="sm" variant="primary" onClick={() => onCollect(toCustomer(row))}>
                Cobrar
              </Button>
            </span>
          );
        })
        .build(),
    ],
    [canCollect, onCollect, onOpenDetail],
  );

  return (
    <DataGridView
      id="accounts-receivable-debtors"
      ref={gridRef}
      title="Deudores"
      url={saleEndpoints.receivableCustomers()}
      fields={fields}
      adapter={HydraAdapter}
      filterRow
      allowAdd={false}
      allowEdit={false}
      allowDelete={false}
      sort={[{ selector: 'totalBalance', desc: true }]}
      emptyState={{
        title: 'Nadie te debe en este momento',
        description:
          'En cuanto un cliente tenga un saldo pendiente, aparecerá aquí con su deuda total.',
        icon: 'hand-coins',
      }}
    />
  );
};
