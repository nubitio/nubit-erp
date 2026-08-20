import { PLANS, planPrice, type BillingCycle, type PaymentGateway, type PlanKey } from '../lib/plans';

interface PlanSelectorProps {
  plan: PlanKey;
  billingCycle: BillingCycle;
  gateway: PaymentGateway;
  onPlanChange: (plan: PlanKey) => void;
  onBillingCycleChange: (cycle: BillingCycle) => void;
  onGatewayChange: (gateway: PaymentGateway) => void;
  currentPlan?: PlanKey;
  savingsLabel?: string;
}

export function PlanSelector({
  plan,
  billingCycle,
  gateway,
  onPlanChange,
  onBillingCycleChange,
  onGatewayChange,
  currentPlan,
  savingsLabel = '2 meses gratis',
}: PlanSelectorProps) {
  const isPaid = plan !== 'free';

  return (
    <div className="ap-plans">
      <div className="ap-plans__toggle" role="group" aria-label="Ciclo de facturación">
        <button
          type="button"
          className={`ap-plans__toggle-btn${billingCycle === 'monthly' ? ' is-active' : ''}`}
          onClick={() => onBillingCycleChange('monthly')}
        >
          Mensual
        </button>
        <button
          type="button"
          className={`ap-plans__toggle-btn${billingCycle === 'annual' ? ' is-active' : ''}`}
          onClick={() => onBillingCycleChange('annual')}
        >
          Anual
          <span className="ap-plans__savings">{savingsLabel}</span>
        </button>
      </div>

      <div className="ap-plans__grid">
        {PLANS.map((option) => {
          const selected = plan === option.key;
          const isCurrent = currentPlan === option.key;
          return (
            <button
              key={option.key}
              type="button"
              className={`ap-plans__card${selected ? ' is-selected' : ''}${isCurrent ? ' is-current' : ''}`}
              onClick={() => onPlanChange(option.key)}
              aria-pressed={selected}
            >
              {isCurrent ? <span className="ap-plans__current-tag">Actual</span> : null}
              <span className="ap-plans__name">{option.name}</span>
              <span className="ap-plans__price">{planPrice(option, billingCycle)}</span>
              <span className="ap-plans__hint">{option.hint}</span>
              <ul className="ap-plans__features">
                {option.features.map((feature) => (
                  <li key={feature}>
                    <i className="ph ph-check" aria-hidden="true" />
                    {feature}
                  </li>
                ))}
              </ul>
            </button>
          );
        })}
      </div>

      {isPaid ? (
        <div className="ap-plans__gateways">
          <p className="ap-plans__gateways-label">Medio de pago</p>
          <div className="ap-plans__gateways-grid">
            {(['mercadopago', 'paypal'] as const).map((value) => (
              <button
                key={value}
                type="button"
                className={`ap-plans__gateway${gateway === value ? ' is-selected' : ''}`}
                onClick={() => onGatewayChange(value)}
                aria-pressed={gateway === value}
              >
                <i className={`ph ${value === 'paypal' ? 'ph-paypal-logo' : 'ph-credit-card'}`} aria-hidden="true" />
                {value === 'paypal' ? 'PayPal' : 'MercadoPago'}
              </button>
            ))}
          </div>
        </div>
      ) : null}
    </div>
  );
}