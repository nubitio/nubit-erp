import { describe, expect, it } from 'vitest';
import {
  describeDifference,
  formatEventTime,
  formatPercent,
  rangeToDates,
} from './teamControlFormat';

describe('rangeToDates', () => {
  const today = new Date(2026, 10, 22); // 22 Nov 2026

  it('counts the last 7 days including today', () => {
    expect(rangeToDates('7d', today)).toEqual({ from: '2026-11-16', to: '2026-11-22' });
  });

  it('counts the last 30 days including today', () => {
    expect(rangeToDates('30d', today)).toEqual({ from: '2026-10-24', to: '2026-11-22' });
  });

  it('starts the month on the 1st', () => {
    expect(rangeToDates('month', today)).toEqual({ from: '2026-11-01', to: '2026-11-22' });
  });

  it('crosses a year boundary', () => {
    expect(rangeToDates('7d', new Date(2027, 0, 3))).toEqual({
      from: '2026-12-28',
      to: '2027-01-03',
    });
  });
});

describe('formatting', () => {
  it('formats a rate the way the signals read it', () => {
    expect(formatPercent(0.075)).toBe('7.5%');
    expect(formatPercent(0)).toBe('0.0%');
  });

  it('shortens a server timestamp and leaves anything else alone', () => {
    expect(formatEventTime('2026-11-22 14:05:33')).toBe('22/11 14:05');
    expect(formatEventTime('sin fecha')).toBe('sin fecha');
  });

  it('names a closing difference by its sign', () => {
    expect(describeDifference(-12.5)).toBe('faltante');
    expect(describeDifference(3)).toBe('sobrante');
    expect(describeDifference(0)).toBe('exacto');
  });
});
