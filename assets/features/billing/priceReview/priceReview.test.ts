import { describe, expect, it } from 'vitest';
import { countBySeller, formatPercent, totalImpact, type PriceReviewLine } from './priceReview';

const line = (overrides: Partial<PriceReviewLine>): PriceReviewLine => ({
  lineId: 1,
  saleId: 's1',
  document: 'N001-1',
  issuedAt: null,
  seller: 'marta',
  product: 'POLLO ENTERO X KG',
  quantity: '1.000',
  chargedPrice: '9.80',
  expectedPrice: '10.10',
  difference: '-0.30',
  differencePercent: '-3.0',
  impact: '-0.30',
  ...overrides,
});

describe('totalImpact', () => {
  it('adds gains and losses without floating-point drift', () => {
    expect(totalImpact([line({ impact: '0.10' }), line({ impact: '0.20' })])).toBe(0.3);
    expect(totalImpact([line({ impact: '-7.65' }), line({ impact: '0.60' })])).toBe(-7.05);
  });

  it('is zero with no lines', () => {
    expect(totalImpact([])).toBe(0);
  });
});

describe('formatPercent', () => {
  it('shows the sign of a gain and the minus of a loss', () => {
    expect(formatPercent('2.96')).toBe('+3.0 %');
    expect(formatPercent('-3.0')).toBe('-3.0 %');
  });

  it('shows a dash when there is nothing to compare', () => {
    expect(formatPercent(null)).toBe('—');
    expect(formatPercent('abc')).toBe('—');
  });
});

describe('countBySeller', () => {
  it('counts per seller, most first then alphabetical, and names a missing seller', () => {
    const counts = countBySeller([
      line({ seller: 'ana' }),
      line({ seller: 'marta' }),
      line({ seller: 'marta' }),
      line({ seller: null }),
    ]);
    expect(counts).toEqual([
      { seller: 'marta', count: 2 },
      { seller: 'ana', count: 1 },
      { seller: 'Sin vendedor', count: 1 },
    ]);
  });
});
