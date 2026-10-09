import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { InventoryHubLayout } from './InventoryHubLayout';
import { useTenantRuntimeConfig } from '@shared/hooks';

const StockItemPage = lazyNamed(() => import('@features/catalog/StockItemPage'), 'StockItemPage');
const KardexPage = lazyNamed(() => import('@features/kardex/KardexPage'), 'KardexPage');
const ReorderPage = lazyNamed(() => import('@features/reorder/ReorderPage'), 'ReorderPage');
const WarehousePage = lazyNamed(() => import('@features/catalog/WarehousePage'), 'WarehousePage');
const InventoryOperationsPage = lazyNamed(
  () => import('./InventoryOperationsPage'),
  'InventoryOperationsPage',
);
const CycleCountPage = lazyNamed(() => import('./CycleCountPage'), 'CycleCountPage');

const WarehouseRouteGuard = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  if (runtimeConfig?.ui.inventory.showWarehouseManagement === false) {
    return <Navigate to="/inventory/stock" replace />;
  }
  return <WarehousePage />;
};

export const inventoryRoutes: AppRouteDefinition[] = [
  {
    path: '/inventory',
    element: InventoryHubLayout,
    children: [
      { path: 'stock', element: StockItemPage },
      { path: 'movements', element: KardexPage },
      { path: 'operations', element: InventoryOperationsPage },
      { path: 'cycle-count', element: CycleCountPage },
      { path: 'reorder', element: ReorderPage },
      { path: 'warehouses', element: WarehouseRouteGuard },
    ],
  },
];
