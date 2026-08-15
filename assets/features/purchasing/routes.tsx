import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const PurchaseOrderPage = lazyNamed(() => import('./PurchaseOrderPage'), 'PurchaseOrderPage');
const ReceiveAgainstPoPage = lazyNamed(
  () => import('./ReceiveAgainstPoPage'),
  'ReceiveAgainstPoPage',
);

export const purchasingRoutes: AppRouteDefinition[] = [
  { path: '/purchase-orders', element: PurchaseOrderPage },
  { path: '/purchase-orders/:id/receive', element: ReceiveAgainstPoPage },
];
