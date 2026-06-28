import { FeatureHubLayout } from '@nubitio/admin';

export const CatalogHubLayout = () => (
  <FeatureHubLayout
    density="compact"
    title="Catálogo"
    subtitle="Productos, categorías, unidades, precios, proveedores y series comerciales."
    basePath="/catalog"
    defaultPath="/catalog/products"
    tabsAriaLabel="Secciones de catálogo"
    tabs={[
      { key: 'products', label: 'Productos', path: '/catalog/products', icon: 'ph-cube', visible: true },
      { key: 'categories', label: 'Categorías', path: '/catalog/categories', icon: 'ph-folders', visible: true },
      { key: 'units', label: 'Unidades', path: '/catalog/units', icon: 'ph-ruler', visible: true },
      { key: 'prices', label: 'Precios', path: '/catalog/prices', icon: 'ph-tag', visible: true },
      { key: 'suppliers', label: 'Proveedores', path: '/catalog/suppliers', icon: 'ph-truck', visible: true },
      { key: 'series', label: 'Series', path: '/catalog/series', icon: 'ph-ticket', visible: true },
    ]}
  />
);
