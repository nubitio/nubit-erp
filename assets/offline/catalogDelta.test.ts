import { describe, expect, it } from 'vitest';
import { FULL_REFRESH_AFTER_MS, mergeCatalogChanges, planCatalogRefresh } from './catalogDelta';

const NOW = 1_000_000_000_000;

describe('planCatalogRefresh', () => {
  it('downloads everything when there is no cursor yet', () => {
    expect(planCatalogRefresh({ cursor: null, lastFullAt: NOW - 1000 }, NOW)).toBe('full');
  });

  it('downloads everything when it never did so in full', () => {
    expect(planCatalogRefresh({ cursor: 'c', lastFullAt: null }, NOW)).toBe('full');
  });

  it('asks for the delta shortly after a sync', () => {
    expect(planCatalogRefresh({ cursor: 'c', lastFullAt: NOW - 60_000 }, NOW)).toBe('delta');
  });

  it('reconciles in full once the last full download is old enough', () => {
    expect(planCatalogRefresh({ cursor: 'c', lastFullAt: NOW - FULL_REFRESH_AFTER_MS }, NOW)).toBe(
      'full',
    );
    expect(
      planCatalogRefresh({ cursor: 'c', lastFullAt: NOW - FULL_REFRESH_AFTER_MS + 1 }, NOW),
    ).toBe('delta');
  });

  it('does not trust a device clock that went backwards', () => {
    expect(planCatalogRefresh({ cursor: 'c', lastFullAt: NOW + 5000 }, NOW)).toBe('full');
  });
});

describe('mergeCatalogChanges', () => {
  const item = (id: string, salePrice: string) => ({ id, salePrice });

  it('replaces a changed product and keeps the order of the rest', () => {
    const merged = mergeCatalogChanges(
      [item('a', '1.00'), item('b', '2.00'), item('c', '3.00')],
      [item('b', '2.50')],
    );
    expect(merged).toEqual([item('a', '1.00'), item('b', '2.50'), item('c', '3.00')]);
  });

  it('appends a product it has not seen', () => {
    expect(mergeCatalogChanges([item('a', '1.00')], [item('z', '9.00')])).toEqual([
      item('a', '1.00'),
      item('z', '9.00'),
    ]);
  });

  it('is idempotent: the server repeats rows from its overlap window', () => {
    const once = mergeCatalogChanges([item('a', '1.00')], [item('a', '1.10'), item('n', '5.00')]);
    const twice = mergeCatalogChanges(once, [item('a', '1.10'), item('n', '5.00')]);
    expect(twice).toEqual(once);
  });

  it('returns the same list when nothing changed', () => {
    const current = [item('a', '1.00')];
    expect(mergeCatalogChanges(current, [])).toBe(current);
  });
});
