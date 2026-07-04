import { useQuery } from '@tanstack/react-query';
import { Card } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import {
  CpAlert,
  CpGrid,
  CpMetricGrid,
  CpPage,
  CpPageHeader,
  CpPageSkeleton,
  CpSection,
} from '../components/layout';
import { CP_STRINGS } from '../i18n/es';

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
    return <CpPageSkeleton variant="dashboard" />;
  }

  if (error || !data) {
    return (
      <CpPage>
        <CpAlert tone="danger">
          {CP_STRINGS.dashboard.loadError}: {(error as Error)?.message ?? CP_STRINGS.unknownError}
        </CpAlert>
      </CpPage>
    );
  }

  const { tenantStats, planStats, backupStats, auditSummary } = data;

  return (
    <CpPage>
      <CpPageHeader title={CP_STRINGS.dashboard.title} subtitle={CP_STRINGS.dashboard.subtitle} />

      <CpSection title={CP_STRINGS.dashboard.sections.tenants}>
        <CpMetricGrid
          items={[
            { label: CP_STRINGS.dashboard.metrics.total, value: tenantStats.total, icon: 'buildings' },
            {
              label: CP_STRINGS.dashboard.metrics.active,
              value: tenantStats.active,
              tone: 'success',
              icon: 'check-circle',
            },
            {
              label: CP_STRINGS.dashboard.metrics.suspended,
              value: tenantStats.suspended,
              tone: 'warning',
              icon: 'pause-circle',
            },
            {
              label: CP_STRINGS.dashboard.metrics.deleted,
              value: tenantStats.deleted,
              tone: 'danger',
              icon: 'trash',
            },
          ]}
        />
      </CpSection>

      <CpGrid>
        <CpSection title={CP_STRINGS.dashboard.sections.plans}>
          <CpMetricGrid
            layout="strip"
            density="compact"
            items={[
              { label: CP_STRINGS.dashboard.metrics.plans, value: planStats.total_plans, icon: 'package' },
              {
                label: CP_STRINGS.dashboard.metrics.withoutPlan,
                value: planStats.tenants_without_plan,
                tone: planStats.tenants_without_plan > 0 ? 'warning' : 'default',
                icon: 'warning-circle',
              },
            ]}
          />
        </CpSection>

        <CpSection title={CP_STRINGS.dashboard.sections.backups}>
          <CpMetricGrid
            layout="strip"
            density="compact"
            items={[
              {
                label: CP_STRINGS.dashboard.metrics.completed,
                value: backupStats.total_backups,
                icon: 'archive',
              },
              {
                label: CP_STRINGS.dashboard.metrics.failed,
                value: backupStats.failed_count,
                tone: backupStats.failed_count > 0 ? 'danger' : 'default',
                icon: 'x-circle',
              },
            ]}
          />
          <div className="cp-meta">
            <span>
              {CP_STRINGS.dashboard.backupSize}: {formatBytes(backupStats.total_size_bytes)}
            </span>
            <span>
              {CP_STRINGS.dashboard.lastBackup}: {backupStats.last_backup_at ?? CP_STRINGS.never}
            </span>
          </div>
        </CpSection>
      </CpGrid>

      <CpSection title={CP_STRINGS.dashboard.sections.audit}>
        {auditSummary.length === 0 ? (
          <Card title={CP_STRINGS.dashboard.auditEmpty}>{CP_STRINGS.dashboard.auditEmptyHint}</Card>
        ) : (
          <div className="cp-data-table-wrap">
            <table className="cp-data-table">
              <thead>
                <tr>
                  <th>{CP_STRINGS.dashboard.auditAction}</th>
                  <th className="cp-data-table__num">{CP_STRINGS.dashboard.auditCount}</th>
                </tr>
              </thead>
              <tbody>
                {auditSummary.map((row) => (
                  <tr key={row.action}>
                    <td>
                      <code className="cp-code">{row.action}</code>
                    </td>
                    <td className="cp-data-table__num">{row.count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CpSection>
    </CpPage>
  );
};