import { lazyNamed } from '@app/router/lazy';

export const CashRegisterDashboard = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterDashboard'),
  'CashRegisterDashboard',
);
export const CashRegisterOpen = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterOpen'),
  'CashRegisterOpen',
);
export const CashRegisterClose = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterClose'),
  'CashRegisterClose',
);
export const CashMovements = lazyNamed(
  () => import('@features/cashRegister/pages/CashMovements'),
  'CashMovements',
);
export const CashRegisterPage = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterPage'),
  'CashRegisterPage',
);
export const CashMovementCategoryPage = lazyNamed(
  () => import('@features/cashRegister/pages/CashMovementCategoryPage'),
  'CashMovementCategoryPage',
);
export const CashRegisterShiftsPage = lazyNamed(
  () => import('@features/cashRegister/pages/CashRegisterShiftsPage'),
  'CashRegisterShiftsPage',
);
