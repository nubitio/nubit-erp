import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { crudRoute } from '@app/router/crudRoute';

const ProductPage = lazyNamed(() => import('./ProductPage'), 'ProductPage');

export const productRoutes: AppRouteDefinition[] = crudRoute('/product', ProductPage);
