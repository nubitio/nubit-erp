import type { AppRouteDefinition } from '@app/router/types';
import { POSPage } from './route-pages';

export const posRoutes: AppRouteDefinition[] = [{ path: '/pos', element: POSPage }];
