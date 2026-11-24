import type { CashLedgerEntry, CashLedgerKind } from '../services/cashSessionService';

export type LedgerFilter = 'all' | 'sale' | 'income' | 'expense' | 'withdrawal';

export const KIND_LABEL: Record<CashLedgerKind, string> = {
  opening: 'Apertura',
  sale: 'Venta',
  income: 'Ingreso',
  expense: 'Egreso',
  withdrawal: 'Retiro',
};

export function countByFilter(entries: CashLedgerEntry[]): Record<LedgerFilter, number> {
  const counts: Record<LedgerFilter, number> = {
    all: entries.length,
    sale: 0,
    income: 0,
    expense: 0,
    withdrawal: 0,
  };
  for (const entry of entries) {
    if (entry.kind !== 'opening') counts[entry.kind] += 1;
  }
  return counts;
}

/** The entries a filter and a payment method leave; `null` for the method means every method. */
export function filterLedger(
  entries: CashLedgerEntry[],
  filter: LedgerFilter,
  paymentMethod: string | null,
): CashLedgerEntry[] {
  return entries.filter(
    (entry) =>
      (filter === 'all' || entry.kind === filter) &&
      (paymentMethod === null || entry.paymentMethod === paymentMethod),
  );
}
