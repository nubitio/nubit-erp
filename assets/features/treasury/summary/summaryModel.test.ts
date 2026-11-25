import { describe, expect, it } from 'vitest';
import type { FinanceSummary } from '../api/treasuryApi';
import { attentionItems, currencyTitle } from './summaryModel';

const money = (value: string, currency: string) => `${currency} ${value}`;
const everything = () => true;

const empty = {
  balance: '0.00',
  overdue: '0.00',
  dueWithinSevenDays: '0.00',
  dueWithinThirtyDays: '0.00',
  count: 0,
  overdueCount: 0,
};

const summary = (
  over: Partial<FinanceSummary['attention']> = {},
  overdueIn = 0,
  overdueOut = 0,
): FinanceSummary => ({
  asOf: '2026-11-18',
  currencies: [
    {
      currency: 'PEN',
      position: {
        total: '0.00',
        cash: '0.00',
        banks: '0.00',
        wallets: '0.00',
        clearing: '0.00',
        suspense: '0.00',
      },
      receivables: { ...empty, overdueCount: overdueIn },
      payables: { ...empty, overdueCount: overdueOut },
      projectedInThirtyDays: '0.00',
    },
  ],
  attention: {
    unreconciledBankMovements: 0,
    suspense: [],
    accountsWithoutOpeningBalance: [],
    ...over,
  },
});

describe('finance summary model', () => {
  it('has nothing to attend when everything is in order', () => {
    expect(attentionItems(summary(), everything, money)).toEqual([]);
  });

  it('puts overdue payables first, then overdue receivables, then housekeeping', () => {
    const items = attentionItems(
      summary({ unreconciledBankMovements: 3, accountsWithoutOpeningBalance: ['BCP'] }, 2, 1),
      everything,
      money,
    );

    expect(items.map((i) => i.key)).toEqual([
      'overdue-payables',
      'overdue-receivables',
      'unreconciled',
      'opening-balance',
    ]);
    expect(items[0]?.label).toBe('1 pago a proveedor vencido');
    expect(items[1]?.label).toBe('2 cobros vencidos');
    expect(items[2]?.label).toBe('3 movimientos del banco sin conciliar');
  });

  it('adds up what is overdue across currencies', () => {
    const s = summary({}, 1, 0);
    s.currencies.push({
      ...s.currencies[0]!,
      currency: 'USD',
      receivables: { ...empty, overdueCount: 2 },
    });

    expect(attentionItems(s, everything, money)[0]?.label).toBe('3 cobros vencidos');
  });

  it('names the money parked in suspense per currency and points at the money accounts', () => {
    const items = attentionItems(
      summary({
        suspense: [
          { currency: 'PEN', balance: '15.00' },
          { currency: 'USD', balance: '4.00' },
        ],
      }),
      everything,
      money,
    );

    expect(items.map((i) => i.key)).toEqual(['suspense-PEN', 'suspense-USD']);
    expect(items[0]?.label).toContain('PEN 15.00');
    expect(items[0]?.to).toBe('/treasury');
  });

  it('leaves out what the user cannot open', () => {
    const items = attentionItems(
      summary({ unreconciledBankMovements: 3 }, 2, 1),
      (permission) => permission === 'sales.view',
      money,
    );

    expect(items.map((i) => i.key)).toEqual(['overdue-receivables']);
  });

  it('names currencies it knows and shows the code of the rest', () => {
    expect(currencyTitle('PEN')).toBe('Soles (PEN)');
    expect(currencyTitle('USD')).toBe('Dólares (USD)');
    expect(currencyTitle('EUR')).toBe('EUR');
  });
});
