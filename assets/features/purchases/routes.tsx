import type { AppRouteDefinition } from '@app/router/types';
import { InboundPage, PurchasesPage } from './route-pages';

export const purchasesRoutes: AppRouteDefinition[] = [
  { path: '/purchases', element: PurchasesPage },
  { path: '/inbound', element: InboundPage },
];
