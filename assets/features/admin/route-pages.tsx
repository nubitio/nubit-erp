import { lazyNamed } from '@app/router/lazy';

export const DashboardPage = lazyNamed(
  () => import('@features/admin/pages/DashboardPage'),
  'DashboardPage',
);
export const ExchangeRatePage = lazyNamed(
  () => import('@features/admin/pages/ExchangeRatePage'),
  'ExchangeRatePage',
);
export const BranchPage = lazyNamed(() => import('@features/admin/pages/BranchPage'), 'BranchPage');
export const CompanyPage = lazyNamed(
  () => import('@features/admin/pages/CompanyPage'),
  'CompanyPage',
);
export const ConfigPage = lazyNamed(() => import('@features/admin/pages/ConfigPage'), 'ConfigPage');
export const DepartmentPage = lazyNamed(
  () => import('@features/admin/pages/DepartmentPage'),
  'DepartmentPage',
);
export const EmployeePage = lazyNamed(
  () => import('@features/admin/pages/EmployeePage'),
  'EmployeePage',
);
export const PositionPage = lazyNamed(
  () => import('@features/admin/pages/PositionPage'),
  'PositionPage',
);
export const RolePage = lazyNamed(() => import('@features/admin/pages/RolePage'), 'RolePage');
export const UserPage = lazyNamed(() => import('@features/admin/pages/UserPage'), 'UserPage');
