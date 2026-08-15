import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const SaleQuotePage = lazyNamed(() => import('./SaleQuotePage'), 'SaleQuotePage');

export const salesQuotesRoutes: AppRouteDefinition[] = [
  { path: '/sale-quotes', element: SaleQuotePage },
];
