import { describe, expect, it } from 'vitest';
import { receiptLinks, saleDate } from './saleDetailModel';

describe('sale detail dates and document links', () => {
  it('keeps the business date when API Platform serializes it at UTC midnight', () => {
    expect(saleDate('2026-10-29T00:00:00+00:00')).toBe(saleDate('2026-10-29'));
    expect(saleDate(null)).toBe('Sin fecha registrada');
  });
  it('uses Lima time for timestamped activity', () => {
    const value = saleDate('2026-10-30T02:00:00Z', true);
    expect(value).toContain('29/10/26');
    expect(value).not.toContain('30/10/26');
  });
  it('derives downloads only from an existing local public receipt route', () => {
    expect(receiptLinks('/receipt/payload')).toEqual({
      xml: '/receipt/payload/xml',
      cdr: '/receipt/payload/cdr',
    });
    expect(receiptLinks(null)).toBeNull();
    expect(receiptLinks('https://example.com/receipt/payload')).toBeNull();
  });
});
