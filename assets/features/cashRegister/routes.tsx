import type { AppRouteDefinition } from '@app/router/types';
import {
  CashMovementCategoryPage,
  CashMovements,
  CashRegisterClose,
  CashRegisterDashboard,
  CashRegisterOpen,
  CashRegisterPage,
  CashRegisterShiftsPage,
} from './route-pages';

export const cashRegisterRoutes: AppRouteDefinition[] = [
  { path: '/cashbox', element: CashRegisterDashboard },
  { path: '/cash-register', element: CashRegisterPage },
  { path: '/cash-register/:id/open', element: CashRegisterOpen },
  { path: '/cash-register/:id/close', element: CashRegisterClose },
  { path: '/cash-shift/:id/movements', element: CashMovements },
  { path: '/cash-register/:id/shifts', element: CashRegisterShiftsPage },
  { path: '/cash-movement-category', element: CashMovementCategoryPage },
];
