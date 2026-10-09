import { useMemo } from 'react';
import {
  Bar,
  BarChart,
  Cell,
  LabelList,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import { currencyFormatter } from '@shared/ui/dashboard/formatters';

import { BAR_RADIUS_RIGHT, CHART_ACCENT, CHART_ANIM_MS } from './chartTheme';
import { MoneyTooltip } from './MoneyTooltip';

type PaymentMethodBarsProps = {
  data: Array<{ method: string; total: number }>;
};

const ROW_HEIGHT = 34;
const MIN_HEIGHT = 150;

/** Payment-method share — horizontal bars sorted by amount, value + % at the end. */
export function PaymentMethodBars({ data }: PaymentMethodBarsProps) {
  const rows = useMemo(() => {
    const sorted = [...data].sort((a, b) => b.total - a.total);
    const total = sorted.reduce((sum, row) => sum + row.total, 0);
    return sorted.map((row) => ({
      ...row,
      label: `${currencyFormatter(row.total)} · ${total > 0 ? Math.round((row.total / total) * 100) : 0}%`,
    }));
  }, [data]);

  const height = Math.max(MIN_HEIGHT, rows.length * ROW_HEIGHT + 16);

  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart
        layout="vertical"
        data={rows}
        margin={{ top: 4, right: 128, left: 8, bottom: 4 }}
        barCategoryGap={8}
      >
        <XAxis type="number" hide />
        <YAxis
          type="category"
          dataKey="method"
          width={112}
          tickLine={false}
          axisLine={false}
          tick={{ fontSize: 12, fill: 'var(--text-secondary)' }}
        />
        <Tooltip
          cursor={{ fill: 'var(--surface-2)' }}
          content={<MoneyTooltip labelKey="method" />}
        />
        <Bar
          dataKey="total"
          radius={BAR_RADIUS_RIGHT}
          maxBarSize={22}
          animationDuration={CHART_ANIM_MS}
        >
          {rows.map((row) => (
            <Cell key={row.method} fill={CHART_ACCENT} />
          ))}
          <LabelList
            dataKey="label"
            position="right"
            offset={8}
            fill="var(--text-secondary)"
            fontSize={11}
          />
        </Bar>
      </BarChart>
    </ResponsiveContainer>
  );
}
