import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const TableMapPage = lazyNamed(() => import('./TableMapPage'), 'TableMapPage');
const OrderPage = lazyNamed(() => import('./OrderPage'), 'OrderPage');
const KitchenDisplayPage = lazyNamed(() => import('./KitchenDisplayPage'), 'KitchenDisplayPage');
const RestaurantAreasAdminPage = lazyNamed(() => import('./RestaurantAreasAdminPage'), 'RestaurantAreasAdminPage');
const TablesAdminPage = lazyNamed(() => import('./TablesAdminPage'), 'TablesAdminPage');
const FloorPlanEditorPage = lazyNamed(() => import('./FloorPlanEditorPage'), 'FloorPlanEditorPage');

// Restaurant vertical (NB-18) — mesas, comanda y cocina sobre datos reales
// (src/Restaurant). Data lives in the app-wide React Query cache (see
// api/useRestaurantData.ts), so these routes don't need a shared provider.
export const restaurantRoutes: AppRouteDefinition[] = [
  { path: '/restaurant/mesas', element: TableMapPage },
  { path: '/restaurant/mesa/:tableId', element: OrderPage },
  { path: '/restaurant/kds', element: KitchenDisplayPage },
  { path: '/restaurant/admin/ambientes', element: RestaurantAreasAdminPage },
  { path: '/restaurant/admin/mesas', element: TablesAdminPage },
  { path: '/restaurant/admin/plano', element: FloorPlanEditorPage },
];
