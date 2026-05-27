import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';

const DashboardPage = lazyNamed(() => import('./DashboardPage'), 'DashboardPage');
const ExchangeRatePage = lazyNamed(() => import('./ExchangeRatePage'), 'ExchangeRatePage');
const BranchPage = lazyNamed(() => import('./BranchPage'), 'BranchPage');
const CompanyPage = lazyNamed(() => import('./CompanyPage'), 'CompanyPage');
const ConfigPage = lazyNamed(() => import('./ConfigPage'), 'ConfigPage');
const RolePage = lazyNamed(() => import('./RolePage'), 'RolePage');
const UserPage = lazyNamed(() => import('./UserPage'), 'UserPage');

export const adminRoutes: AppRouteDefinition[] = [
  { path: '/dashboard', element: DashboardPage },
  { path: '/branch', element: BranchPage },
  { path: '/company', element: CompanyPage },
  { path: '/config', element: ConfigPage },
  { path: '/exchange-rate', element: ExchangeRatePage },
  { path: '/role', element: RolePage },
  { path: '/user', element: UserPage },
];
