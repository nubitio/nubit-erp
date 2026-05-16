import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const DashboardPage = lazyNamed(() => import('./pages/DashboardPage'), 'DashboardPage');
const ExchangeRatePage = lazyNamed(() => import('./pages/ExchangeRatePage'), 'ExchangeRatePage');
const BranchPage = lazyNamed(() => import('./pages/BranchPage'), 'BranchPage');
const CompanyPage = lazyNamed(() => import('./pages/CompanyPage'), 'CompanyPage');
const ConfigPage = lazyNamed(() => import('./pages/ConfigPage'), 'ConfigPage');
const RolePage = lazyNamed(() => import('./pages/RolePage'), 'RolePage');
const UserPage = lazyNamed(() => import('./pages/UserPage'), 'UserPage');

export const adminRoutes: AppRouteDefinition[] = [
  { path: '/dashboard', element: DashboardPage },
  { path: '/branch', element: BranchPage },
  { path: '/company', element: CompanyPage },
  { path: '/config', element: ConfigPage },
  { path: '/exchange-rate', element: ExchangeRatePage },
  { path: '/role', element: RolePage },
  { path: '/user', element: UserPage },
];
