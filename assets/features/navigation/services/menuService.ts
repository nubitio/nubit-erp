import { httpClient } from '@shared/api/HttpClient';
import { API_BASE_URL } from '@app/constants';
import type { AdminMenuItem } from '@nubit/admin';

class MenuService {
  getMenuByRoleId(roleId: number) {
    if (roleId === null || roleId === undefined) {
      roleId = 0;
    }
    return httpClient.get<AdminMenuItem[]>(API_BASE_URL + `menu/${roleId}`);
  }
}

export const menuService = new MenuService();
