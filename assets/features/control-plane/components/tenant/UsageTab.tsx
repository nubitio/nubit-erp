import type { QuotaUsage } from '../../types';
import { formatBytes, formatResourceLabel } from '../../utils/format';

interface UsageTabProps {
  quotaSummary: Record<string, QuotaUsage>;
  usageLatest: Record<string, number>;
  quotaAlerts: Array<{ resource: string; percent: number }>;
}

const formatValue = (resource: string, value: number): string => {
  if (resource === 'storage_bytes') return formatBytes(value);
  return value.toLocaleString();
};

export const UsageTab = ({ quotaSummary, usageLatest, quotaAlerts }: UsageTabProps) => (
  <div className="cp-stack">
    {quotaAlerts.length > 0 && (
      <div className="cp-alert cp-alert--warning">
        <strong>Quota alerts:</strong>
        <ul>
          {quotaAlerts.map((alert) => (
            <li key={alert.resource}>
              {formatResourceLabel(alert.resource)} at {alert.percent}%
            </li>
          ))}
        </ul>
      </div>
    )}

    <section className="cp-card">
      <h3>Latest metrics</h3>
      <div className="cp-metrics cp-metrics--compact">
        {Object.entries(usageLatest).map(([metric, value]) => (
          <div key={metric} className="cp-metric">
            <span className="cp-metric__label">{formatResourceLabel(metric)}</span>
            <span className="cp-metric__value cp-metric__value--sm">
              {formatValue(metric, value)}
            </span>
          </div>
        ))}
      </div>
    </section>

    <section className="cp-card">
      <h3>Quota usage</h3>
      {Object.keys(quotaSummary).length === 0 ? (
        <div className="cp-empty">No quota data available.</div>
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
                    {unlimited ? 'Unlimited' : formatValue(resource, usage.limit)}
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
    </section>
  </div>
);