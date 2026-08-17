import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const ExpenseReportPage = lazyNamed(() => import('./ExpenseReportPage'), 'ExpenseReportPage');

export const expenseReportsRoutes: AppRouteDefinition[] = [
  { path: '/expense-reports', element: ExpenseReportPage },
];
