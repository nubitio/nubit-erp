import { withNavigationWatcher } from './contexts/navigation';
import type { AppRouteDefinition } from '@app/router/types';
import { adminRoutes } from '@features/admin/routes';
import { cashRegisterRoutes } from '@features/cashRegister/routes';
import { catalogRoutes } from '@features/catalog/routes';
import { salesRoutes } from '@features/sales/routes';

const routes: AppRouteDefinition[] = [
  ...adminRoutes,
  ...cashRegisterRoutes,
  ...catalogRoutes,
  ...salesRoutes,
];

export const appRoutes = routes.map((route) => ({
  ...route,
  element: withNavigationWatcher(route.element, route.path),
}));
