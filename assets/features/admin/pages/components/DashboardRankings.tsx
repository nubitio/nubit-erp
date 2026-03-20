import { CardAnalytics } from '@shared/ui/card-analytics/CardAnalytics';
import { TopProduct, TopCustomer, TopPurchasedProduct, PurchasesSummaryStats } from '../types';
import { DashboardMetrics } from '../hooks/useDashboardMetrics';
import { currencyFormatter, decimalFormatter } from '@shared/ui/dashboard/formatters';

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="dash-section-header">
      <span className="dash-section-header__title">{title}</span>
      {subtitle && <span className="dash-section-header__sub">{subtitle}</span>}
    </div>
  );
}

type Props = {
  topProducts: TopProduct[];
  topCustomers: TopCustomer[];
  topPurchasedProducts: TopPurchasedProduct[];
  purchasesSummary: PurchasesSummaryStats;
  metrics: DashboardMetrics;
};

export function DashboardRankings({
  topProducts,
  topCustomers,
  topPurchasedProducts,
  purchasesSummary,
  metrics,
}: Props) {
  const { purchasesMonthGrowthUp } = metrics;

  return (
    <>
      <SectionHeader title="Rankings" subtitle="Últimos 30 días" />
      <div className="dash-rankings-grid">
        <CardAnalytics title="Top productos" contentClass="list-card" menuVisible={false}>
          <ul className="ranked-list">
            {topProducts.map((item) => (
              <li key={item.product}>
                <div className="ranked-list__title">{item.product}</div>
                <div className="ranked-list__meta">
                  <span>{decimalFormatter(item.quantity)} u.</span>
                  <span>{currencyFormatter(item.total)}</span>
                </div>
              </li>
            ))}
            {topProducts.length === 0 && <div className="empty">Sin datos disponibles</div>}
          </ul>
        </CardAnalytics>

        <CardAnalytics title="Top clientes" contentClass="list-card" menuVisible={false}>
          <ul className="ranked-list">
            {topCustomers.map((item) => (
              <li key={item.customer}>
                <div className="ranked-list__title">{item.customer}</div>
                <div className="ranked-list__meta">
                  <span>{decimalFormatter(item.invoices)} docs</span>
                  <span>{currencyFormatter(item.total)}</span>
                </div>
              </li>
            ))}
            {topCustomers.length === 0 && <div className="empty">Sin datos disponibles</div>}
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

        <CardAnalytics title="Top productos comprados" contentClass="list-card" menuVisible={false}>
          <ul className="ranked-list">
            {topPurchasedProducts.map((item) => (
              <li key={item.product}>
                <div className="ranked-list__title">{item.product}</div>
                <div className="ranked-list__meta">
                  <span>{decimalFormatter(item.quantity)} u.</span>
                  <span>{currencyFormatter(item.total)}</span>
                </div>
              </li>
            ))}
            {topPurchasedProducts.length === 0 && (
              <div className="empty">Sin datos disponibles</div>
            )}
          </ul>
        </CardAnalytics>
      </div>
    </>
  );
}
