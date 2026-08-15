import type { AppRouteDefinition } from '@app/router/types';
import { accountsPayableRoutes } from '@features/accountsPayable/routes';
import { adminRoutes } from '@features/admin/routes';
import { bankReconciliationRoutes } from '@features/bankReconciliation/routes';
import { billingRoutes } from '@features/billing/routes';
import { cashRegisterRoutes } from '@features/cashRegister/routes';
import { catalogRoutes } from '@features/catalog/routes';
import { inventoryRoutes } from '@features/inventory/routes';
import { posRoutes } from '@features/pos/routes';
import { purchasingRoutes } from '@features/purchasing/routes';
import { salesRoutes } from '@features/sales/routes';
import { salesQuotesRoutes } from '@features/salesQuotes/routes';
import { subscriptionsRoutes } from '@features/subscriptions/routes';
import { taxesRoutes } from '@features/taxes/routes';

export const appRoutes: AppRouteDefinition[] = [
  ...accountsPayableRoutes,
  ...adminRoutes,
  ...bankReconciliationRoutes,
  ...billingRoutes,
  ...cashRegisterRoutes,
  ...catalogRoutes,
  ...inventoryRoutes,
  ...posRoutes,
  ...purchasingRoutes,
  ...salesRoutes,
  ...salesQuotesRoutes,
  ...subscriptionsRoutes,
  ...taxesRoutes,
];
