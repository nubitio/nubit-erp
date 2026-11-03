import { Outlet, useMatch } from 'react-router-dom';
import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';
import './BillingHubLayout.scss';

export const BillingHubLayout = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  const showOperationalReviewTabs = navigationTier === 'standard' || navigationTier === 'advanced';

  const saleDetail = useMatch('/billing/sales/:id');
  if (saleDetail) return <Outlet />;

  return (
    <FeatureHubLayout
      density="compact"
      className="billing-hub"
      title="Facturación"
      subtitle="Ventas, consultas, comprobantes y notas de crédito en un solo flujo."
      basePath="/billing"
      defaultPath="/billing/sales"
      tabsAriaLabel="Secciones de facturación"
      tabs={[
        {
          key: 'sales',
          label: 'Ventas',
          path: '/billing/sales',
          icon: 'ph-shopping-cart',
          visible: true,
        },
        {
          key: 'consult',
          label: 'Consultas',
          path: '/billing/consult',
          icon: 'ph-chart-line-up',
          visible: showOperationalReviewTabs,
        },
        {
          key: 'documents',
          label: 'Comprobantes',
          path: '/billing/documents',
          icon: 'ph-file-text',
          visible: true,
        },
        {
          key: 'credit-notes',
          label: 'Notas de crédito',
          path: '/billing/credit-notes',
          icon: 'ph-file-minus',
          visible: showOperationalReviewTabs,
        },
      ]}
    />
  );
};
