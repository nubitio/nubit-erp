import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";
import AuthResponse from "../core/http/AuthResponse";
import {Constants} from "../core/common/Constants";

class AuthService {

    login(username: string, password: string, branchId?: string) {
        return httpClient.post<AuthResponse>(Constants.API_BASE_URL + `auth`, {username, password, branchId});
    }

    setLocal(localId: string) {
        return httpClient.post<AppResponse>(`/auth/local`, {localId});
    }

    changePassword(userId: number, actualPassword: string, newPassword: string) {
        return httpClient.post<AppResponse>('/changePassword/update-password', {userId, actualPassword, newPassword});
    }
}

export const authService = new AuthService();
