import type { AppRouteDefinition } from '@app/router/types';
import { CustomerPage } from './route-pages';

export const customerRoutes: AppRouteDefinition[] = [{ path: '/customer', element: CustomerPage }];
