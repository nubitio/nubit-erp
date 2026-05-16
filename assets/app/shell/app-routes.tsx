import type { AppRouteDefinition } from '@app/router/types';
import { adminRoutes } from '@features/admin/routes';
import { cashRegisterRoutes } from '@features/cashRegister/routes';
import { catalogRoutes } from '@features/catalog/routes';
import { posRoutes } from '@features/pos/routes';
import { salesRoutes } from '@features/sales/routes';

export const appRoutes: AppRouteDefinition[] = [
  ...adminRoutes,
  ...cashRegisterRoutes,
  ...catalogRoutes,
  ...posRoutes,
  ...salesRoutes,
];
