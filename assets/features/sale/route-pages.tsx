import { lazyNamed } from '@app/router/lazy';

export const SalePage = lazyNamed(() => import('./pages'), 'SalePage');
