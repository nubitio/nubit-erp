import {
  BarChart,
  Bar,
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import { StatCard } from '@nubit/ui';
import { SalesByMonth, SalesByDay } from '../types';
import { currencyFormatter } from '@shared/ui/dashboard/formatters';

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="dash-section-header">
      <span className="dash-section-header__title">{title}</span>
      {subtitle && <span className="dash-section-header__sub">{subtitle}</span>}
    </div>
  );
}

type Props = {
  salesByMonth: SalesByMonth[];
  salesByDay: SalesByDay[];
};

function CurrencyTooltip({ active, payload }: { active?: boolean; payload?: { value: number }[] }) {
  if (!active || !payload?.length) return null;
  return <div className="dash-chart-tooltip">{currencyFormatter(payload[0].value)}</div>;
}

export function DashboardCharts({ salesByMonth, salesByDay }: Props) {
  return (
    <>
      <SectionHeader title="Evolución de ventas" />
      <div className="dash-charts-2col">
        <StatCard title="Evolución mensual (12 meses)" className="chart-card" menuVisible={false}>
          <ResponsiveContainer width="100%" height={190}>
            <BarChart data={salesByMonth} margin={{ top: 4, right: 8, left: 8, bottom: 4 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border-color, rgba(0,0,0,0.08))" />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
              <YAxis
                tickFormatter={(v: number) => currencyFormatter(v)}
                tick={{ fontSize: 11 }}
                tickLine={false}
                axisLine={false}
                width={80}
              />
              <Tooltip content={<CurrencyTooltip />} />
              <Bar dataKey="total" fill="var(--accent-color, #4f46e5)" radius={[3, 3, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </StatCard>

        <StatCard title="Ventas diarias (mes actual)" className="chart-card" menuVisible={false}>
          <ResponsiveContainer width="100%" height={190}>
            <AreaChart data={salesByDay} margin={{ top: 4, right: 8, left: 8, bottom: 4 }}>
              <defs>
                <linearGradient id="salesByDayGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="5%" stopColor="var(--accent-color, #4f46e5)" stopOpacity={0.25} />
                  <stop offset="95%" stopColor="var(--accent-color, #4f46e5)" stopOpacity={0} />
                </linearGradient>
              </defs>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--border-color, rgba(0,0,0,0.08))" />
              <XAxis dataKey="day" tick={{ fontSize: 11 }} tickLine={false} axisLine={false} />
              <YAxis
                tickFormatter={(v: number) => currencyFormatter(v)}
                tick={{ fontSize: 11 }}
                tickLine={false}
                axisLine={false}
                width={80}
              />
              <Tooltip content={<CurrencyTooltip />} />
              <Area
                type="monotone"
                dataKey="total"
                stroke="var(--accent-color, #4f46e5)"
                strokeWidth={2}
                fill="url(#salesByDayGradient)"
              />
            </AreaChart>
          </ResponsiveContainer>
        </StatCard>
      </div>
    </>
  );
}
