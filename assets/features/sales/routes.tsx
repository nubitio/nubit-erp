import type { AppRouteDefinition } from '@app/router/types';
import { invoiceRoutes } from '@features/invoice/routes';
import { outboundRoutes } from '@features/outbound/routes';
import { purchasesRoutes } from '@features/purchases/routes';
import { saleRoutes } from '@features/sale/routes';
import { transferRoutes } from '@features/transfer/routes';
import { CreditNotePage, SaleConsultPage } from './route-pages';

export const salesRoutes: AppRouteDefinition[] = [
  ...saleRoutes,
  ...invoiceRoutes,
  ...outboundRoutes,
  ...transferRoutes,
  ...purchasesRoutes,
  { path: '/credit-note', element: CreditNotePage },
  { path: '/sale-consult', element: SaleConsultPage },
];
