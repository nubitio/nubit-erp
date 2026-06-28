import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { SettingsHubLayout } from './SettingsHubLayout';
import { SettingsCompanyTab } from './SettingsCompanyTab';
import { SettingsSunatTab } from './SettingsSunatTab';
import { SettingsGeneralTab } from './SettingsGeneralTab';
import { SettingsCrudPanel } from './SettingsCrudPanel';

const VoucherSeriesPage = lazyNamed(() => import('@features/catalog/VoucherSeriesPage'), 'VoucherSeriesPage');
const UserPage = lazyNamed(() => import('@features/admin/UserPage'), 'UserPage');
const BranchPage = lazyNamed(() => import('@features/admin/BranchPage'), 'BranchPage');

const SettingsSeriesTab = () => (
  <SettingsCrudPanel>
    <VoucherSeriesPage />
  </SettingsCrudPanel>
);

const SettingsUsersTab = () => (
  <SettingsCrudPanel>
    <UserPage />
  </SettingsCrudPanel>
);

const SettingsBranchesTab = () => (
  <SettingsCrudPanel>
    <BranchPage />
  </SettingsCrudPanel>
);

export const settingsRoutes: AppRouteDefinition[] = [
  { path: '/user', element: () => <Navigate to="/config/users" replace /> },
  { path: '/branch', element: () => <Navigate to="/config/branches" replace /> },
  { path: '/config/warehouses', element: () => <Navigate to="/inventory/warehouses" replace /> },
  { path: '/voucher-series', element: () => <Navigate to="/config/series" replace /> },
  {
    path: '/config',
    element: SettingsHubLayout,
    children: [
      { path: 'company', element: SettingsCompanyTab },
      { path: 'sunat', element: SettingsSunatTab },
      { path: 'series', element: SettingsSeriesTab },
      { path: 'users', element: SettingsUsersTab },
      { path: 'branches', element: SettingsBranchesTab },
      { path: 'general', element: SettingsGeneralTab },
    ],
  },
];
