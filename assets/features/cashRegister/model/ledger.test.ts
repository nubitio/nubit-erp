import { describe, expect, it } from 'vitest';
import type { CashLedgerEntry } from '../services/cashSessionService';
import { countByFilter, filterLedger } from './ledger';

const entry = (
  id: string,
  kind: CashLedgerEntry['kind'],
  paymentMethod: string,
): CashLedgerEntry => ({
  id,
  at: '2026-11-23T10:00:00-05:00',
  kind,
  document: null,
  paymentMethod,
  description: '',
  amount: '1.00',
});

const entries = [
  entry('1', 'sale', 'Efectivo'),
  entry('2', 'sale', 'Yape'),
  entry('3', 'expense', 'Efectivo'),
  entry('4', 'withdrawal', 'Efectivo'),
  entry('5', 'opening', 'Efectivo'),
];

describe('ledger filters', () => {
  it('counts by kind, with the opening only in the total', () => {
    expect(countByFilter(entries)).toEqual({
      all: 5,
      sale: 2,
      income: 0,
      expense: 1,
      withdrawal: 1,
    });
  });

  it('combines the kind and the payment method', () => {
    expect(filterLedger(entries, 'sale', null).map((e) => e.id)).toEqual(['1', '2']);
    expect(filterLedger(entries, 'sale', 'Yape').map((e) => e.id)).toEqual(['2']);
    expect(filterLedger(entries, 'all', 'Efectivo').map((e) => e.id)).toEqual(['1', '3', '4', '5']);
  });
});
