import type { FormLayout } from '@nubitio/crud';

export const subscriptionPlanFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Plan',
      fields: ['name', 'product', 'billingCycle', 'amount', 'paymentTermDays'],
    },
    {
      label: 'Emisión',
      fields: ['branch', 'warehouse', 'isActive'],
    },
  ],
};

export const customerSubscriptionFormLayout: FormLayout = {
  type: 'sections',
  sections: [
    {
      label: 'Suscripción',
      fields: ['customer', 'plan', 'status', 'autoRenew'],
    },
    {
      label: 'Fechas',
      fields: ['startDate', 'nextBillingDate'],
    },
  ],
};
