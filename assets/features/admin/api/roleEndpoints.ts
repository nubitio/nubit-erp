import { createEndpoints } from '@shared/api/endpoints';

const roles = createEndpoints('roles');

export const roleEndpoints = {
  ...roles,
  menu: (roleId: string | number) => `${roles.list()}/menu/${roleId}`,
  saveMenu: () => `${roles.list()}/menu`,
};
