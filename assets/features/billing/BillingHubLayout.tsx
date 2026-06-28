import { FeatureHubLayout } from '@nubitio/admin';

export const BillingHubLayout = () => (
  <FeatureHubLayout
    density="compact"
    title="Facturación"
    subtitle="Ventas, consultas, comprobantes y notas de crédito en un solo flujo."
    basePath="/billing"
    defaultPath="/billing/sales"
    tabsAriaLabel="Secciones de facturación"
    tabs={[
      { key: 'sales', label: 'Ventas', path: '/billing/sales', icon: 'ph-shopping-cart', visible: true },
      { key: 'consult', label: 'Consultas', path: '/billing/consult', icon: 'ph-chart-line-up', visible: true },
      { key: 'documents', label: 'Comprobantes', path: '/billing/documents', icon: 'ph-file-text', visible: true },
      { key: 'credit-notes', label: 'Notas de crédito', path: '/billing/credit-notes', icon: 'ph-file-minus', visible: true },
    ]}
  />
);
