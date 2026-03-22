import type { AppRouteDefinition } from '@app/router/types';
import { SalePage } from './route-pages';

export const saleRoutes: AppRouteDefinition[] = [{ path: '/sale', element: SalePage }];
