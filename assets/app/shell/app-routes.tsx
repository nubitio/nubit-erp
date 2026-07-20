import type { AppRouteDefinition } from '@app/router/types';
import { adminRoutes } from '@features/admin/routes';
import { billingRoutes } from '@features/billing/routes';
import { cashRegisterRoutes } from '@features/cashRegister/routes';
import { catalogRoutes } from '@features/catalog/routes';
import { inventoryRoutes } from '@features/inventory/routes';
import { posRoutes } from '@features/pos/routes';
import { salesRoutes } from '@features/sales/routes';
import { subscriptionsRoutes } from '@features/subscriptions/routes';
import { taxesRoutes } from '@features/taxes/routes';

export const appRoutes: AppRouteDefinition[] = [
  ...adminRoutes,
  ...billingRoutes,
  ...cashRegisterRoutes,
  ...catalogRoutes,
  ...inventoryRoutes,
  ...posRoutes,
  ...salesRoutes,
  ...subscriptionsRoutes,
  ...taxesRoutes,
];
