import { formatMinorUnits, toMinorUnits } from '@nubitio/core';

export const CASH_DENOMINATIONS = [
  20000, 10000, 5000, 2000, 1000, 500, 200, 100, 50, 20, 10,
] as const;
export const CASH_METHOD_ID = 1; // The summary/close providers define cash with this ID.
export function cashMinor(amount: string | number): bigint {
  return toMinorUnits({ amount: String(amount), currency: 'PEN', scale: 2 });
}
export function denominationTotal(counts: Record<string, string>): string | undefined {
  let total = 0n;
  let entered = false;
  for (const denomination of CASH_DENOMINATIONS) {
    const count = counts[denomination] ?? '';
    if (!count) continue;
    entered = true;
    if (!/^\d+$/.test(count) || Number(count) > 999999) return undefined;
    total += BigInt(denomination) * BigInt(count);
  }
  return entered ? formatMinorUnits(total, 2) : undefined;
}
export function parseCashCount(value: string): string | undefined {
  // cash_sessions stores DECIMAL(12,2); preserve the decimal literal for the API.
  return /^\d{1,10}(\.\d{0,2})?$/.test(value) ? value : undefined;
}
