import type { GridCellContext } from '@nubitio/crud';
import { Badge } from '@nubitio/ui';
import {
  PAYABLE_STATUS_LABEL,
  RECEIVABLE_STATUS_LABEL,
  entityLabel,
  formatDate,
  formatInvoiceNumber,
  formatMoney,
  formatMonthPeriod,
  payableStatus,
  receivableStatus,
  type PayableStatusKey,
  type ReceivableStatusKey,
} from '@shared/lib/displayFormat';

export function moneyCell(cell: GridCellContext): string {
  return formatMoney(cell.value, String(cell.data?.currency ?? 'PEN'));
}

export function dateCell(cell: GridCellContext): string {
  return formatDate(cell.value);
}

export function monthPeriodCell(cell: GridCellContext): string {
  return formatMonthPeriod(cell.value);
}

export function invoiceNumberCell(cell: GridCellContext): string {
  return formatInvoiceNumber(cell.value);
}

export function entityNameCell(cell: GridCellContext): string {
  return (
    entityLabel(cell.value) || entityLabel(cell.data?.supplier) || entityLabel(cell.data?.customer)
  );
}

const RECEIVABLE_BADGE: Record<
  ReceivableStatusKey,
  'success' | 'warning' | 'danger' | 'secondary'
> = {
  paid: 'success',
  pending: 'warning',
  partial: 'secondary',
  overdue: 'danger',
};

export function receivableStatusCell(cell: GridCellContext) {
  const key = receivableStatus(cell.data ?? {});
  return (
    <Badge variant={RECEIVABLE_BADGE[key]} outlined>
      {RECEIVABLE_STATUS_LABEL[key]}
    </Badge>
  );
}

const PAYABLE_BADGE: Record<PayableStatusKey, 'success' | 'warning' | 'secondary'> = {
  paid: 'success',
  pending: 'warning',
  partial: 'secondary',
};

export function payableStatusCell(cell: GridCellContext) {
  const key = payableStatus(cell.data ?? {});
  return (
    <Badge variant={PAYABLE_BADGE[key]} outlined>
      {PAYABLE_STATUS_LABEL[key]}
    </Badge>
  );
}

export const moneyPatch = {
  align: 'right' as const,
  formatter: moneyCell,
};

export const datePatch = {
  formatter: dateCell,
};

export const monthPeriodPatch = {
  formatter: monthPeriodCell,
};
