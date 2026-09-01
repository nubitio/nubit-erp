import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import {
  axisProps,
  BAR_RADIUS_TOP,
  CHART_ACCENT,
  CHART_ANIM_MS,
  CHART_MARGIN,
  compactCurrency,
  gridProps,
} from './chartTheme';
import { MoneyTooltip } from './MoneyTooltip';

type SalesByDayChartProps = {
  data: Array<{ label: string; total: number }>;
  height?: number;
};

/** Last-7-days net sales — one bar per day, single accent hue. */
export function SalesByDayChart({ data, height = 220 }: SalesByDayChartProps) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} margin={CHART_MARGIN}>
        <CartesianGrid {...gridProps} />
        <XAxis dataKey="label" {...axisProps} />
        <YAxis {...axisProps} width={64} tickFormatter={compactCurrency} />
        <Tooltip cursor={{ fill: 'var(--surface-2)' }} content={<MoneyTooltip />} />
        <Bar
          dataKey="total"
          fill={CHART_ACCENT}
          radius={BAR_RADIUS_TOP}
          maxBarSize={48}
          animationDuration={CHART_ANIM_MS}
        />
      </BarChart>
    </ResponsiveContainer>
  );
}
