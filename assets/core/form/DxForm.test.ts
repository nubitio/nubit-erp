import { describe, expect, it } from 'vitest';
import { syncComputedValuesIntoFormData } from './DxForm';

describe('syncComputedValuesIntoFormData', () => {
  it('applies computed values into form data', () => {
    const result = syncComputedValuesIntoFormData({ subtotal: 100, total: 100 }, ['total'], {
      total: 118,
    });

    expect(result.changed).toBe(true);
    expect(result.nextFormData).toEqual({ subtotal: 100, total: 118 });
  });

  it('clears stale computed values when a computed field is no longer provided', () => {
    const result = syncComputedValuesIntoFormData({ subtotal: 100, total: 118 }, ['total'], {});

    expect(result.changed).toBe(true);
    expect(result.nextFormData).toEqual({ subtotal: 100, total: undefined });
  });

  it('does not change unrelated fields or unchanged computed values', () => {
    const result = syncComputedValuesIntoFormData(
      { subtotal: 100, total: 118, notes: 'ok' },
      ['total'],
      { total: 118 },
    );

    expect(result.changed).toBe(false);
    expect(result.nextFormData).toEqual({ subtotal: 100, total: 118, notes: 'ok' });
  });
});
