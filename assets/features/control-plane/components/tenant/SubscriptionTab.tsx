import type { PaymentEventRecord, SubscriptionRecord } from '../../types';

interface SubscriptionTabProps {
  subscription: SubscriptionRecord | null;
  paymentEvents: PaymentEventRecord[];
}

export const SubscriptionTab = ({ subscription, paymentEvents }: SubscriptionTabProps) => (
  <div className="cp-stack">
    <section className="cp-card">
      <h3>Subscription</h3>
      {!subscription ? (
        <div className="cp-empty">No active subscription. This tenant is on the free plan.</div>
      ) : (
        <>
          <div className="cp-metrics cp-metrics--compact">
            <div className="cp-metric">
              <span className="cp-metric__label">Plan</span>
              <span className="cp-metric__value cp-metric__value--sm">{subscription.plan}</span>
            </div>
            <div className="cp-metric">
              <span className="cp-metric__label">Status</span>
              <span className="cp-metric__value cp-metric__value--sm">{subscription.status}</span>
            </div>
            <div className="cp-metric">
              <span className="cp-metric__label">Billing</span>
              <span className="cp-metric__value cp-metric__value--sm">
                {subscription.billing_cycle === 'annual' ? 'Annual' : 'Monthly'}
              </span>
            </div>
            <div className="cp-metric">
              <span className="cp-metric__label">Gateway</span>
              <span className="cp-metric__value cp-metric__value--sm">{subscription.gateway}</span>
            </div>
          </div>
          <dl className="cp-dl cp-dl--grid">
            <dt>Subscription ID</dt>
            <dd>
              <code>{subscription.id}</code>
            </dd>
            <dt>Period end</dt>
            <dd>{subscription.current_period_end ?? '—'}</dd>
            <dt>Created</dt>
            <dd>{subscription.created_at}</dd>
          </dl>
        </>
      )}
    </section>

    {paymentEvents.length > 0 && (
      <section className="cp-card">
        <h3>Recent payment events</h3>
        <table className="cp-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Gateway</th>
              <th>Event</th>
              <th>Processed</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            {paymentEvents.map((event) => (
              <tr key={event.id}>
                <td>{event.id}</td>
                <td>{event.gateway}</td>
                <td>
                  <code>{event.event_type}</code>
                </td>
                <td>{event.processed_at ?? '—'}</td>
                <td>{event.created_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    )}
  </div>
);