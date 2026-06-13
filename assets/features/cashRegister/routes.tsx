import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const CashRegisterDashboard = lazyNamed(
  () => import('./CashRegisterDashboard'),
  'CashRegisterDashboard',
);
const CashRegisterOpen = lazyNamed(() => import('./CashRegisterOpen'), 'CashRegisterOpen');
const CashRegisterClose = lazyNamed(() => import('./CashRegisterClose'), 'CashRegisterClose');
const CashMovements = lazyNamed(() => import('./CashMovements'), 'CashMovements');
const CashRegisterPage = lazyNamed(() => import('./CashRegisterPage'), 'CashRegisterPage');
const CashMovementCategoryPage = lazyNamed(
  () => import('./CashMovementCategoryPage'),
  'CashMovementCategoryPage',
);
const CashRegisterShiftsPage = lazyNamed(
  () => import('./CashRegisterShiftsPage'),
  'CashRegisterShiftsPage',
);

export const cashRegisterRoutes: AppRouteDefinition[] = [
  { path: '/cashbox', element: CashRegisterDashboard },
  { path: '/cash-register', element: CashRegisterPage },
  { path: '/cash-register/:id/open', element: CashRegisterOpen },
  { path: '/cash-session/:id/close', element: CashRegisterClose },
  { path: '/cash-register/:id/close', element: CashRegisterClose },
  { path: '/cash-session/:id/movements', element: CashMovements },
  { path: '/cash-shift/:id/movements', element: CashMovements },
  { path: '/cash-register/:id/shifts', element: CashRegisterShiftsPage },
  { path: '/cash-movement-category', element: CashMovementCategoryPage },
];
