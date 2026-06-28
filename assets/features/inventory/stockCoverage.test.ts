import { describe, expect, it } from 'vitest';
import { stockCoverageColor, stockCoveragePercent } from './stockCoverage';

describe('stockCoveragePercent', () => {
  it('returns 0 for negative stock regardless of minimum', () => {
    expect(stockCoveragePercent(-1, null)).toBe(0);
    expect(stockCoveragePercent('-3', '10')).toBe(0);
  });

  it('returns 0 for zero stock', () => {
    expect(stockCoveragePercent(0, null)).toBe(0);
    expect(stockCoveragePercent('0', '5')).toBe(0);
  });

  it('returns 100 for positive stock without a minimum', () => {
    expect(stockCoveragePercent(10, null)).toBe(100);
    expect(stockCoveragePercent('98.02', undefined)).toBe(100);
    expect(stockCoveragePercent(5, 0)).toBe(100);
  });

  it('calculates coverage against a positive minimum', () => {
    expect(stockCoveragePercent(5, 10)).toBe(50);
    expect(stockCoveragePercent(15, 10)).toBe(100);
    expect(stockCoveragePercent(98.02, 100)).toBe(98);
  });
});

describe('stockCoverageColor', () => {
  it('uses red for zero or negative coverage', () => {
    expect(stockCoverageColor(0)).toBe('#ef4444');
    expect(stockCoverageColor(-5)).toBe('#ef4444');
  });
});