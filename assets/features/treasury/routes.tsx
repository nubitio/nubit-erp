import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const TreasuryPage = lazyNamed(() => import('./TreasuryPage'), 'TreasuryPage');
const FinanceSummaryPage = lazyNamed(
  () => import('./summary/FinanceSummaryPage'),
  'FinanceSummaryPage',
);

export const treasuryRoutes: AppRouteDefinition[] = [
  { path: '/treasury', element: TreasuryPage },
  { path: '/finance-summary', element: FinanceSummaryPage },
];
