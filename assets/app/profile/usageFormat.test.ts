import { describe, expect, it } from 'vitest';
import { describeUsage, planLabel, usageRows } from './usageFormat';

describe('describeUsage', () => {
  it('reads "current of limit" with its fill and flags the last 20%', () => {
    expect(describeUsage('users', { limit: 10, current: 4 })).toMatchObject({
      label: 'Usuarios',
      text: '4 de 10',
      ratio: 0.4,
      near: false,
    });
    expect(describeUsage('users', { limit: 10, current: 8 }).near).toBe(true);
  });

  it('never fills past the limit', () => {
    expect(describeUsage('products', { limit: 50, current: 60 }).ratio).toBe(1);
  });

  it('has nothing to fill when the plan sets no limit', () => {
    expect(describeUsage('customers', { limit: -1, current: 117 })).toMatchObject({
      text: '117 · sin límite',
      ratio: null,
      near: false,
    });
  });

  it('adds the unit where there is one', () => {
    expect(describeUsage('storage_mb', { limit: 10000, current: 11 }).text).toBe(
      '11 MB de 10000 MB',
    );
  });

  it('keeps an unknown resource visible under its key', () => {
    expect(describeUsage('seats', { limit: 5, current: 1 }).label).toBe('seats');
  });
});

describe('usageRows', () => {
  it('lists known resources in a stable order, then the rest', () => {
    const rows = usageRows({
      storage_mb: { limit: 100, current: 1 },
      seats: { limit: 3, current: 1 },
      users: { limit: 3, current: 1 },
      branches: { limit: 1, current: 1 },
    });
    expect(rows.map((row) => row.key)).toEqual(['users', 'branches', 'storage_mb', 'seats']);
  });
});

describe('planLabel', () => {
  it('names the known plans and falls back to the key', () => {
    expect(planLabel('pro')).toBe('Pro');
    expect(planLabel('enterprise')).toBe('enterprise');
  });
});
