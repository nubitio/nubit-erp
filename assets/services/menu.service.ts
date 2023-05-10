import {httpClient} from "../core/http/HttpClient";

class MenuService {
    getMenuByRoleId(roleId: number) {
        return httpClient.get<any>(`/menu/${roleId}`)
    }
}

export const menuService = new MenuService();