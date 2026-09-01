/**
 * Shared Recharts theme for the eFact dashboard.
 *
 * Colours are CSS custom properties, not hex — so light/dark themes and the
 * tenant accent flow through without re-rendering the charts.
 */

export const CHART_ACCENT = 'var(--accent-color)';
export const CHART_GRID = 'var(--border-subtle)';
export const CHART_TICK_FILL = 'var(--text-tertiary)';

/** Recharts <CartesianGrid> props — recessive horizontal lines only. */
export const gridProps = {
  stroke: CHART_GRID,
  strokeDasharray: '3 3',
  vertical: false,
} as const;

/** Recharts <XAxis>/<YAxis> shared props — no axis line, no tick line, muted labels. */
export const axisProps = {
  tickLine: false,
  axisLine: false,
  tick: { fontSize: 11, fill: CHART_TICK_FILL },
} as const;

export const CHART_MARGIN = { top: 8, right: 12, left: 8, bottom: 4 } as const;

/** Rounded data-ends: top for vertical bars, right for horizontal bars. */
export const BAR_RADIUS_TOP: [number, number, number, number] = [4, 4, 0, 0];
export const BAR_RADIUS_RIGHT: [number, number, number, number] = [0, 4, 4, 0];

export const CHART_ANIM_MS = 450;

const compactCurrencyIntl = new Intl.NumberFormat('es-PE', {
  notation: 'compact',
  style: 'currency',
  currency: 'PEN',
  maximumFractionDigits: 1,
});

/** Short money for axis ticks: `S/ 1.2 mil`, `S/ 3 M`. */
export const compactCurrency = (value: number): string => compactCurrencyIntl.format(value);
