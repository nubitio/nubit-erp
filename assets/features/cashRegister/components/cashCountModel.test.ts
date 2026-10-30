import { describe, expect, it } from 'vitest';
import { cashMinor, denominationTotal, parseCashCount } from './cashCountModel';
describe('cash count', () => {
  it('counts notes and small coins exactly, distinguishing pending from explicit zero', () => {
    expect(denominationTotal({})).toBeUndefined();
    expect(denominationTotal({ '10': '0' })).toBe('0.00');
    expect(denominationTotal({ '20000': '2', '10': '3', '20': '1' })).toBe('400.50');
    expect(cashMinor('400.50') - cashMinor('400.40')).toBe(10n);
  });
  it('rejects fractional and negative quantities and preserves entered monetary decimals', () => {
    expect(denominationTotal({ '100': '2.5' })).toBeUndefined();
    expect(denominationTotal({ '100': '-1' })).toBeUndefined();
    expect(parseCashCount('9999999999.99')).toBe('9999999999.99');
    for (const value of ['-1', '1.001', '', 'NaN', '10000000000'])
      expect(parseCashCount(value)).toBeUndefined();
  });
});
