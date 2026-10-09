import { describe, expect, it } from 'vitest';
import { resolveStockUnitLabel } from './inventoryUnitLabel';

describe('resolveStockUnitLabel', () => {
  it('prefers explicit unit code from stock item', () => {
    expect(resolveStockUnitLabel({ unitName: 'Kilogramo', unitCode: 'KGM' })).toBe('kilos');
  });

  it('reads nested product unit of measure', () => {
    expect(
      resolveStockUnitLabel({
        product: { unitOfMeasure: { name: 'Kilogramo', code: 'KGM' } },
      }),
    ).toBe('kilos');
  });

  it('falls back to unidades when unit is unknown', () => {
    expect(resolveStockUnitLabel({})).toBe('unidades');
  });
});
