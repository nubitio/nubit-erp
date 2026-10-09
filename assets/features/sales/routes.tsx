import { lazyNamed } from '@app/router/lazy';
import { crudRoute } from '@app/router/crudRoute';
import type { AppRouteDefinition } from '@app/router/types';

const InboundPage = lazyNamed(() => import('./InboundPage'), 'InboundPage');
const PurchasesPage = lazyNamed(() => import('./InboundPage'), 'PurchasesPage');
const TransferPage = lazyNamed(() => import('@features/stockEntry/StockEntryPage'), 'TransferPage');
const OutboundPage = lazyNamed(() => import('@features/stockEntry/StockEntryPage'), 'IssuePage');

export const salesRoutes: AppRouteDefinition[] = [
  ...crudRoute('/purchases', PurchasesPage),
  ...crudRoute('/inbound', InboundPage),
  ...crudRoute('/transfer', TransferPage),
  ...crudRoute('/outbound', OutboundPage),
];
