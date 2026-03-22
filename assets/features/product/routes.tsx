import type { AppRouteDefinition } from '@app/router/types';
import { ProductPage } from './route-pages';

export const productRoutes: AppRouteDefinition[] = [{ path: '/product', element: ProductPage }];
