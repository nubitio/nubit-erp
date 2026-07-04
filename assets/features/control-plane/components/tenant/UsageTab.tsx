import { Card, EmptyState } from '@nubitio/ui';
import { CP_STRINGS } from '../../i18n/es';
import type { QuotaUsage } from '../../types';
import { formatBytes, formatResourceLabel } from '../../utils/format';
import { CpAlert, CpMetricGrid } from '../layout';

interface UsageTabProps {
  quotaSummary: Record<string, QuotaUsage>;
  usageLatest: Record<string, number>;
  quotaAlerts: Array<{ resource: string; percent: number }>;
}

const S = CP_STRINGS.tenantTabs.usage;

const formatValue = (resource: string, value: number): string => {
  if (resource === 'storage_bytes') return formatBytes(value);
  return value.toLocaleString();
};

export const UsageTab = ({ quotaSummary, usageLatest, quotaAlerts }: UsageTabProps) => (
  <div className="cp-stack">
    {quotaAlerts.length > 0 && (
      <CpAlert tone="warning">
        <strong>{S.alertsTitle}:</strong>
        <ul>
          {quotaAlerts.map((alert) => (
            <li key={alert.resource}>
              {S.atPercent(formatResourceLabel(alert.resource), alert.percent)}
            </li>
          ))}
        </ul>
      </CpAlert>
    )}

    <Card variant="panel" title={S.metricsTitle}>
      <CpMetricGrid
        layout="strip"
        density="compact"
        items={Object.entries(usageLatest).map(([metric, value]) => ({
          label: formatResourceLabel(metric),
          value: formatValue(metric, value),
        }))}
      />
    </Card>

    <Card variant="panel" title={S.quotaTitle}>
      {Object.keys(quotaSummary).length === 0 ? (
        <EmptyState icon="chart-line-up" title={S.quotaEmpty} size="sm" />
      ) : (
        <div className="cp-quota-list">
          {Object.entries(quotaSummary).map(([resource, usage]) => {
            const unlimited = usage.limit === -1;
            const percent = unlimited || usage.limit <= 0 ? 0 : (usage.current / usage.limit) * 100;

            return (
              <div key={resource} className="cp-quota-item">
                <div className="cp-quota-item__header">
                  <span>{formatResourceLabel(resource)}</span>
                  <span className="cp-muted">
                    {formatValue(resource, usage.current)}
                    {' / '}
                    {unlimited ? CP_STRINGS.unlimited : formatValue(resource, usage.limit)}
                  </span>
                </div>
                {!unlimited && usage.limit > 0 && (
                  <div className="cp-progress">
                    <div
                      className={`cp-progress__bar${percent >= 80 ? ' cp-progress__bar--warn' : ''}`}
                      style={{ width: `${Math.min(percent, 100)}%` }}
                    />
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </Card>
  </div>
);