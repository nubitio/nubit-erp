import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const CustomerPage = lazyNamed(() => import('./CustomerPage'), 'CustomerPage');

export const customerRoutes: AppRouteDefinition[] = [{ path: '/customer', element: CustomerPage }];
