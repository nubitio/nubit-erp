import { lazyNamed } from '@app/router/lazy';

export const POSPage = lazyNamed(() => import('@features/pos/pages/POSPage'), 'POSPage');
