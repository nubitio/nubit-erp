import type { AppRouteDefinition } from '@app/router/types';
import { accountingRoutes } from '@features/accounting/routes';
import { accountsPayableRoutes } from '@features/accountsPayable/routes';
import { adminRoutes } from '@features/admin/routes';
import { bankReconciliationRoutes } from '@features/bankReconciliation/routes';
import { treasuryRoutes } from '@features/treasury/routes';
import { billingRoutes } from '@features/billing/routes';
import { cashRegisterRoutes } from '@features/cashRegister/routes';
import { catalogRoutes } from '@features/catalog/routes';
import { expenseReportsRoutes } from '@features/expenseReports/routes';
import { financialReportsRoutes } from '@features/financialReports/routes';
import { fixedAssetsRoutes } from '@features/fixedAssets/routes';
import { inventoryRoutes } from '@features/inventory/routes';
import { payrollRoutes } from '@features/payroll/routes';
import { posRoutes } from '@features/pos/routes';
import { purchasingRoutes } from '@features/purchasing/routes';
import { restaurantRoutes } from '@features/restaurant/routes';
import { salesRoutes } from '@features/sales/routes';
import { salesQuotesRoutes } from '@features/salesQuotes/routes';
import { subscriptionsRoutes } from '@features/subscriptions/routes';
import { taxesRoutes } from '@features/taxes/routes';

export const appRoutes: AppRouteDefinition[] = [
  ...accountingRoutes,
  ...accountsPayableRoutes,
  ...adminRoutes,
  ...bankReconciliationRoutes,
  ...treasuryRoutes,
  ...billingRoutes,
  ...cashRegisterRoutes,
  ...catalogRoutes,
  ...expenseReportsRoutes,
  ...financialReportsRoutes,
  ...fixedAssetsRoutes,
  ...inventoryRoutes,
  ...payrollRoutes,
  ...posRoutes,
  ...purchasingRoutes,
  ...restaurantRoutes,
  ...salesRoutes,
  ...salesQuotesRoutes,
  ...subscriptionsRoutes,
  ...taxesRoutes,
];
