import { httpClient } from '@shared/api/HttpClient';
import { SideNavigationItem } from '@shared/ui/library/side-navigation-menu/SideNavigationMenu';
import { Constants } from '@core/common/Constants';

class MenuService {
  getMenuByRoleId(roleId: number) {
    if (roleId === null || roleId === undefined) {
      roleId = 0;
    }
    return httpClient.get<SideNavigationItem[]>(Constants.API_BASE_URL + `menu/${roleId}`);
  }
}

export const menuService = new MenuService();
