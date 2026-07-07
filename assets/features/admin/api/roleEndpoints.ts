import { createEndpoints } from '@shared/api/endpoints';

const roles = createEndpoints('roles');

export const roleEndpoints = {
  ...roles,
  menu: (roleId: string | number) => `/api/role-menu/${roleId}`,
  saveMenu: () => '/api/role-menu',
  permissions: (roleId: string | number) => `/api/role-permissions/${roleId}`,
  savePermissions: () => '/api/role-permissions',
};
