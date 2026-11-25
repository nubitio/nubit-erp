import type {
  AccountKind,
  MethodAccountMapping,
  PaymentMethodRef,
  TreasuryAccount,
} from '../api/treasuryApi';

export const KIND_LABEL: Record<AccountKind, string> = {
  cash_register: 'Caja',
  bank: 'Banco',
  wallet: 'Billetera',
  clearing: 'Fondos por liquidar',
  suspense: 'Sin asignar',
};

/** Accounts a payment method can land in: banks and wallets only (cash is the drawer, suspense is a holding pen). */
export function mappableAccounts(accounts: TreasuryAccount[], currency: string): TreasuryAccount[] {
  return accounts.filter(
    (account) =>
      account.active &&
      account.currency === currency &&
      (account.kind === 'bank' || account.kind === 'wallet'),
  );
}

/** Currencies that have at least one account a method could map to, in a stable order. */
export function mappableCurrencies(accounts: TreasuryAccount[]): string[] {
  const found = new Set(
    accounts
      .filter((a) => a.active && (a.kind === 'bank' || a.kind === 'wallet'))
      .map((a) => a.currency),
  );

  return [...found].sort();
}

/** Methods that move money into an account the admin chooses: not cash, and not credit (settles later). */
export function mappableMethods(methods: PaymentMethodRef[]): PaymentMethodRef[] {
  return methods.filter((method) => !method.isCash && method.settlesImmediately);
}

export function mappedAccountId(
  mappings: MethodAccountMapping[],
  branchId: string,
  paymentMethodId: number,
  currency: string,
): string | null {
  return (
    mappings.find(
      (m) =>
        m.branchId === branchId && m.paymentMethodId === paymentMethodId && m.currency === currency,
    )?.accountId ?? null
  );
}

/** Balance parked in suspense per currency, as decimal strings; only currencies with a non-zero balance. */
export function suspenseBalances(accounts: TreasuryAccount[]): Array<[string, string]> {
  return accounts
    .filter((a) => a.kind === 'suspense' && Number(a.balance) !== 0)
    .map((a): [string, string] => [a.currency, a.balance]);
}

/** True when there is nothing to pick for a payment method yet: the admin must create a bank or wallet first. */
export function needsAccounts(accounts: TreasuryAccount[]): boolean {
  return !accounts.some((a) => a.active && (a.kind === 'bank' || a.kind === 'wallet'));
}
