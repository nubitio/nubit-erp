import { useQuery } from '@tanstack/react-query';
import { Card, Code, DataTable } from '@nubitio/ui';
import { controlPlaneClient } from '../api/controlPlaneClient';
import {
  CpAlert,
  CpMetricGrid,
  CpPage,
  CpPageHeader,
  CpPageSkeleton,
  CpSection,
  Grid,
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

      <Grid cols={2} gap={4}>
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
      </Grid>

      <CpSection title={CP_STRINGS.dashboard.sections.audit}>
        {auditSummary.length === 0 ? (
          <Card variant="panel" title={CP_STRINGS.dashboard.auditEmpty}>{CP_STRINGS.dashboard.auditEmptyHint}</Card>
        ) : (
          <DataTable
            rows={auditSummary}
            getRowKey={(row) => row.action}
            columns={[
              {
                id: 'action',
                header: CP_STRINGS.dashboard.auditAction,
                render: (row) => <Code>{row.action}</Code>,
              },
              {
                id: 'count',
                header: CP_STRINGS.dashboard.auditCount,
                align: 'right',
                accessor: (row) => row.count,
              },
            ]}
          />
        )}
      </CpSection>
    </CpPage>
  );
};