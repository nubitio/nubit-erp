import { Constants } from '@core/common/Constants';
import { createEndpoints } from '@shared/api/endpoints';

const BASE = Constants.API_BASE_URL;

export const roleEndpoints = {
  ...createEndpoints('roles'),
  menu: (roleId: string | number) => `${BASE}role-menu/${roleId}`,
  saveMenu: () => `${BASE}role-menu`,
};
