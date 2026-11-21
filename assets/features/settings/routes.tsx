import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { SettingsHubLayout } from './SettingsHubLayout';
import { SettingsSunatTab } from './SettingsSunatTab';
import { SettingsGeneralTab } from './SettingsGeneralTab';
import { SettingsIntegrationsTab } from './SettingsIntegrationsTab';
import { SettingsCrudPanel } from './SettingsCrudPanel';
import { useTenantRuntimeConfig } from '@shared/hooks';

const VoucherSeriesPage = lazyNamed(
  () => import('@features/catalog/VoucherSeriesPage'),
  'VoucherSeriesPage',
);
const UserPage = lazyNamed(() => import('@features/admin/UserPage'), 'UserPage');
const RolePage = lazyNamed(() => import('@features/admin/RolePage'), 'RolePage');
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

const SettingsRolesTab = () => (
  <SettingsCrudPanel>
    <RolePage />
  </SettingsCrudPanel>
);

const InvitationsPage = lazyNamed(
  () => import('@features/admin/InvitationsPage'),
  'InvitationsPage',
);
const ActivityPage = lazyNamed(() => import('@features/activity/ActivityPage'), 'ActivityPage');

const SettingsInvitationsTab = () => (
  <SettingsCrudPanel>
    <InvitationsPage />
  </SettingsCrudPanel>
);

const SettingsActivityTab = () => (
  <SettingsCrudPanel>
    <ActivityPage />
  </SettingsCrudPanel>
);

const SettingsBranchesTab = () => (
  <SettingsCrudPanel>
    <BranchPage />
  </SettingsCrudPanel>
);

const WarehousesRedirect = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  if (runtimeConfig?.ui.inventory.showWarehouseManagement === false) {
    return <Navigate to="/inventory/stock" replace />;
  }
  return <Navigate to="/inventory/warehouses" replace />;
};

export const settingsRoutes: AppRouteDefinition[] = [
  { path: '/user', element: () => <Navigate to="/config/users" replace /> },
  { path: '/role', element: () => <Navigate to="/config/roles" replace /> },
  { path: '/branch', element: () => <Navigate to="/config/branches" replace /> },
  { path: '/config/warehouses', element: WarehousesRedirect },
  { path: '/voucher-series', element: () => <Navigate to="/config/series" replace /> },
  {
    path: '/config',
    element: SettingsHubLayout,
    children: [
      { path: 'company', element: () => <Navigate to="/dashboard?settings=company" replace /> },
      { path: 'sunat', element: SettingsSunatTab },
      { path: 'series', element: SettingsSeriesTab },
      { path: 'users', element: SettingsUsersTab },
      { path: 'roles', element: SettingsRolesTab },
      { path: 'invitations', element: SettingsInvitationsTab },
      { path: 'activity', element: SettingsActivityTab },
      { path: 'branches', element: SettingsBranchesTab },
      { path: 'general', element: SettingsGeneralTab },
      { path: 'integrations', element: SettingsIntegrationsTab },
    ],
  },
];
