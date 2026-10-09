import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import {
  axisProps,
  CHART_ACCENT,
  CHART_ANIM_MS,
  CHART_MARGIN,
  compactCurrency,
  gridProps,
} from './chartTheme';
import { MoneyTooltip } from './MoneyTooltip';

type SalesTrendChartProps = {
  data: Array<{ label: string; total: number }>;
  height?: number;
};

const GRADIENT_ID = 'efact-sales-trend-fill';

/** Last-12-months net sales trend — area with a top-down accent fade. */
export function SalesTrendChart({ data, height = 240 }: SalesTrendChartProps) {
  return (
    <ResponsiveContainer width="100%" height={height}>
      <AreaChart data={data} margin={CHART_MARGIN}>
        <defs>
          <linearGradient id={GRADIENT_ID} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={CHART_ACCENT} stopOpacity={0.28} />
            <stop offset="100%" stopColor={CHART_ACCENT} stopOpacity={0} />
          </linearGradient>
        </defs>
        <CartesianGrid {...gridProps} />
        <XAxis dataKey="label" {...axisProps} minTickGap={16} />
        <YAxis {...axisProps} width={64} tickFormatter={compactCurrency} />
        <Tooltip cursor={{ stroke: 'var(--border-color)' }} content={<MoneyTooltip />} />
        <Area
          type="monotone"
          dataKey="total"
          stroke={CHART_ACCENT}
          strokeWidth={2}
          fill={`url(#${GRADIENT_ID})`}
          activeDot={{ r: 4, strokeWidth: 0 }}
          animationDuration={CHART_ANIM_MS}
        />
      </AreaChart>
    </ResponsiveContainer>
  );
}
