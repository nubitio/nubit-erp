import { lazyNamed } from '@app/router/lazy';

export const TransferPage = lazyNamed(() => import('./pages'), 'TransferPage');
