import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { settingsRoutes } from '@features/settings';

const DashboardPage = lazyNamed(() => import('./DashboardPage'), 'DashboardPage');
const ExcelImportPage = lazyNamed(() => import('@features/setup/ExcelImportPage'), 'ExcelImportPage');
const ExchangeRatePage = lazyNamed(() => import('./ExchangeRatePage'), 'ExchangeRatePage');
const CompanyPage = lazyNamed(() => import('./CompanyPage'), 'CompanyPage');
const PleExportPage = lazyNamed(() => import('./PleExportPage'), 'PleExportPage');
const ReportsRedirect = () => <Navigate to="/dashboard" replace />;

export const adminRoutes: AppRouteDefinition[] = [
  { path: '/dashboard', element: DashboardPage },
  { path: '/setup/import', element: ExcelImportPage },
  { path: '/reports', element: ReportsRedirect },
  { path: '/ple', element: PleExportPage },
  { path: '/company', element: CompanyPage },
  { path: '/exchange-rate', element: ExchangeRatePage },
  ...settingsRoutes,
];