import { FeatureHubLayout } from '@nubitio/admin';
import { hasPermission } from '@shared/permissions';

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
        { key: 'plans', label: 'Planes', path: '/subscriptions/plans', icon: 'ph-package', visible: hasPermission('subscriptions.view') },
        {
          key: 'customer-subscriptions',
          label: 'Suscripciones de clientes',
          path: '/subscriptions/customer-subscriptions',
          icon: 'ph-arrows-clockwise',
          visible: hasPermission('subscriptions.view'),
        },
        {
          key: 'runs',
          label: 'Emisiones',
          path: '/subscriptions/runs',
          icon: 'ph-receipt',
          visible: hasPermission('subscriptions.view'),
        },
        {
          key: 'lines',
          label: 'Líneas',
          path: '/subscriptions/lines',
          icon: 'ph-list-plus',
          visible: hasPermission('subscriptions.view'),
        },
      ]}
    />
  );
};
