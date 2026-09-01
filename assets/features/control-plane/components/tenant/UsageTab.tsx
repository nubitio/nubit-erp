import { Card, EmptyState } from '@nubitio/ui';
import { CP_STRINGS } from '../../i18n/es';
import type { QuotaUsage } from '../../types';
import { formatBytes, formatResourceLabel } from '../../utils/format';
import { CpAlert, CpMetricGrid, DataTable, Stack } from '../layout';

interface UsageDelta {
  previous: number;
  current: number;
  delta: number;
}

interface UsageTabProps {
  quotaSummary: Record<string, QuotaUsage>;
  usageLatest: Record<string, number>;
  usageDeltas: Record<string, UsageDelta>;
  quotaAlerts: Array<{ resource: string; percent: number }>;
}

const S = CP_STRINGS.tenantTabs.usage;

/** Quota resources whose allowance resets on the 1st (mirrors the backend). */
const MONTHLY_QUOTAS = new Set(['invoices']);

const formatValue = (resource: string, value: number): string => {
  if (resource === 'storage_bytes') return formatBytes(value);
  return value.toLocaleString();
};

const formatDelta = (resource: string, delta: number): string => {
  if (delta === 0) return '0';
  const sign = delta > 0 ? '+' : '−';
  return `${sign}${formatValue(resource, Math.abs(delta))}`;
};

export const UsageTab = ({ quotaSummary, usageLatest, usageDeltas, quotaAlerts }: UsageTabProps) => {
  const deltaRows = Object.entries(usageDeltas).map(([resource, d]) => ({ resource, ...d }));

  return (
    <Stack>
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
              const percent =
                unlimited || usage.limit <= 0 ? 0 : (usage.current / usage.limit) * 100;

              return (
                <div key={resource} className="cp-quota-item">
                  <div className="cp-quota-item__header">
                    <span>
                      {formatResourceLabel(resource)}
                      {MONTHLY_QUOTAS.has(resource) && (
                        <span className="cp-quota-item__period"> · {S.perMonth}</span>
                      )}
                    </span>
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

      <Card variant="panel" title={S.deltasTitle}>
        {deltaRows.length === 0 ? (
          <EmptyState icon="chart-line-up" title={S.deltasEmpty} size="sm" />
        ) : (
          <DataTable
            variant="flush"
            rows={deltaRows}
            getRowKey={(row) => row.resource}
            columns={[
              {
                id: 'resource',
                header: S.deltaColResource,
                accessor: (row) => formatResourceLabel(row.resource),
              },
              {
                id: 'previous',
                header: S.deltaColBefore,
                align: 'right',
                accessor: (row) => formatValue(row.resource, row.previous),
              },
              {
                id: 'current',
                header: S.deltaColNow,
                align: 'right',
                accessor: (row) => formatValue(row.resource, row.current),
              },
              {
                id: 'delta',
                header: S.deltaColChange,
                align: 'right',
                render: (row) => {
                  const tone =
                    row.delta > 0 ? ' cp-delta--up' : row.delta < 0 ? ' cp-delta--down' : '';
                  return <span className={`cp-delta${tone}`}>{formatDelta(row.resource, row.delta)}</span>;
                },
              },
            ]}
          />
        )}
      </Card>
    </Stack>
  );
};
