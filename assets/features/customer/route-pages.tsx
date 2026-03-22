import { lazyNamed } from '@app/router/lazy';

export const CustomerPage = lazyNamed(() => import('./pages'), 'CustomerPage');
