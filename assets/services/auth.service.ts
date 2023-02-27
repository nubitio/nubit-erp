import {httpClient} from "../core/http/HttpClient";
import AppResponse from "../core/http/AppResponse";
import AuthResponse from "../core/http/AuthResponse";

class AuthService {

    login(username: string, password: string) {
        return httpClient.post<AuthResponse>(`/auth/login`, {username, password});
    }

    setLocal(localId: string) {
        return httpClient.post<AppResponse>(`/auth/local`, {localId});
    }
}

export const authService = new AuthService();