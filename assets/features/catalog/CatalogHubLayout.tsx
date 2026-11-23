import { FeatureHubLayout } from '@nubitio/admin';
import { Outlet, useMatch } from 'react-router-dom';

export const CatalogHubLayout = () => {
  const productForm = useMatch('/catalog/products/:id');

  if (productForm) return <Outlet />;

  return (
    <FeatureHubLayout
      density="compact"
      className="hub-in-sidebar"
      title="Catálogo"
      subtitle="Productos, categorías, unidades, precios y proveedores."
      basePath="/catalog"
      defaultPath="/catalog/products"
      tabsAriaLabel="Secciones de catálogo"
      tabs={[
        {
          key: 'products',
          label: 'Productos',
          path: '/catalog/products',
          icon: 'ph-cube',
          visible: true,
        },
        {
          key: 'categories',
          label: 'Categorías',
          path: '/catalog/categories',
          icon: 'ph-folders',
          visible: true,
        },
        {
          key: 'units',
          label: 'Unidades',
          path: '/catalog/units',
          icon: 'ph-ruler',
          visible: true,
        },
        {
          key: 'prices',
          label: 'Precios',
          path: '/catalog/prices',
          icon: 'ph-tag',
          visible: true,
        },
        {
          key: 'suppliers',
          label: 'Proveedores',
          path: '/catalog/suppliers',
          icon: 'ph-truck',
          visible: true,
        },
        {
          key: 'payment-methods',
          label: 'Métodos de pago',
          path: '/catalog/payment-methods',
          icon: 'ph-credit-card',
          visible: true,
        },
      ]}
    />
  );
};
