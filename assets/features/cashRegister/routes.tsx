import { Navigate, useParams } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { CashRegisterHubLayout } from './CashRegisterHubLayout';

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

// Legacy standalone paths: kept as redirects so old bookmarks/links still resolve.
const RegistersRedirect = () => <Navigate to="/cashbox/registers" replace />;
const CategoriesRedirect = () => <Navigate to="/cashbox/categories" replace />;
const ShiftMovementsRedirect = () => {
  const { id } = useParams();
  return <Navigate to={`/cash-session/${id}/movements`} replace />;
};

export const cashRegisterRoutes: AppRouteDefinition[] = [
  { path: '/cash-register', element: RegistersRedirect },
  { path: '/cash-movement-category', element: CategoriesRedirect },
  { path: '/cash-register/:id/open', element: CashRegisterOpen },
  { path: '/cash-session/:id/close', element: CashRegisterClose },
  { path: '/cash-register/:id/close', element: CashRegisterClose },
  { path: '/cash-session/:id/movements', element: CashMovements },
  { path: '/cash-shift/:id/movements', element: ShiftMovementsRedirect },
  { path: '/cash-register/:id/shifts', element: CashRegisterShiftsPage },
  {
    path: '/cashbox',
    element: CashRegisterHubLayout,
    children: [
      { path: 'dashboard', element: CashRegisterDashboard },
      { path: 'registers', element: CashRegisterPage },
      { path: 'categories', element: CashMovementCategoryPage },
    ],
  },
];
