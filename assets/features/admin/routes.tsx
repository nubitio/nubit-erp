import type { AppRouteDefinition } from '@app/router/types';
import {
  BranchPage,
  CompanyPage,
  ConfigPage,
  DashboardPage,
  ExchangeRatePage,
  RolePage,
  UserPage,
} from './route-pages';

export const adminRoutes: AppRouteDefinition[] = [
  { path: '/dashboard', element: DashboardPage },
  { path: '/branch', element: BranchPage },
  { path: '/company', element: CompanyPage },
  { path: '/config', element: ConfigPage },
  { path: '/exchange-rate', element: ExchangeRatePage },
  { path: '/role', element: RolePage },
  { path: '/user', element: UserPage },
];
