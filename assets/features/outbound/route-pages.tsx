import { lazyNamed } from '@app/router/lazy';

export const OutboundPage = lazyNamed(() => import('./pages'), 'OutboundPage');
