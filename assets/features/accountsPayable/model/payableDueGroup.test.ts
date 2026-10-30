import { expect, test } from 'vitest';
import { payableDueGroup } from './payableDueGroup';

test('calendar groups respect week, month and year boundaries using the report date', () => {
  const today = '2026-10-29'; // Thursday; this week ends November 1.
  expect(payableDueGroup('2026-10-28', today).key).toBe('overdue');
  expect(payableDueGroup('2026-10-29T00:00:00+00:00', today).key).toBe('this-week');
  expect(payableDueGroup('2026-11-01', today).key).toBe('this-week');
  expect(payableDueGroup('2026-11-02', today).key).toBe('next-week');
  expect(payableDueGroup('2026-11-08', today).key).toBe('next-week');
  expect(payableDueGroup('2026-11-09', today).key).toBe('2026-11');
  expect(payableDueGroup('2026-11-09', today).label.toLowerCase()).toBe('noviembre');
  expect(payableDueGroup('2027-01-01', today).label.toLowerCase()).toBe('enero 2027');
  expect(payableDueGroup(null, today).key).toBe('unknown');
  expect(payableDueGroup('invalid', today).key).toBe('unknown');
});
