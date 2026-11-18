import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const CustomersPage = lazyNamed(() => import('./CustomersPage'), 'CustomersPage');

export const customerRoutes: AppRouteDefinition[] = [{ path: '/customer', element: CustomersPage }];
