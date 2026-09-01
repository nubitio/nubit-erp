import { formatMoney } from '@shared/lib/displayFormat';
import { percentFormatter } from '@shared/ui/dashboard/formatters';
import type { EfactDeclarativeDashboardPayload } from '../mapEfactDashboardPayload';

type Stats = EfactDeclarativeDashboardPayload['stats'];

type Tone = 'success' | 'warning' | 'accent';

type KpiTile = {
  id: string;
  title: string;
  value: number;
  icon: string;
  tone: Tone;
  caption?: string;
  trend?: { value: number | null; label: string };
};

function Trend({ value, label }: { value: number | null; label: string }) {
  if (value == null) return null;
  const up = value >= 0;
  return (
    <span className={`efact-kpi__trend efact-kpi__trend--${up ? 'up' : 'down'}`}>
      {up ? '▲' : '▼'} {percentFormatter(Math.abs(value))}% <span>{label}</span>
    </span>
  );
}

export function KpiRow({ stats }: { stats: Stats }) {
  const tiles: KpiTile[] = [
    {
      id: 'sales-today',
      title: 'Ventas hoy',
      value: stats.netSalesToday,
      icon: 'ph ph-currency-dollar',
      tone: 'success',
      trend: { value: stats.dailyGrowth, label: 'vs ayer' },
    },
    {
      id: 'sales-month',
      title: 'Ventas mes',
      value: stats.salesThisMonth,
      icon: 'ph ph-trend-up',
      tone: 'warning',
      trend: { value: stats.monthGrowth, label: 'vs mes ant.' },
    },
    {
      id: 'receivables',
      title: 'Por cobrar',
      value: stats.receivablesTotal,
      icon: 'ph ph-hand-coins',
      tone: 'warning',
      caption:
        stats.receivablesCount > 0
          ? `${stats.receivablesCount} ${stats.receivablesCount === 1 ? 'cuenta' : 'cuentas'}`
          : undefined,
    },
    {
      id: 'payables',
      title: 'Por pagar',
      value: stats.payablesTotal,
      icon: 'ph ph-invoice',
      tone: 'accent',
      caption:
        stats.payablesCount > 0
          ? `${stats.payablesCount} ${stats.payablesCount === 1 ? 'cuenta' : 'cuentas'}`
          : undefined,
    },
  ];

  return (
    <div className="efact-dashboard__kpis">
      {tiles.map((tile) => (
        <article key={tile.id} className="efact-kpi">
          <span className={`efact-kpi__icon efact-kpi__icon--${tile.tone}`}>
            <i className={tile.icon} aria-hidden="true" />
          </span>
          <div className="efact-kpi__body">
            <span className="efact-kpi__title">{tile.title}</span>
            <strong className="efact-kpi__value">{formatMoney(tile.value)}</strong>
            {tile.trend ? (
              <Trend value={tile.trend.value} label={tile.trend.label} />
            ) : tile.caption ? (
              <span className="efact-kpi__caption">{tile.caption}</span>
            ) : null}
          </div>
        </article>
      ))}
    </div>
  );
}
