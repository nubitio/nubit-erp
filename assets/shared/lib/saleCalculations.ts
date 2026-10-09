/**
 * Sale Calculation Utilities
 *
 * Single source of truth for all sale-related monetary calculations.
 * Used by both the POS Workspace and the Administrative Sale Editor.
 *
 * Pricing model: tax-inclusive (IGV is already embedded in unitPrice).
 * Discount convention: all percentages are in [0, 100] — never fractions.
 *
 * TAX RATE POLICY
 * ───────────────
 * `IGV_RATE` (0.18) is the **fallback default only**.
 * The authoritative rate is `TenantSettings.igv` fetched from /api/tenant/settings.
 * Tenants may configure different rates (e.g. 0.105 for restaurants).
 * Always pass an explicit `taxRate` from `useTenantIgvRate()` rather than relying
 * on the default.
 *
 * TAX TYPE POLICY
 * ───────────────
 * Each sale line carries a `taxType` (SunatTaxType). Only gravado lines ('10')
 * contribute to the IGV base. Exonerado ('20') and inafecto/export ('30','40')
 * lines carry zero embedded tax.
 * When `taxType` is absent it defaults to `'10'` (gravado) — matching existing
 * behaviour for all current products which have no explicit tax classification.
 */

import type { Discount, SaleTotals, SunatTaxType } from '@shared/domain/sale.types';
import { formatMinorUnits } from '@nubitio/core';

/**
 * The standard Peruvian IGV rate (18%).
 *
 * ⚠️  This is a FALLBACK/DEFAULT only — not the authoritative rate.
 * Use `useTenantIgvRate()` to obtain the live tenant-configured rate.
 */
export const IGV_RATE = 0.18;

const MONEY_SCALE = 2;

interface DecimalValue {
  coefficient: bigint;
  scale: number;
}

export type MoneyInput = string | number;

/** Normalize an API decimal string or legacy JS number once; arithmetic stays integer-only. */
const decimalValue = (value: MoneyInput): DecimalValue => {
  if (typeof value === 'number' && !Number.isFinite(value)) {
    return { coefficient: 0n, scale: 0 };
  }

  const raw = typeof value === 'number' ? value.toFixed(12) : value.trim().replace(',', '.');
  if (!/^[+-]?\d+(?:\.\d+)?$/.test(raw)) return { coefficient: 0n, scale: 0 };
  const negative = raw.startsWith('-');
  const fixed = raw.replace(/^[+-]/, '').replace(/0+$/, '').replace(/\.$/, '');
  const [integer = '0', fraction = ''] = fixed.split('.');
  const coefficient = BigInt(`${integer}${fraction}` || '0');

  return { coefficient: negative ? -coefficient : coefficient, scale: fraction.length };
};

const powerOfTen = (scale: number): bigint => 10n ** BigInt(scale);

/** Integer division rounded half away from zero (HALF_UP for positive money). */
const divideHalfUp = (numerator: bigint, denominator: bigint): bigint => {
  if (denominator === 0n) return 0n;
  const negative = numerator < 0n !== denominator < 0n;
  const absoluteNumerator = numerator < 0n ? -numerator : numerator;
  const absoluteDenominator = denominator < 0n ? -denominator : denominator;
  const rounded = (absoluteNumerator + absoluteDenominator / 2n) / absoluteDenominator;
  return negative ? -rounded : rounded;
};

const decimalToMinor = (value: MoneyInput): bigint => {
  const decimal = decimalValue(value);
  return divideHalfUp(decimal.coefficient * powerOfTen(MONEY_SCALE), powerOfTen(decimal.scale));
};

const multiplyToMinor = (left: MoneyInput, right: MoneyInput): bigint => {
  const a = decimalValue(left);
  const b = decimalValue(right);
  return divideHalfUp(
    a.coefficient * b.coefficient * powerOfTen(MONEY_SCALE),
    powerOfTen(a.scale + b.scale),
  );
};

const percentageOfMinor = (amount: bigint, percent: number): bigint => {
  const rate = decimalValue(percent);
  return divideHalfUp(amount * rate.coefficient, 100n * powerOfTen(rate.scale));
};

const minorToNumber = (value: bigint): number => Number(formatMinorUnits(value, MONEY_SCALE));

/** Round a number to 2 decimal places using exact minor-unit HALF_UP rounding. */
export const round2 = (value: number): number => minorToNumber(decimalToMinor(value));

/**
 * Returns true if the given SUNAT tax type is subject to IGV.
 * Missing / undefined taxType defaults to gravado ('10').
 */
export const isGravado = (taxType: SunatTaxType | undefined): boolean =>
  !taxType || taxType === '10';

/**
 * Returns true if the given SUNAT tax type is exonerado (exempt).
 */
export const isExonerado = (taxType: SunatTaxType | undefined): boolean => taxType === '20';

/**
 * Compute the gross amount, discount amount, and net amount for a single sale line.
 *
 * @param quantity        Number of units.
 * @param unitPrice       Tax-inclusive unit price.
 * @param discountPercent Line-level discount percentage in [0, 100].
 */
export const computeLineAmount = (
  quantity: number,
  unitPrice: MoneyInput,
  discountPercent: number,
): { grossAmount: number; discountAmount: number; netAmount: number } => {
  const grossMinor = multiplyToMinor(quantity, unitPrice);
  const discountMinor = percentageOfMinor(grossMinor, discountPercent);
  const netMinor = grossMinor - discountMinor;
  return {
    grossAmount: minorToNumber(grossMinor),
    discountAmount: minorToNumber(discountMinor),
    netAmount: minorToNumber(netMinor),
  };
};

/**
 * Compute sale totals from a list of lines and a header-level discount.
 *
 * The header discount is allocated proportionally across gravado, exonerado,
 * and inafecto line groups — the standard approach accepted by SUNAT.
 *
 * @param lines    Array of lines; each must have `netAmount` and optionally `taxType`.
 *                 Lines without `taxType` are treated as gravado ('10').
 * @param discount Header-level discount; `percent` is in [0, 100].
 * @param taxRate  IGV rate as a decimal fraction (e.g. 0.18 or 0.105).
 *                 Defaults to `IGV_RATE` — prefer passing the tenant's configured rate.
 */
export const computeTotals = (
  lines: { netAmount: number; taxType?: SunatTaxType }[],
  discount: Pick<Discount, 'percent'>,
  taxRate: number = IGV_RATE,
): SaleTotals => {
  const subtotalMinor = lines.reduce((sum, line) => sum + decimalToMinor(line.netAmount), 0n);
  const discountMinor = percentageOfMinor(subtotalMinor, discount.percent);
  const afterDiscountMinor = subtotalMinor - discountMinor;

  // Split the subtotal by tax group before proportional discount allocation.
  const gravadoSubtotalMinor = lines
    .filter((line) => isGravado(line.taxType))
    .reduce((sum, line) => sum + decimalToMinor(line.netAmount), 0n);
  const exoneradoSubtotalMinor = lines
    .filter((line) => isExonerado(line.taxType))
    .reduce((sum, line) => sum + decimalToMinor(line.netAmount), 0n);
  const inafectoSubtotalMinor = subtotalMinor - gravadoSubtotalMinor - exoneradoSubtotalMinor;
  // Proportional allocation of header discount across each group.
  const allocate = (groupSubtotal: bigint): bigint =>
    subtotalMinor > 0n ? divideHalfUp(afterDiscountMinor * groupSubtotal, subtotalMinor) : 0n;

  let gravadoBaseMinor = allocate(gravadoSubtotalMinor);
  let exoneradoBaseMinor = allocate(exoneradoSubtotalMinor);
  let inafectoBaseMinor = allocate(inafectoSubtotalMinor);

  // Assign the rounding remainder to the last non-empty tax group. This keeps
  // the group bases equal to the sale total without inventing a -0.01 base for
  // a group that had no lines.
  if (inafectoSubtotalMinor > 0n) {
    inafectoBaseMinor = afterDiscountMinor - gravadoBaseMinor - exoneradoBaseMinor;
  } else if (exoneradoSubtotalMinor > 0n) {
    exoneradoBaseMinor = afterDiscountMinor - gravadoBaseMinor;
    inafectoBaseMinor = 0n;
  } else {
    gravadoBaseMinor = afterDiscountMinor;
    exoneradoBaseMinor = 0n;
    inafectoBaseMinor = 0n;
  }

  const rate = decimalValue(taxRate);
  const igvMinor = divideHalfUp(
    gravadoBaseMinor * rate.coefficient,
    powerOfTen(rate.scale) + rate.coefficient,
  );

  const subtotal = minorToNumber(subtotalMinor);
  const discountAmount = minorToNumber(discountMinor);
  const gravadoBase = minorToNumber(gravadoBaseMinor);
  const exoneradoBase = minorToNumber(exoneradoBaseMinor);
  const inafectoBase = minorToNumber(inafectoBaseMinor);
  const igv = minorToNumber(igvMinor);

  return {
    subtotal,
    discount: { percent: discount.percent, amount: discountAmount },
    gravadoBase,
    exoneradoBase,
    inafectoBase,
    taxableBase: gravadoBase, // backward-compat alias
    igv,
    total: minorToNumber(afterDiscountMinor),
  };
};

/**
 * Compute totals from raw cart-style data (no pre-computed netAmount).
 * Convenience wrapper used by the POS checkout.
 *
 * @param items           Array of cart items; each has `price`, `quantity`, and
 *                        optional `taxType`. Missing `taxType` defaults to `'10'`.
 * @param discountPercent Header-level discount in [0, 100].
 * @param taxRate         IGV rate as a decimal fraction (e.g. 0.18 or 0.105).
 *                        Defaults to `IGV_RATE` — prefer passing the tenant's rate.
 */
export const computeCartTotals = (
  items: { price: MoneyInput; quantity: number; taxType?: SunatTaxType }[],
  discountPercent: number,
  taxRate: number = IGV_RATE,
): {
  subtotal: number;
  discountAmount: number;
  total: number;
  includedTax: number;
  gravadoBase: number;
  exoneradoBase: number;
  inafectoBase: number;
} => {
  // Reuse computeTotals by converting each item to a minimal SaleLine-like object.
  const lines = items.map((item) => ({
    netAmount: minorToNumber(multiplyToMinor(item.price, item.quantity)),
    taxType: item.taxType,
  }));

  const totals = computeTotals(lines, { percent: discountPercent }, taxRate);

  return {
    subtotal: totals.subtotal,
    discountAmount: totals.discount.amount,
    total: totals.total,
    includedTax: totals.igv,
    gravadoBase: totals.gravadoBase,
    exoneradoBase: totals.exoneradoBase,
    inafectoBase: totals.inafectoBase,
  };
};
