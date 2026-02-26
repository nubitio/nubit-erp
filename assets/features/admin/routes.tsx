import type { AppRouteDefinition } from '@app/router/types';
import { lazyNamed } from '@app/router/lazy';

const DashboardPage = lazyNamed(() => import('@pages/Dashboard/DashboardPage'), 'DashboardPage');
const BranchPage = lazyNamed(() => import('@pages/Branch/BranchPage'), 'BranchPage');
const CompanyPage = lazyNamed(() => import('@pages/Company/CompanyPage'), 'CompanyPage');
const ConfigPage = lazyNamed(() => import('@pages/Config/ConfigPage'), 'ConfigPage');
const DepartmentPage = lazyNamed(() => import('@pages/Department/DepartmentPage'), 'DepartmentPage');
const EmployeePage = lazyNamed(() => import('@pages/Employee/EmployeePage'), 'EmployeePage');
const PositionPage = lazyNamed(() => import('@pages/Position/PositionPage'), 'PositionPage');
const RolePage = lazyNamed(() => import('@pages/Role/RolePage'), 'RolePage');
const UserPage = lazyNamed(() => import('@pages/User/UserPage'), 'UserPage');

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
