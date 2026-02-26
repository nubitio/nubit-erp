import type { AppRouteDefinition } from '@app/router/types';
import { lazyNamed } from '@app/router/lazy';

const CreditNotePage = lazyNamed(() => import('@pages/CreditNote/CreditNotePage'), 'CreditNotePage');
const InboundPage = lazyNamed(() => import('@pages/Inbound/InboundPage'), 'InboundPage');
const InvoicePage = lazyNamed(() => import('@pages/Invoice/InvoicePage'), 'InvoicePage');
const KardexPage = lazyNamed(() => import('@pages/Kardex/KardexPage'), 'KardexPage');
const OutboundPage = lazyNamed(() => import('@pages/Outbound/OutboundPage'), 'OutboundPage');
const SalePage = lazyNamed(() => import('@pages/Sale/SalePage'), 'SalePage');
const SaleConsultPage = lazyNamed(() => import('@pages/SaleConsult/SaleConsultPage'), 'SaleConsultPage');
const TransferPage = lazyNamed(() => import('@pages/Transfer/TransferPage'), 'TransferPage');

export const salesRoutes: AppRouteDefinition[] = [
  { path: '/credit-note', element: CreditNotePage },
  { path: '/inbound', element: InboundPage },
  { path: '/invoice', element: InvoicePage },
  { path: '/kardex', element: KardexPage },
  { path: '/outbound', element: OutboundPage },
  { path: '/sale', element: SalePage },
  { path: '/sale-consult', element: SaleConsultPage },
  { path: '/transfer', element: TransferPage },
];
