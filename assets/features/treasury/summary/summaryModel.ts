import type { FinanceSummary } from '../api/treasuryApi';

export interface AttentionItem {
  key: string;
  /** What needs the owner, in a sentence. */
  label: string;
  /** Where to go to deal with it. */
  to: string;
  tone: 'danger' | 'warning';
}

const CURRENCY_NAME: Record<string, string> = { PEN: 'Soles', USD: 'Dólares' };

/** "Soles (PEN)"; a currency we have no name for is shown by its code. */
export function currencyTitle(currency: string): string {
  const name = CURRENCY_NAME[currency];

  return name ? `${name} (${currency})` : currency;
}

const plural = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;

/**
 * What needs attention today, most urgent first. Each item points at the screen where it is solved, and
 * only the items the user is allowed to open are returned (`can` answers for a permission).
 */
export function attentionItems(
  summary: FinanceSummary,
  can: (permission: string) => boolean,
  formatMoney: (value: string, currency: string) => string,
): AttentionItem[] {
  const items: AttentionItem[] = [];

  const overdueIn = summary.currencies.reduce((sum, c) => sum + c.receivables.overdueCount, 0);
  const overdueOut = summary.currencies.reduce((sum, c) => sum + c.payables.overdueCount, 0);

  if (overdueOut > 0 && can('accounts_payable.view')) {
    items.push({
      key: 'overdue-payables',
      label: `${plural(overdueOut, 'pago a proveedor vencido', 'pagos a proveedores vencidos')}`,
      to: '/accounts-payable',
      tone: 'danger',
    });
  }
  if (overdueIn > 0 && can('sales.view')) {
    items.push({
      key: 'overdue-receivables',
      label: `${plural(overdueIn, 'cobro vencido', 'cobros vencidos')}`,
      to: '/accounts-receivable',
      tone: 'danger',
    });
  }
  if (summary.attention.unreconciledBankMovements > 0 && can('bank_reconciliation.view')) {
    items.push({
      key: 'unreconciled',
      label: `${plural(
        summary.attention.unreconciledBankMovements,
        'movimiento del banco sin conciliar',
        'movimientos del banco sin conciliar',
      )}`,
      to: '/bank-reconciliation',
      tone: 'warning',
    });
  }
  for (const parked of summary.attention.suspense) {
    items.push({
      key: `suspense-${parked.currency}`,
      label: `${formatMoney(parked.balance, parked.currency)} sin asignar a una cuenta: asigna los métodos de pago`,
      to: '/treasury',
      tone: 'warning',
    });
  }
  if (summary.attention.accountsWithoutOpeningBalance.length > 0) {
    items.push({
      key: 'opening-balance',
      label: `Falta el saldo inicial de ${summary.attention.accountsWithoutOpeningBalance.join(', ')}`,
      to: '/treasury',
      tone: 'warning',
    });
  }

  return items;
}
