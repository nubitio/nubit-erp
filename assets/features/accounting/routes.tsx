import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const AccountingPage = lazyNamed(() => import('./AccountingPage'), 'AccountingPage');

export const accountingRoutes: AppRouteDefinition[] = [
  { path: '/accounting', element: AccountingPage },
];
