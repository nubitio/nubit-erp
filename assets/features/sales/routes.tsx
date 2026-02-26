import type { AppRouteDefinition } from '@app/router/types';
import {
  CreditNotePage,
  InboundPage,
  InvoicePage,
  KardexPage,
  OutboundPage,
  SaleConsultPage,
  SalePage,
  TransferPage,
} from './route-pages';

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
