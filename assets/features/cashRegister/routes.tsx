import type { AppRouteDefinition } from '@app/router/types';
import { lazyNamed } from '@app/router/lazy';

const CashRegisterDashboard = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterDashboard'),
  'CashRegisterDashboard',
);
const CashRegisterOpen = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterOpen'),
  'CashRegisterOpen',
);
const CashRegisterClose = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterClose'),
  'CashRegisterClose',
);
const CashMovements = lazyNamed(() => import('@features/cashRegister/pages/CashMovements'), 'CashMovements');
const CashRegisterPage = lazyNamed(() => import('@pages/CashRegister/CashRegisterPage'), 'CashRegisterPage');
const CashMovementCategoryPage = lazyNamed(
  () => import('@pages/CashMovementCategory/CashMovementCategoryPage'),
  'CashMovementCategoryPage',
);
const CashRegisterShiftsPage = lazyNamed(
  () => import('@pages/CashRegisterShifts/CashRegisterShiftsPage'),
  'CashRegisterShiftsPage',
);

export const cashRegisterRoutes: AppRouteDefinition[] = [
  { path: '/cashbox', element: CashRegisterDashboard },
  { path: '/cash-register', element: CashRegisterPage },
  { path: '/cash-register/:id/open', element: CashRegisterOpen },
  { path: '/cash-register/:id/close', element: CashRegisterClose },
  { path: '/cash-shift/:id/movements', element: CashMovements },
  { path: '/cash-register/:id/shifts', element: CashRegisterShiftsPage },
  { path: '/cash-movement-category', element: CashMovementCategoryPage },
];
