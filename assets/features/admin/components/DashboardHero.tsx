import { SummaryStats, PaymentMethodToday, RecentSaleToday } from '../types';
import { DashboardMetrics } from '../hooks/useDashboardMetrics';
import { currencyFormatter, decimalFormatter } from '@shared/ui/dashboard/formatters';

type Props = {
  isMobile: boolean;
  summary: SummaryStats;
  metrics: DashboardMetrics;
  paymentMethodsToday: PaymentMethodToday[];
  recentSalesToday: RecentSaleToday[];
  currentUserName: string;
  currentBranchName: string;
  cashboxLoading: boolean;
};

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="dash-section-header">
      <span className="dash-section-header__title">{title}</span>
      {subtitle && <span className="dash-section-header__sub">{subtitle}</span>}
    </div>
  );
}

export function DashboardHero({
  isMobile,
  summary,
  metrics,
  paymentMethodsToday,
  recentSalesToday,
  currentUserName,
  currentBranchName,
  cashboxLoading,
}: Props) {
  const { growthUp, monthGrowthUp, bestHour, topPaymentMethod, totalPaymentsToday, openCashCount } =
    metrics;

  if (isMobile) {
    return (
      <>
        <div className="dash-mobile-hero">
          <span className="dash-mobile-hero__branch">
            <i className="ph ph-map-pin" /> {currentBranchName}
          </span>
          <span className="dash-mobile-hero__title">Hola, {currentUserName}</span>
          <span className="dash-mobile-hero__amount">
            {currencyFormatter(summary.netSalesToday)}
          </span>
          <span className="dash-mobile-hero__label">Venta neta de hoy</span>
          <div className="dash-mobile-hero__meta">
            <span>{decimalFormatter(summary.invoicesToday)} comprobantes</span>
            {summary.dailyGrowth !== null && summary.dailyGrowth !== undefined && (
              <span className={summary.dailyGrowth >= 0 ? 'up' : 'down'}>
                {summary.dailyGrowth >= 0 ? '+' : ''}
                {summary.dailyGrowth.toFixed(1)}% vs ayer
              </span>
            )}
          </div>
        </div>

        <div className="dash-mobile-kpis">
          <div className="dash-mobile-kpi">
            <span className="dash-mobile-kpi__label">Ventas hoy</span>
            <strong>{currencyFormatter(summary.salesToday)}</strong>
          </div>
          <div className="dash-mobile-kpi">
            <span className="dash-mobile-kpi__label">Ticket promedio</span>
            <strong>{currencyFormatter(summary.avgTicketToday)}</strong>
          </div>
          <div className="dash-mobile-kpi">
            <span className="dash-mobile-kpi__label">Ventas 7 dias</span>
            <strong>{currencyFormatter(summary.salesWeek)}</strong>
          </div>
          <div className="dash-mobile-kpi">
            <span className="dash-mobile-kpi__label">Reembolsos hoy</span>
            <strong>{currencyFormatter(summary.refundsToday)}</strong>
          </div>
        </div>

        <SectionHeader title="Ritmo de caja" subtitle="Hoy" />
        <div className="dash-mobile-surface">
          <div className="dash-mobile-surface__row">
            <span>Pico de venta</span>
            <strong>
              {bestHour ? `${bestHour.hour} · ${currencyFormatter(bestHour.total)}` : 'Sin ventas'}
            </strong>
          </div>
          <div className="dash-mobile-surface__row">
            <span>Metodo principal</span>
            <strong>
              {topPaymentMethod
                ? `${topPaymentMethod.method} · ${currencyFormatter(topPaymentMethod.total)}`
                : 'Sin datos'}
            </strong>
          </div>
          <div className="dash-mobile-surface__row">
            <span>Cajas abiertas</span>
            <strong>{cashboxLoading ? 'Cargando...' : String(openCashCount)}</strong>
          </div>
        </div>

        <SectionHeader title="Metodos de pago" subtitle="Distribucion de hoy" />
        <ul className="ranked-list dash-mobile-list">
          {paymentMethodsToday.map((item) => {
            const percentage = totalPaymentsToday > 0 ? (item.total / totalPaymentsToday) * 100 : 0;
            return (
              <li key={item.method}>
                <div className="ranked-list__title">{item.method}</div>
                <div className="ranked-list__meta">
                  <span>{percentage.toFixed(1)}%</span>
                  <span>{currencyFormatter(item.total)}</span>
                </div>
              </li>
            );
          })}
          {paymentMethodsToday.length === 0 && <div className="empty">Sin datos disponibles</div>}
        </ul>

        <SectionHeader title="Ultimas ventas" subtitle="Movimientos recientes" />
        <ul className="ranked-list dash-mobile-list">
          {recentSalesToday.map((item) => (
            <li key={item.id}>
              <div className="ranked-list__title">
                {item.issuedAt} · {item.paymentMethod}
              </div>
              <div className="ranked-list__meta">
                <span>Venta #{item.id}</span>
                <span>{currencyFormatter(item.total)}</span>
              </div>
            </li>
          ))}
          {recentSalesToday.length === 0 && <div className="empty">Sin ventas registradas hoy</div>}
        </ul>
      </>
    );
  }

  return (
    <div className="dash-today">
      <div className="dash-today__main">
        <span className="dash-today__label">Ventas hoy · {currentUserName}</span>
        <span className="dash-today__amount">{currencyFormatter(summary.salesToday)}</span>
        <div className="dash-today__row">
          {summary.dailyGrowth !== null && summary.dailyGrowth !== undefined && (
            <span className={`dash-today__delta ${growthUp ? 'up' : 'down'}`}>
              {growthUp ? '▲' : '▼'} {Math.abs(summary.dailyGrowth).toFixed(1)}% vs ayer
            </span>
          )}
          <span className="dash-today__pill">
            {decimalFormatter(summary.invoicesToday)} comprobantes
          </span>
          <span className="dash-today__pill">
            Ticket prom: {currencyFormatter(summary.avgTicketToday)}
          </span>
        </div>
      </div>

      <div className="dash-today__side">
        <div className="dash-today__stat">
          <span className="dash-today__stat-label">Esta semana</span>
          <span className="dash-today__stat-value">{currencyFormatter(summary.salesWeek)}</span>
        </div>
        <div className="dash-today__divider" />
        <div className="dash-today__stat">
          <span className="dash-today__stat-label">Este mes</span>
          <span className="dash-today__stat-value">
            {currencyFormatter(summary.salesThisMonth)}
          </span>
          {summary.monthGrowth !== null && summary.monthGrowth !== undefined && (
            <span className={`dash-today__stat-delta ${monthGrowthUp ? 'up' : 'down'}`}>
              {monthGrowthUp ? '+' : ''}
              {summary.monthGrowth.toFixed(1)}% vs mes anterior
            </span>
          )}
        </div>
        <div className="dash-today__divider" />
        <div className="dash-today__stat">
          <span className="dash-today__stat-label">Ticket promedio mes</span>
          <span className="dash-today__stat-value">{currencyFormatter(summary.avgTicket)}</span>
        </div>
        <div className="dash-today__divider" />
        <div className="dash-today__stat">
          <span className="dash-today__stat-label">Reembolsos hoy</span>
          <span className="dash-today__stat-value dash-today__stat-value--muted">
            {currencyFormatter(summary.refundsToday)}
          </span>
        </div>
      </div>
    </div>
  );
}
