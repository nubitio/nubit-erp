import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const TaxesPage = lazyNamed(() => import('./TaxesPage'), 'TaxesPage');

export const taxesRoutes: AppRouteDefinition[] = [
  { path: '/taxes', element: TaxesPage },
];
