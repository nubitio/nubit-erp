import { Navigate } from 'react-router-dom';
import { lazyNamed } from '@app/router/lazy';
import type { AppRouteDefinition } from '@app/router/types';
import { useTenantRuntimeConfig } from '@shared/hooks';

const UserPage = lazyNamed(() => import('@features/admin/UserPage'), 'UserPage');
const RolePage = lazyNamed(() => import('@features/admin/RolePage'), 'RolePage');
const ActivityPage = lazyNamed(() => import('@features/activity/ActivityPage'), 'ActivityPage');

const WarehousesRedirect = () => {
  const { data: runtimeConfig } = useTenantRuntimeConfig();
  if (runtimeConfig?.ui.inventory.showWarehouseManagement === false) {
    return <Navigate to="/inventory/stock" replace />;
  }
  return <Navigate to="/inventory/warehouses" replace />;
};

/** A settings page that is edited inside the account settings dialog over the dashboard. */
const inDialog = (section: string, path = `/config/${section}`): AppRouteDefinition => ({
  path,
  element: () => <Navigate to={`/dashboard?settings=${section}`} replace />,
});

export const settingsRoutes: AppRouteDefinition[] = [
  { path: '/user', element: () => <Navigate to="/config/users" replace /> },
  { path: '/role', element: () => <Navigate to="/config/roles" replace /> },
  { path: '/branch', element: () => <Navigate to="/config/branches" replace /> },
  { path: '/config/warehouses', element: WarehousesRedirect },
  { path: '/voucher-series', element: () => <Navigate to="/config/series" replace /> },
  inDialog('company', '/config'),
  inDialog('company'),
  inDialog('sunat'),
  inDialog('series'),
  inDialog('branches'),
  inDialog('general'),
  inDialog('integrations'),
  // Lists of records, each a page of its own under the "Administración" menu group. Pending
  // invitations are rows of the users grid.
  { path: '/config/users', element: UserPage },
  { path: '/config/invitations', element: () => <Navigate to="/config/users" replace /> },
  { path: '/config/roles', element: RolePage },
  { path: '/config/activity', element: ActivityPage },
];
