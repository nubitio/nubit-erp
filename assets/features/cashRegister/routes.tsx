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
const CashShiftDetailPage = lazyNamed(() => import('./CashShiftDetailPage'), 'CashShiftDetailPage');
const CashRegisterPage = lazyNamed(() => import('./CashRegisterPage'), 'CashRegisterPage');
const CashRegisterShiftsHistory = lazyNamed(
  () => import('./CashRegisterShiftsHistory'),
  'CashRegisterShiftsHistory',
);
const TeamControlPage = lazyNamed(() => import('./teamControl/TeamControlPage'), 'TeamControlPage');

// Legacy standalone paths: kept as redirects so old bookmarks/links still resolve.
const RegistersRedirect = () => <Navigate to="/cashbox/registers" replace />;
const CategoriesRedirect = () => <Navigate to="/cashbox/registers" replace />;
const RegisterShiftsRedirect = () => {
  const { id } = useParams();
  return <Navigate to={`/cashbox/shifts?caja=${id}`} replace />;
};
const ShiftMovementsRedirect = () => {
  const { id } = useParams();
  return <Navigate to={`/cash-session/${id}/movements`} replace />;
};

export const cashRegisterRoutes: AppRouteDefinition[] = [
  { path: '/cash-register', element: RegistersRedirect },
  { path: '/cash-movement-category', element: CategoriesRedirect },
  { path: '/cashbox/categories', element: CategoriesRedirect },
  { path: '/cash-register/:id/open', element: CashRegisterOpen },
  { path: '/cash-session/:id/close', element: CashRegisterClose },
  { path: '/cash-register/:id/close', element: CashRegisterClose },
  { path: '/cash-session/:id/movements', element: CashShiftDetailPage },
  { path: '/cash-shift/:id/movements', element: ShiftMovementsRedirect },
  { path: '/cash-register/:id/shifts', element: RegisterShiftsRedirect },
  {
    path: '/cashbox',
    element: CashRegisterHubLayout,
    children: [
      { path: 'dashboard', element: CashRegisterDashboard },
      { path: 'shifts', element: CashRegisterShiftsHistory },
      { path: 'registers', element: CashRegisterPage },
      { path: 'control', element: TeamControlPage },
    ],
  },
];
