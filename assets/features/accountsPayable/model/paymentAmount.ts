import { formatMinorUnits, toMinorUnits } from '@nubitio/core';

/** Supplier bills store DECIMAL(12,2); submit the canonical decimal, not a float. */
export function supplierPaymentAmount(
  raw: string,
  balance: string,
  currency: string,
): string | null {
  if (!/^\d{1,10}(\.\d{1,2})?$/.test(raw)) return null;
  const amount = toMinorUnits({ amount: raw, currency, scale: 2 });
  const remaining = toMinorUnits({ amount: balance, currency, scale: 2 });
  return amount > 0n && amount <= remaining ? formatMinorUnits(amount, 2) : null;
}
