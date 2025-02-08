import {httpClient} from "../core/http/HttpClient";
import AuthResponse from "../core/http/AuthResponse";
import {Constants} from "../core/common/Constants";

class AuthService {
    login(username: string, password: string, branchId?: string) {
        return httpClient.post<AuthResponse>(Constants.API_BASE_URL + `auth`, {username, password, branchId});
    }
}

export const authService = new AuthService();
