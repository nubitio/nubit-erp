import { lazyNamed } from '@app/router/lazy';

export const PurchasesPage = lazyNamed(() => import('./pages'), 'PurchasesPage');
export const InboundPage = lazyNamed(() => import('./pages'), 'InboundPage');
