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

/**
 * The standard Peruvian IGV rate (18%).
 *
 * ⚠️  This is a FALLBACK/DEFAULT only — not the authoritative rate.
 * Use `useTenantIgvRate()` to obtain the live tenant-configured rate.
 */
export const IGV_RATE = 0.18;

/** Round a number to 2 decimal places (standard half-up rounding). */
export const round2 = (value: number): number => Math.round(value * 100) / 100;

/**
 * Returns true if the given SUNAT tax type is subject to IGV.
 * Missing / undefined taxType defaults to gravado ('10').
 */
export const isGravado = (taxType: SunatTaxType | undefined): boolean =>
  !taxType || taxType === '10';

/**
 * Returns true if the given SUNAT tax type is exonerado (exempt).
 */
export const isExonerado = (taxType: SunatTaxType | undefined): boolean =>
  taxType === '20';

/**
 * Compute the gross amount, discount amount, and net amount for a single sale line.
 *
 * @param quantity        Number of units.
 * @param unitPrice       Tax-inclusive unit price.
 * @param discountPercent Line-level discount percentage in [0, 100].
 */
export const computeLineAmount = (
  quantity: number,
  unitPrice: number,
  discountPercent: number,
): { grossAmount: number; discountAmount: number; netAmount: number } => {
  const grossAmount = round2(quantity * unitPrice);
  const discountAmount = round2(grossAmount * (discountPercent / 100));
  const netAmount = round2(grossAmount - discountAmount);
  return { grossAmount, discountAmount, netAmount };
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
  const subtotal = round2(lines.reduce((sum, l) => sum + l.netAmount, 0));
  const discountAmount = round2(subtotal * (discount.percent / 100));
  const afterDiscount = round2(subtotal - discountAmount);

  // Split the subtotal by tax group before proportional discount allocation.
  const gravadoSubtotal = round2(
    lines.filter((l) => isGravado(l.taxType)).reduce((sum, l) => sum + l.netAmount, 0),
  );
  const exoneradoSubtotal = round2(
    lines.filter((l) => isExonerado(l.taxType)).reduce((sum, l) => sum + l.netAmount, 0),
  );
  // Proportional allocation of header discount across each group.
  const allocate = (groupSubtotal: number): number =>
    subtotal > 0 ? round2(afterDiscount * (groupSubtotal / subtotal)) : 0;

  const gravadoBase = allocate(gravadoSubtotal);
  const exoneradoBase = allocate(exoneradoSubtotal);
  // Compute inafectoBase as the remainder to eliminate accumulated rounding error.
  const inafectoBase = round2(afterDiscount - gravadoBase - exoneradoBase);

  const igv = round2(gravadoBase * (taxRate / (1 + taxRate)));

  return {
    subtotal,
    discount: { percent: discount.percent, amount: discountAmount },
    gravadoBase,
    exoneradoBase,
    inafectoBase,
    taxableBase: gravadoBase, // backward-compat alias
    igv,
    total: afterDiscount,
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
  items: { price: number; quantity: number; taxType?: SunatTaxType }[],
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
    netAmount: round2(item.price * item.quantity),
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
