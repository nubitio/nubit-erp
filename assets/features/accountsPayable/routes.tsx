import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const SupplierBillPage = lazyNamed(() => import('./SupplierBillPage'), 'SupplierBillPage');
const SupplierBillPay = lazyNamed(() => import('./SupplierBillPay'), 'SupplierBillPay');
const ExpenseCategoryPage = lazyNamed(() => import('./ExpenseCategoryPage'), 'ExpenseCategoryPage');

export const accountsPayableRoutes: AppRouteDefinition[] = [
  { path: '/accounts-payable', element: SupplierBillPage },
  { path: '/supplier-bill/:id/pay', element: SupplierBillPay },
  { path: '/expense-category', element: ExpenseCategoryPage },
];
