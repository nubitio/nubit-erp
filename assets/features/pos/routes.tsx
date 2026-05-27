import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const POSPage = lazyNamed(() => import('./POSPage'), 'POSPage');

export const posRoutes: AppRouteDefinition[] = [{ path: '/pos', element: POSPage }];
