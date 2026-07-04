import { useQuery } from '@tanstack/react-query';
import { controlPlaneClient } from '../api/controlPlaneClient';
import { MetricCard } from '../components/MetricCard';

const formatBytes = (bytes: number): string => {
  if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(2)} GB`;
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  return `${(bytes / 1024).toFixed(1)} KB`;
};

export const DashboardPage = () => {
  const { data, isLoading, error } = useQuery({
    queryKey: ['control-plane', 'dashboard'],
    queryFn: () => controlPlaneClient.getDashboard(),
  });

  if (isLoading) {
    return <div className="cp-page cp-page--loading">Cargando métricas…</div>;
  }

  if (error || !data) {
    return (
      <div className="cp-page">
        <div className="cp-alert cp-alert--danger">
          No se pudo cargar el dashboard: {(error as Error)?.message ?? 'Error desconocido'}
        </div>
      </div>
    );
  }

  const { tenantStats, planStats, backupStats, auditSummary } = data;

  return (
    <div className="cp-page">
      <header className="cp-page__header">
        <div>
          <h1>Dashboard</h1>
          <p>Resumen operativo del SaaS multi-tenant.</p>
        </div>
      </header>

      <section className="cp-section">
        <h2>Tenants</h2>
        <div className="cp-metrics">
          <MetricCard label="Total" value={tenantStats.total} />
          <MetricCard label="Activos" value={tenantStats.active} tone="success" />
          <MetricCard label="Suspendidos" value={tenantStats.suspended} tone="warning" />
          <MetricCard label="Eliminados" value={tenantStats.deleted} tone="danger" />
        </div>
      </section>

      <div className="cp-grid cp-grid--2">
        <section className="cp-section">
          <h2>Planes</h2>
          <div className="cp-metrics cp-metrics--compact">
            <MetricCard label="Planes" value={planStats.total_plans} />
            <MetricCard
              label="Sin plan"
              value={planStats.tenants_without_plan}
              tone={planStats.tenants_without_plan > 0 ? 'warning' : 'default'}
            />
          </div>
        </section>

        <section className="cp-section">
          <h2>Backups</h2>
          <div className="cp-metrics cp-metrics--compact">
            <MetricCard label="Completados" value={backupStats.total_backups} />
            <MetricCard
              label="Fallidos"
              value={backupStats.failed_count}
              tone={backupStats.failed_count > 0 ? 'danger' : 'default'}
            />
          </div>
          <div className="cp-meta">
            <span>Tamaño total: {formatBytes(backupStats.total_size_bytes)}</span>
            <span>Último backup: {backupStats.last_backup_at ?? 'Nunca'}</span>
          </div>
        </section>
      </div>

      <section className="cp-section">
        <h2>Actividad de auditoría (24h)</h2>
        {auditSummary.length === 0 ? (
          <div className="cp-empty">Sin actividad en las últimas 24 horas.</div>
        ) : (
          <table className="cp-table">
            <thead>
              <tr>
                <th>Acción</th>
                <th className="cp-table__num">Conteo</th>
              </tr>
            </thead>
            <tbody>
              {auditSummary.map((row) => (
                <tr key={row.action}>
                  <td>
                    <code>{row.action}</code>
                  </td>
                  <td className="cp-table__num">{row.count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
};