import { lazyNamed } from '@app/router/lazy';

export const InvoicePage = lazyNamed(() => import('./pages'), 'InvoicePage');
