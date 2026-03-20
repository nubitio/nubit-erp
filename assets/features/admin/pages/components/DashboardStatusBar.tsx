import { SummaryStats } from '../types';
import { DashboardMetrics } from '../hooks/useDashboardMetrics';

type Props = {
  summary: SummaryStats;
  metrics: DashboardMetrics;
  isPartial: boolean;
  currentBranchName: string;
  cashboxLoading: boolean;
};

export function DashboardStatusBar({
  summary,
  metrics,
  isPartial,
  currentBranchName,
  cashboxLoading,
}: Props) {
  const { openCashCount, criticalStockCount, totalStockAlerts, growthUp } = metrics;

  return (
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

      {isPartial && (
        <span className="dash-status-pill warn">
          <i className="ph ph-warning" /> Datos parciales
        </span>
      )}

      <span className="dash-status-bar__branch">
        <i className="ph ph-map-pin" />
        {currentBranchName}
      </span>
    </div>
  );
}
