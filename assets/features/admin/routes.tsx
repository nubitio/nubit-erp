import type { AppRouteDefinition } from '@app/router/types';
import {
  BranchPage,
  CompanyPage,
  ConfigPage,
  DashboardPage,
  DepartmentPage,
  EmployeePage,
  PositionPage,
  RolePage,
  UserPage,
} from './route-pages';

export const adminRoutes: AppRouteDefinition[] = [
  { path: '/dashboard', element: DashboardPage },
  { path: '/branch', element: BranchPage },
  { path: '/company', element: CompanyPage },
  { path: '/config', element: ConfigPage },
  { path: '/department', element: DepartmentPage },
  { path: '/employee', element: EmployeePage },
  { path: '/position', element: PositionPage },
  { path: '/role', element: RolePage },
  { path: '/user', element: UserPage },
];
