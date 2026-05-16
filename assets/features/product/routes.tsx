import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const ProductPage = lazyNamed(() => import('./ProductPage'), 'ProductPage');

export const productRoutes: AppRouteDefinition[] = [{ path: '/product', element: ProductPage }];
