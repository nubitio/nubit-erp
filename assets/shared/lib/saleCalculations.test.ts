import { describe, expect, it } from 'vitest';

import {
  computeCartTotals,
  computeLineAmount,
  computeTotals,
  round2,
} from './saleCalculations';

describe('saleCalculations exact money', () => {
  it('rounds monetary halves using HALF_UP semantics', () => {
    expect(round2(1.005)).toBe(1.01);
    expect(round2(2.675)).toBe(2.68);
  });

  it('multiplies decimal quantities and prices without floating-point drift', () => {
    expect(computeLineAmount(0.1, 0.2, 0)).toEqual({
      grossAmount: 0.02,
      discountAmount: 0,
      netAmount: 0.02,
    });
  });

  it('computes tax included in a PEN 118.00 gravado total exactly', () => {
    const totals = computeTotals([{ netAmount: 118, taxType: '10' }], { percent: 0 }, 0.18);

    expect(totals.gravadoBase).toBe(118);
    expect(totals.igv).toBe(18);
    expect(totals.total).toBe(118);
  });

  it('allocates discounts across tax groups without losing a cent', () => {
    const totals = computeTotals(
      [
        { netAmount: 10.01, taxType: '10' },
        { netAmount: 10.02, taxType: '20' },
        { netAmount: 10.03, taxType: '30' },
      ],
      { percent: 7.5 },
      0.18,
    );

    expect(totals.total).toBe(27.81);
    expect(
      round2(totals.gravadoBase + totals.exoneradoBase + totals.inafectoBase),
    ).toBe(totals.total);
  });

  it('does not create a negative inafecto base from allocation rounding', () => {
    const totals = computeTotals(
      [
        { netAmount: 0.01, taxType: '10' },
        { netAmount: 0.01, taxType: '20' },
      ],
      { percent: 25 },
      0.18,
    );

    expect(totals.inafectoBase).toBe(0);
    expect(round2(totals.gravadoBase + totals.exoneradoBase)).toBe(totals.total);
  });

  it('keeps cart subtotal, discount and total internally consistent', () => {
    const totals = computeCartTotals(
      [
        { price: 0.1, quantity: 3, taxType: '10' },
        { price: 0.2, quantity: 3, taxType: '20' },
      ],
      10,
      0.18,
    );

    expect(totals.subtotal).toBe(0.9);
    expect(totals.discountAmount).toBe(0.09);
    expect(totals.total).toBe(0.81);
    expect(round2(totals.gravadoBase + totals.exoneradoBase + totals.inafectoBase)).toBe(0.81);
  });
});
