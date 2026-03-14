import type { AppRouteDefinition } from '@app/router/types';
import {
  CreditNotePage,
  InboundPage,
  InvoicePage,
  OutboundPage,
  PurchasesPage,
  SaleConsultPage,
  SalePage,
  TransferPage,
} from './route-pages';

export const salesRoutes: AppRouteDefinition[] = [
  { path: '/credit-note', element: CreditNotePage },
  { path: '/purchases', element: PurchasesPage },
  // Deprecated alias kept for backward compatibility.
  { path: '/inbound', element: InboundPage },
  { path: '/invoice', element: InvoicePage },
  { path: '/outbound', element: OutboundPage },
  { path: '/sale', element: SalePage },
  { path: '/sale-consult', element: SaleConsultPage },
  { path: '/transfer', element: TransferPage },
];
