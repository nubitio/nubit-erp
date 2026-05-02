import { lazyNamed } from '@app/router/lazy';

export const OutboundPage = lazyNamed(
  () => import('@features/stockEntry/pages/StockEntryPage'),
  'IssuePage',
);
