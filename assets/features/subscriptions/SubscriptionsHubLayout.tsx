import { FeatureHubLayout } from '@nubitio/admin';

export const SubscriptionsHubLayout = () => {
  return (
    <FeatureHubLayout
      density="compact"
      title="Suscripciones"
      subtitle="Planes recurrentes y suscripciones de clientes (hosting, correo, etc.)."
      basePath="/subscriptions"
      defaultPath="/subscriptions/plans"
      tabsAriaLabel="Secciones de suscripciones"
      tabs={[
        { key: 'plans', label: 'Planes', path: '/subscriptions/plans', icon: 'ph-package', visible: true },
        {
          key: 'customer-subscriptions',
          label: 'Suscripciones de clientes',
          path: '/subscriptions/customer-subscriptions',
          icon: 'ph-arrows-clockwise',
          visible: true,
        },
      ]}
    />
  );
};
