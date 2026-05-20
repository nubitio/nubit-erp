import { API_BASE_URL } from '@app/constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = API_BASE_URL;

export const roleEndpoints = {
  ...createEndpoints('roles'),
  menu: (roleId: string | number) => `${BASE}role-menu/${roleId}`,
  saveMenu: () => `${BASE}role-menu`,
};
