import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';

export const CatalogHubLayout = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  const navigationTier = runtimeConfig?.profile.navigationTier ?? 'basic';
  const showConfigurationTabs = navigationTier === 'standard' || navigationTier === 'advanced';

  return (
    <FeatureHubLayout
      density="compact"
      title="Catálogo"
      subtitle="Productos, categorías, unidades, precios y proveedores."
      basePath="/catalog"
      defaultPath="/catalog/products"
      tabsAriaLabel="Secciones de catálogo"
      tabs={[
        { key: 'products', label: 'Productos', path: '/catalog/products', icon: 'ph-cube', visible: true },
        {
          key: 'categories',
          label: 'Categorías',
          path: '/catalog/categories',
          icon: 'ph-folders',
          visible: showConfigurationTabs,
        },
        {
          key: 'units',
          label: 'Unidades',
          path: '/catalog/units',
          icon: 'ph-ruler',
          visible: showConfigurationTabs,
        },
        {
          key: 'prices',
          label: 'Precios',
          path: '/catalog/prices',
          icon: 'ph-tag',
          visible: showConfigurationTabs,
        },
        {
          key: 'suppliers',
          label: 'Proveedores',
          path: '/catalog/suppliers',
          icon: 'ph-truck',
          visible: showConfigurationTabs,
        },
        {
          key: 'payment-methods',
          label: 'Métodos de pago',
          path: '/catalog/payment-methods',
          icon: 'ph-credit-card',
          visible: showConfigurationTabs,
        },
      ]}
    />
  );
};
