import { httpClient } from '@shared/api/HttpClient';
import { Constants } from '@core/common/Constants';
import type { SideNavigationItem } from '@app/shell/components';

class MenuService {
  getMenuByRoleId(roleId: number) {
    if (roleId === null || roleId === undefined) {
      roleId = 0;
    }
    return httpClient.get<SideNavigationItem[]>(Constants.API_BASE_URL + `menu/${roleId}`);
  }
}

export const menuService = new MenuService();
