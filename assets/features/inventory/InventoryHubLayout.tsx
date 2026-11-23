import { FeatureHubLayout } from '@nubitio/admin';
import { useTenantRuntimeConfig } from '@shared/hooks';

export const InventoryHubLayout = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();

  const showWarehouses = runtimeConfig?.ui.inventory.showWarehouseManagement ?? false;
  const showTransfers = runtimeConfig?.ui.inventory.showTransfers ?? false;

  return (
    <FeatureHubLayout
      density="compact"
      className="hub-in-sidebar"
      title="Inventario"
      subtitle="Qué hay, qué falta y qué se movió. El kardex es la fuente de verdad."
      basePath="/inventory"
      defaultPath="/inventory/stock"
      tabsAriaLabel="Secciones de inventario"
      tabs={[
        {
          key: 'stock',
          label: 'Existencias',
          path: '/inventory/stock',
          icon: 'ph-package',
          visible: true,
        },
        {
          key: 'movements',
          label: 'Movimientos',
          path: '/inventory/movements',
          icon: 'ph-list-magnifying-glass',
          visible: true,
        },
        {
          key: 'operations',
          label: 'Operaciones',
          path: '/inventory/operations',
          icon: 'ph-arrows-left-right',
          visible: true,
        },
        {
          key: 'reorder',
          label: 'Reorden',
          path: '/inventory/reorder',
          icon: 'ph-warning-circle',
          visible: true,
        },
        {
          key: 'warehouses',
          label: 'Almacenes',
          path: '/inventory/warehouses',
          icon: 'ph-warehouse',
          visible: showWarehouses,
        },
      ]}
      banner={
        showTransfers
          ? undefined
          : {
              visible: true,
              tone: 'info',
              message:
                'Las transferencias entre almacenes están disponibles con el plan multi-almacén.',
            }
      }
    />
  );
};
