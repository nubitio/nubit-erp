import './DashboardPage.scss';
import { AppToolbar } from '@shared/ui/toolbar/AppToolbar';
import { useDashboardOverview } from './hooks/useDashboardOverview';
import { useCashRegisters } from './hooks/useCashRegisters';
import { useDashboardMetrics } from './hooks/useDashboardMetrics';
import { useSessionInfo } from '@app/contexts/useSessionInfo';
import { useScreenSize } from '@nubit/admin';
import { DashboardStatusBar } from './components/DashboardStatusBar';
import { DashboardHero } from './components/DashboardHero';
import { DashboardOperations } from './components/DashboardOperations';
import { DashboardRankings } from './components/DashboardRankings';
import { DashboardCharts } from './components/DashboardCharts';

export const DashboardPage = () => {
  const screen = useScreenSize();
  const isMobile = screen.isXSmall || screen.isSmall;
  const { data, loading, error, refetch } = useDashboardOverview({ lite: isMobile });
  const { cashRegisters, isLoading: cashboxLoading } = useCashRegisters();
  const metrics = useDashboardMetrics(data, cashRegisters);
  const { currentUserName, currentBranchName } = useSessionInfo();

  const isPartial = !!(data.meta?.partial && !error);

  return (
    <div className="view-wrapper-scroll dashboard-page">
      <AppToolbar title="Dashboard" onRefresh={refetch}>
        {error && <div className="dashboard-error">{error}</div>}
        {isPartial && (
          <div className="dashboard-error">Se cargó información parcial del dashboard.</div>
        )}

        {!isMobile && (
          <DashboardStatusBar
            summary={data.summary}
            metrics={metrics}
            isPartial={!!data.meta?.partial}
            currentBranchName={currentBranchName}
            cashboxLoading={cashboxLoading}
          />
        )}

        <DashboardHero
          isMobile={isMobile}
          summary={data.summary}
          metrics={metrics}
          paymentMethodsToday={data.paymentMethodsToday}
          recentSalesToday={data.recentSalesToday}
          currentUserName={currentUserName}
          currentBranchName={currentBranchName}
          cashboxLoading={cashboxLoading}
        />

        {!isMobile && (
          <>
            <DashboardOperations
              cashRegisters={cashRegisters}
              cashboxLoading={cashboxLoading}
              lowStockProducts={data.lowStockProducts}
            />
            <DashboardRankings
              topProducts={data.topProducts}
              topCustomers={data.topCustomers}
              topPurchasedProducts={data.topPurchasedProducts}
              purchasesSummary={data.purchasesSummary}
              metrics={metrics}
            />
            <DashboardCharts salesByMonth={data.salesByMonth} salesByDay={data.salesByDay} />
          </>
        )}

        {isMobile && (
          <DashboardRankings
            topProducts={data.topProducts}
            topCustomers={data.topCustomers}
            topPurchasedProducts={data.topPurchasedProducts}
            purchasesSummary={data.purchasesSummary}
            metrics={metrics}
          />
        )}
      </AppToolbar>
      {(loading || cashboxLoading) && (
        <div className="dashboard-loading-overlay" aria-hidden="true">
          <span className="dashboard-loading-spinner" />
        </div>
      )}
    </div>
  );
};
