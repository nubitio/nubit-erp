import { describe, expect, it } from 'vitest';
import { saleFilterPresets } from './saleFilterPresets';

describe('saleFilterPresets', () => {
  it('uses the Lima calendar when UTC has already entered the next month', () => {
    const presets = saleFilterPresets(new Date('2026-11-01T02:00:00Z'));
    expect(presets.find((item) => item.key === 'today')?.value).toBe('2026-10-31');
    expect(presets.find((item) => item.key === 'month')?.value).toBe('2026-10-01|2026-10-31');
  });
  it('includes the last day of February in a leap year', () => {
    expect(
      saleFilterPresets(new Date('2028-02-15T12:00:00Z')).find((item) => item.key === 'month')
        ?.value,
    ).toBe('2028-02-01|2028-02-29');
  });
});
