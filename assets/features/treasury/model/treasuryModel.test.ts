import { describe, expect, it } from 'vitest';
import type { PaymentMethodRef, TreasuryAccount } from '../api/treasuryApi';
import {
  mappableAccounts,
  mappableCurrencies,
  mappableMethods,
  mappedAccountId,
  needsAccounts,
  suspenseBalances,
} from './treasuryModel';

const account = (over: Partial<TreasuryAccount>): TreasuryAccount => ({
  id: 'a',
  kind: 'bank',
  name: 'BCP',
  currency: 'PEN',
  branchId: null,
  cashRegisterId: null,
  bankAccountId: null,
  active: true,
  balance: '0.00',
  ...over,
});

describe('treasury model', () => {
  const accounts = [
    account({ id: 'bcp' }),
    account({ id: 'yape', kind: 'wallet', name: 'Yape' }),
    account({ id: 'usd', currency: 'USD' }),
    account({ id: 'off', active: false }),
    account({ id: 'drawer', kind: 'cash_register' }),
    account({ id: 'susp', kind: 'suspense', balance: '45.50' }),
  ];

  it('offers only active banks and wallets of the currency, never drawers or suspense', () => {
    expect(mappableAccounts(accounts, 'PEN').map((a) => a.id)).toEqual(['bcp', 'yape']);
    expect(mappableAccounts(accounts, 'USD').map((a) => a.id)).toEqual(['usd']);
  });

  it('lists the currencies that can be mapped, sorted', () => {
    expect(mappableCurrencies(accounts)).toEqual(['PEN', 'USD']);
  });

  it('leaves out cash and credit methods', () => {
    const methods: PaymentMethodRef[] = [
      { id: 1, name: 'Efectivo', isCash: true, settlesImmediately: true },
      { id: 2, name: 'Credito', isCash: false, settlesImmediately: false },
      { id: 6, name: 'Yape', isCash: false, settlesImmediately: true },
    ];
    expect(mappableMethods(methods).map((m) => m.name)).toEqual(['Yape']);
  });

  it('finds the mapping by branch, method and currency', () => {
    const mappings = [{ branchId: 'b1', paymentMethodId: 6, currency: 'PEN', accountId: 'yape' }];
    expect(mappedAccountId(mappings, 'b1', 6, 'PEN')).toBe('yape');
    expect(mappedAccountId(mappings, 'b1', 6, 'USD')).toBeNull();
    expect(mappedAccountId(mappings, 'b2', 6, 'PEN')).toBeNull();
  });

  it('reports the suspense balance per currency and ignores an empty one', () => {
    expect(suspenseBalances(accounts)).toEqual([['PEN', '45.50']]);
    expect(suspenseBalances([account({ kind: 'suspense', balance: '0.00' })])).toEqual([]);
  });

  it('says accounts are needed until a bank or wallet exists', () => {
    expect(needsAccounts([account({ kind: 'cash_register' }), account({ kind: 'suspense' })])).toBe(
      true,
    );
    expect(needsAccounts(accounts)).toBe(false);
    expect(needsAccounts([account({ kind: 'bank', active: false })])).toBe(true);
  });
});
