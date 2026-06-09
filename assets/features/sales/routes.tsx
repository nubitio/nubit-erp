import { lazyNamed } from '@app/router/lazy';
import { crudRoute } from '@app/router/crudRoute';
import type { AppRouteDefinition } from '@app/router/types';

const SalePage = lazyNamed(() => import('./SalePage'), 'SalePage');
const InvoicePage = lazyNamed(() => import('./InvoicePage'), 'InvoicePage');
const CreditNotePage = lazyNamed(() => import('./CreditNotePage'), 'CreditNotePage');
const SaleConsultPage = lazyNamed(() => import('./SaleConsultPage'), 'SaleConsultPage');
const InboundPage = lazyNamed(() => import('./InboundPage'), 'InboundPage');
const PurchasesPage = lazyNamed(() => import('./InboundPage'), 'PurchasesPage');
const TransferPage = lazyNamed(
  () => import('@features/stockEntry/StockEntryPage'),
  'TransferPage',
);
const OutboundPage = lazyNamed(
  () => import('@features/stockEntry/StockEntryPage'),
  'IssuePage',
);

export const salesRoutes: AppRouteDefinition[] = [
  { path: '/sale', element: SalePage },
  ...crudRoute('/invoice', InvoicePage),
  ...crudRoute('/credit-note', CreditNotePage),
  { path: '/sale-consult', element: SaleConsultPage },
  ...crudRoute('/purchases', PurchasesPage),
  ...crudRoute('/inbound', InboundPage),
  ...crudRoute('/transfer', TransferPage),
  ...crudRoute('/outbound', OutboundPage),
];
