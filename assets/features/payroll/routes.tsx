import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const EmployeePage = lazyNamed(() => import('./EmployeePage'), 'EmployeePage');
const AfpRatePage = lazyNamed(() => import('./AfpRatePage'), 'AfpRatePage');
const PayrollRunPage = lazyNamed(() => import('./PayrollRunPage'), 'PayrollRunPage');
const PayrollRunDetailPage = lazyNamed(() => import('./PayrollRunDetailPage'), 'PayrollRunDetailPage');

export const payrollRoutes: AppRouteDefinition[] = [
  { path: '/employees', element: EmployeePage },
  { path: '/afp-rates', element: AfpRatePage },
  { path: '/payroll-runs', element: PayrollRunPage },
  { path: '/payroll-runs/:id', element: PayrollRunDetailPage },
];
