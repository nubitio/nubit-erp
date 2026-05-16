import { lazyNamed } from '@app/router/lazy';

export const CashRegisterDashboard = lazyNamed(
  () => import('@features/cashRegister/CashRegisterDashboard'),
  'CashRegisterDashboard',
);
export const CashRegisterOpen = lazyNamed(
  () => import('@features/cashRegister/CashRegisterOpen'),
  'CashRegisterOpen',
);
export const CashRegisterClose = lazyNamed(
  () => import('@features/cashRegister/CashRegisterClose'),
  'CashRegisterClose',
);
export const CashMovements = lazyNamed(
  () => import('@features/cashRegister/CashMovements'),
  'CashMovements',
);
export const CashRegisterPage = lazyNamed(
  () => import('@features/cashRegister/CashRegisterPage'),
  'CashRegisterPage',
);
export const CashMovementCategoryPage = lazyNamed(
  () => import('@features/cashRegister/CashMovementCategoryPage'),
  'CashMovementCategoryPage',
);
export const CashRegisterShiftsPage = lazyNamed(
  () => import('@features/cashRegister/CashRegisterShiftsPage'),
  'CashRegisterShiftsPage',
);
