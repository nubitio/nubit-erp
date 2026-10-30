import { formatMinorUnits, toMinorUnits } from '@nubitio/core';

export interface ReceivableSummary {
  totalBalance: string;
  currency: string;
  debtorCount: number;
  overdueTotal: string;
  notYetDueTotal: string;
  aging: { bucket: string; label: string; total: string }[];
}

/** Group the API's two oldest buckets without rounding decimal balances. */
export function receivableAging(summary: ReceivableSummary) {
  const amount = (bucket: string) =>
    summary.aging.find((entry) => entry.bucket === bucket)?.total ?? '0.00';
  const minor = (value: string) =>
    toMinorUnits({ amount: value, currency: summary.currency, scale: 2 });
  return [
    { label: 'Al día', amount: summary.notYetDueTotal, tone: 'success' as const },
    { label: 'Vencido 1–30 días', amount: amount('1-30'), tone: 'warning' as const },
    { label: 'Vencido 31–60 días', amount: amount('31-60'), tone: 'danger' as const },
    {
      label: 'Más de 60 días',
      amount: formatMinorUnits(minor(amount('61-90')) + minor(amount('90+')), 2),
      tone: 'danger' as const,
    },
  ];
}
