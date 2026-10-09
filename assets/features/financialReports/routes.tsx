import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const FinancialReportsPage = lazyNamed(
  () => import('./FinancialReportsPage'),
  'FinancialReportsPage',
);

export const financialReportsRoutes: AppRouteDefinition[] = [
  { path: '/financial-reports', element: FinancialReportsPage },
];
