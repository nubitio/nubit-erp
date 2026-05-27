import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import type { AdminMenuItem } from '@nubit/admin';

class MenuService {
  getMenuByRoleId(roleId: number) {
    if (roleId === null || roleId === undefined) {
      roleId = 0;
    }
    return httpClient.get<AdminMenuItem[]>(API + `menu/${roleId}`);
  }
}

export const menuService = new MenuService();
