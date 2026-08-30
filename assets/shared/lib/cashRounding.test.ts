import { describe, expect, it } from 'vitest';
import { cashRoundingAdjustment, roundCashDownToTenth } from './cashRounding';

describe('Peru cash rounding', () => {
  it('always rounds cash down to S/ 0.10 in the customer favour', () => {
    expect(roundCashDownToTenth(10.63)).toBe(10.6);
    expect(roundCashDownToTenth(10.68)).toBe(10.6);
    expect(cashRoundingAdjustment(10.68)).toBe(0.08);
  });
});
