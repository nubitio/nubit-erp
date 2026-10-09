import { Card, Code, EmptyState } from '@nubitio/ui';
import { CP_STRINGS } from '../../i18n/es';
import type { PaymentEventRecord, SubscriptionRecord } from '../../types';
import { CpDescriptionList, CpMetricGrid, DataTable, Stack } from '../layout';

interface SubscriptionTabProps {
  subscription: SubscriptionRecord | null;
  paymentEvents: PaymentEventRecord[];
}

const S = CP_STRINGS.tenantTabs.subscription;

export const SubscriptionTab = ({ subscription, paymentEvents }: SubscriptionTabProps) => (
  <Stack>
    <Card variant="panel" title={S.title}>
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
                value: <Code>{subscription.id}</Code>,
              },
              { term: S.periodEnd, value: subscription.current_period_end ?? '—' },
              { term: S.created, value: subscription.created_at },
            ]}
          />
        </>
      )}
    </Card>

    {paymentEvents.length > 0 && (
      <Card variant="panel" title={S.paymentsTitle}>
        <DataTable
          variant="flush"
          rows={paymentEvents}
          getRowKey={(event) => event.id}
          columns={[
            { id: 'id', header: CP_STRINGS.tenants.colId, accessor: (event) => event.id },
            { id: 'gateway', header: S.gateway, accessor: (event) => event.gateway },
            {
              id: 'event',
              header: S.colEvent,
              render: (event) => <Code>{event.event_type}</Code>,
            },
            {
              id: 'processed',
              header: S.colProcessed,
              accessor: (event) => event.processed_at ?? '—',
            },
            { id: 'created', header: S.created, accessor: (event) => event.created_at },
          ]}
        />
      </Card>
    )}
  </Stack>
);
