import { Card, EmptyState } from '@nubitio/ui';
import { CP_STRINGS } from '../../i18n/es';
import type { PaymentEventRecord, SubscriptionRecord } from '../../types';
import { CpDescriptionList, CpMetricGrid } from '../layout';

interface SubscriptionTabProps {
  subscription: SubscriptionRecord | null;
  paymentEvents: PaymentEventRecord[];
}

const S = CP_STRINGS.tenantTabs.subscription;

export const SubscriptionTab = ({ subscription, paymentEvents }: SubscriptionTabProps) => (
  <div className="cp-stack">
    <Card title={S.title}>
      {!subscription ? (
        <EmptyState icon="credit-card" title={S.empty} size="sm" />
      ) : (
        <>
          <CpMetricGrid
            layout="strip"
            density="compact"
            items={[
              { label: S.plan, value: subscription.plan },
              { label: S.status, value: subscription.status },
              {
                label: S.billing,
                value: subscription.billing_cycle === 'annual' ? S.billingAnnual : S.billingMonthly,
              },
              { label: S.gateway, value: subscription.gateway },
            ]}
          />
          <CpDescriptionList
            className="cp-description-list--grid"
            items={[
              {
                term: S.subscriptionId,
                value: <code className="cp-code">{subscription.id}</code>,
              },
              { term: S.periodEnd, value: subscription.current_period_end ?? '—' },
              { term: S.created, value: subscription.created_at },
            ]}
          />
        </>
      )}
    </Card>

    {paymentEvents.length > 0 && (
      <Card title={S.paymentsTitle}>
        <div className="cp-data-table-wrap">
          <table className="cp-data-table">
            <thead>
              <tr>
                <th>{CP_STRINGS.tenants.colId}</th>
                <th>{S.gateway}</th>
                <th>{S.colEvent}</th>
                <th>{S.colProcessed}</th>
                <th>{S.created}</th>
              </tr>
            </thead>
            <tbody>
              {paymentEvents.map((event) => (
                <tr key={event.id}>
                  <td>{event.id}</td>
                  <td>{event.gateway}</td>
                  <td>
                    <code className="cp-code">{event.event_type}</code>
                  </td>
                  <td>{event.processed_at ?? '—'}</td>
                  <td>{event.created_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    )}
  </div>
);