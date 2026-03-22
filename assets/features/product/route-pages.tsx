import { lazyNamed } from '@app/router/lazy';

export const ProductPage = lazyNamed(() => import('./pages'), 'ProductPage');
