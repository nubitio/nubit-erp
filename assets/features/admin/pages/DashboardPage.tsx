import './DashboardPage.scss';
import { useMemo } from 'react';
import { Chart } from 'devextreme-react';
import {
  ArgumentAxis,
  Label,
  Legend,
  Series,
  Size,
  Tooltip,
  ValueAxis,
} from 'devextreme-react/chart';
import LoadPanel from 'devextreme-react/load-panel';
import { AppToolbar } from '@shared/ui/toolbar/AppToolbar';
import { CardAnalytics } from '@widgets/library/card-analytics/CardAnalytics';
import ScrollView from 'devextreme-react/scroll-view';
import { currencyFormatter, decimalFormatter } from '@widgets/dashboard/formatters';
import { useDashboardOverview } from './hooks/useDashboardOverview';
import { useCashRegisters } from './hooks/useCashRegisters';
import { userInformation } from '@core/common/UserInformation';
import { useScreenSize } from '@utils/media-query';

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="dash-section-header">
      <span className="dash-section-header__title">{title}</span>
      {subtitle && <span className="dash-section-header__sub">{subtitle}</span>}
    </div>
  );
}

export const DashboardPage = () => {
  const screen = useScreenSize();
  const isMobileDashboard = screen.isXSmall || screen.isSmall;
  const { data, loading, error, refetch } = useDashboardOverview({ lite: isMobileDashboard });
  const { cashRegisters, isLoading: cashboxLoading } = useCashRegisters();
  const summary = useMemo(() => data.summary, [data]);
  const purchasesSummary = useMemo(() => data.purchasesSummary, [data]);
  const { currentUserName, currentBranchName } = userInformation();

  // ── Derived operational values ──────────────────────────────────────────────
  const openCashCount = cashRegisters.filter((c) => c.status === 'open').length;
  const criticalStockCount = data.lowStockProducts.filter((p) => p.stockPercentage < 30).length;
  const warnStockCount = data.lowStockProducts.filter(
    (p) => p.stockPercentage >= 30 && p.stockPercentage < 60,
  ).length;
  const totalStockAlerts = criticalStockCount + warnStockCount;
  const growthUp =
    summary.dailyGrowth !== null && summary.dailyGrowth !== undefined && summary.dailyGrowth >= 0;
  const monthGrowthUp =
    summary.monthGrowth !== null && summary.monthGrowth !== undefined && summary.monthGrowth >= 0;
  const purchasesMonthGrowthUp =
    purchasesSummary.monthGrowth !== null &&
    purchasesSummary.monthGrowth !== undefined &&
    purchasesSummary.monthGrowth >= 0;

  const totalPaymentsToday = data.paymentMethodsToday.reduce((acc, item) => acc + item.total, 0);
  const bestHour = data.hourlySalesToday.reduce<{ hour: string; total: number } | null>(
    (best, current) => {
      if (!best || current.total > best.total) {
        return current;
      }

      return best;
    },
    null,
  );
  const topPaymentMethod = data.paymentMethodsToday[0] ?? null;

  if (isMobileDashboard) {
    return (
      <ScrollView className="view-wrapper-scroll">
        <AppToolbar title="Dashboard" onRefresh={refetch}>
          {error && <div className="dashboard-error">{error}</div>}
          {data.meta?.partial && !error && (
            <div className="dashboard-error">Se cargó información parcial del dashboard.</div>
          )}

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
                {bestHour
                  ? `${bestHour.hour} · ${currencyFormatter(bestHour.total)}`
                  : 'Sin ventas'}
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
            {data.paymentMethodsToday.map((item) => {
              const percentage =
                totalPaymentsToday > 0 ? (item.total / totalPaymentsToday) * 100 : 0;

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
            {data.paymentMethodsToday.length === 0 && (
              <div className="empty">Sin datos disponibles</div>
            )}
          </ul>

          <SectionHeader title="Ultimas ventas" subtitle="Movimientos recientes" />
          <ul className="ranked-list dash-mobile-list">
            {data.recentSalesToday.map((item) => (
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
            {data.recentSalesToday.length === 0 && (
              <div className="empty">Sin ventas registradas hoy</div>
            )}
          </ul>
        </AppToolbar>
        <LoadPanel
          container=".content"
          visible={loading || cashboxLoading}
          position={{ of: '.layout-body' }}
        />
      </ScrollView>
    );
  }

  return (
    <ScrollView className="view-wrapper-scroll">
      <AppToolbar title="Dashboard" onRefresh={refetch}>
        {error && <div className="dashboard-error">{error}</div>}
        {data.meta?.partial && !error && (
          <div className="dashboard-error">Se cargó información parcial del dashboard.</div>
        )}

        {/* ── 1. Operación viva ────────────────────────── */}
        <div className="dash-status-bar">
          <span className={`dash-status-pill ${openCashCount > 0 ? 'ok' : 'neutral'}`}>
            <i className="ph ph-cash-register" />
            {cashboxLoading
              ? 'Cargando cajas…'
              : `${openCashCount} ${openCashCount === 1 ? 'caja abierta' : 'cajas abiertas'}`}
          </span>

          <span
            className={`dash-status-pill ${
              criticalStockCount > 0 ? 'critical' : totalStockAlerts > 0 ? 'warn' : 'ok'
            }`}
          >
            <i className="ph ph-package" />
            {criticalStockCount > 0
              ? `${criticalStockCount} productos sin stock`
              : totalStockAlerts > 0
                ? `${totalStockAlerts} productos stock bajo`
                : 'Stock OK'}
          </span>

          {summary.dailyGrowth !== null && summary.dailyGrowth !== undefined && (
            <span className={`dash-status-pill ${growthUp ? 'ok' : 'warn'}`}>
              <i className={`ph ph-trend-${growthUp ? 'up' : 'down'}`} />
              {growthUp ? '+' : ''}
              {summary.dailyGrowth.toFixed(1)}% vs ayer
            </span>
          )}

          {data.meta?.partial && (
            <span className="dash-status-pill warn">
              <i className="ph ph-warning" /> Datos parciales
            </span>
          )}

          <span className="dash-status-bar__branch">
            <i className="ph ph-map-pin" />
            {currentBranchName}
          </span>
        </div>

        {/* ── 2. Dinero hoy ────────────────────────────── */}
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

        {/* ── 3. Estado operacional ─────────────────────── */}
        <SectionHeader title="Estado operacional" />
        <div className="dash-ops-grid">
          <CardAnalytics
            title="Cajas"
            contentClass="list-card"
            menuVisible={false}
            isLoading={cashboxLoading}
          >
            <ul className="ranked-list">
              {cashRegisters.map((item) => (
                <li key={item.id}>
                  <div className="ranked-list__title">{item.name}</div>
                  <div className="ranked-list__meta">
                    <span className={item.status === 'open' ? 'cash-open' : 'cash-closed'}>
                      <span className={`cash-dot ${item.status === 'open' ? 'open' : ''}`} />
                      {item.status === 'open' ? 'Abierta' : 'Cerrada'}
                      {item.cashier ? ` · ${item.cashier}` : ''}
                    </span>
                    <span>{currencyFormatter(item.balance ?? 0)}</span>
                  </div>
                </li>
              ))}
              {cashRegisters.length === 0 && !cashboxLoading && (
                <div className="empty">Sin cajas registradas</div>
              )}
            </ul>
          </CardAnalytics>

          <CardAnalytics title="Stock crítico" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list stock-alert">
              {data.lowStockProducts.map((item, idx) => (
                <li
                  key={idx}
                  className={
                    item.stockPercentage < 30
                      ? 'critical'
                      : item.stockPercentage < 60
                        ? 'warning'
                        : ''
                  }
                >
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span className="stock-info">
                      {decimalFormatter(item.currentStock)} / {decimalFormatter(item.minStock)} u.
                      <span className="stock-badge">{item.stockPercentage}%</span>
                    </span>
                  </div>
                </li>
              ))}
              {data.lowStockProducts.length === 0 && (
                <div className="empty">✓ Stock en niveles óptimos</div>
              )}
            </ul>
          </CardAnalytics>
        </div>

        {/* ── 4. Rankings ──────────────────────────────── */}
        <SectionHeader title="Rankings" subtitle="Últimos 30 días" />
        <div className="dash-rankings-grid">
          <CardAnalytics title="Top productos" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              {data.topProducts.map((item) => (
                <li key={item.product}>
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span>{decimalFormatter(item.quantity)} u.</span>
                    <span>{currencyFormatter(item.total)}</span>
                  </div>
                </li>
              ))}
              {data.topProducts.length === 0 && <div className="empty">Sin datos disponibles</div>}
            </ul>
          </CardAnalytics>

          <CardAnalytics title="Top clientes" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              {data.topCustomers.map((item) => (
                <li key={item.customer}>
                  <div className="ranked-list__title">{item.customer}</div>
                  <div className="ranked-list__meta">
                    <span>{decimalFormatter(item.invoices)} docs</span>
                    <span>{currencyFormatter(item.total)}</span>
                  </div>
                </li>
              ))}
              {data.topCustomers.length === 0 && <div className="empty">Sin datos disponibles</div>}
            </ul>
          </CardAnalytics>
        </div>

        <SectionHeader title="Compras e ingresos" subtitle="Últimos 30 días" />
        <div className="dash-rankings-grid">
          <CardAnalytics title="Resumen de compras" contentClass="list-card" menuVisible={false}>
            <ul className="ranked-list">
              <li>
                <div className="ranked-list__title">Compras hoy</div>
                <div className="ranked-list__meta">
                  <span>{currencyFormatter(purchasesSummary.purchasesToday)}</span>
                  <span>{decimalFormatter(purchasesSummary.receiptsToday)} ingresos</span>
                </div>
              </li>
              <li>
                <div className="ranked-list__title">Compras semana</div>
                <div className="ranked-list__meta">
                  <span>{currencyFormatter(purchasesSummary.purchasesWeek)}</span>
                  <span>{decimalFormatter(purchasesSummary.receiptsThisMonth)} ingresos mes</span>
                </div>
              </li>
              <li>
                <div className="ranked-list__title">Compras mes</div>
                <div className="ranked-list__meta">
                  <span>{currencyFormatter(purchasesSummary.purchasesThisMonth)}</span>
                  {purchasesSummary.monthGrowth !== null &&
                    purchasesSummary.monthGrowth !== undefined && (
                      <span className={purchasesMonthGrowthUp ? 'cash-open' : 'cash-closed'}>
                        {purchasesMonthGrowthUp ? '+' : ''}
                        {purchasesSummary.monthGrowth.toFixed(1)}% vs mes anterior
                      </span>
                    )}
                </div>
              </li>
            </ul>
          </CardAnalytics>

          <CardAnalytics
            title="Top productos comprados"
            contentClass="list-card"
            menuVisible={false}
          >
            <ul className="ranked-list">
              {data.topPurchasedProducts.map((item) => (
                <li key={item.product}>
                  <div className="ranked-list__title">{item.product}</div>
                  <div className="ranked-list__meta">
                    <span>{decimalFormatter(item.quantity)} u.</span>
                    <span>{currencyFormatter(item.total)}</span>
                  </div>
                </li>
              ))}
              {data.topPurchasedProducts.length === 0 && (
                <div className="empty">Sin datos disponibles</div>
              )}
            </ul>
          </CardAnalytics>
        </div>

        {/* ── 5. Evolución ─────────────────────────────── */}
        <SectionHeader title="Evolución de ventas" />
        <div className="dash-charts-2col">
          <CardAnalytics
            title="Evolución mensual (12 meses)"
            contentClass="chart-card"
            menuVisible={false}
          >
            <Chart dataSource={data.salesByMonth} id="sales-by-month">
              <Size height={260} />
              <ArgumentAxis discreteAxisDivisionMode="betweenLabels" />
              <Series
                valueField="total"
                argumentField="label"
                type="bar"
                color="var(--accent-color)"
              />
              <ValueAxis>
                <Label customizeText={(arg) => currencyFormatter(Number(arg.value))} />
              </ValueAxis>
              <Tooltip
                enabled
                customizeTooltip={(arg) => ({
                  text: currencyFormatter('value' in arg ? Number(arg.value) : 0),
                })}
              />
              <Legend visible={false} />
            </Chart>
          </CardAnalytics>

          <CardAnalytics
            title="Ventas diarias (mes actual)"
            contentClass="chart-card"
            menuVisible={false}
          >
            <Chart dataSource={data.salesByDay} id="sales-by-day">
              <Size height={260} />
              <ArgumentAxis argumentType="numeric" />
              <Series
                valueField="total"
                argumentField="day"
                type="splinearea"
                color="var(--accent-color)"
              />
              <ValueAxis>
                <Label customizeText={(arg) => currencyFormatter(Number(arg.value))} />
              </ValueAxis>
              <Tooltip
                enabled
                customizeTooltip={(arg) => ({
                  text: currencyFormatter('value' in arg ? Number(arg.value) : 0),
                })}
              />
              <Legend visible={false} />
            </Chart>
          </CardAnalytics>
        </div>
      </AppToolbar>
      <LoadPanel container=".content" visible={loading} position={{ of: '.layout-body' }} />
    </ScrollView>
  );
};
