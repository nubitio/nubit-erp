import {httpClient} from "../core/http/HttpClient";
import {Observable} from "rxjs";
import ApiResponse from "../core/http/ApiResponse";
import AppResponse from "../core/http/AppResponse";
import AuthResponse from "../core/http/AuthResponse";

class AuthService {

    login(username: string, password: string): Observable<ApiResponse<AuthResponse>> {
        return httpClient.post(`/auth/login`, {username, password});
    }

    setLocal(localId: string): Observable<ApiResponse<AppResponse>> {
        return httpClient.post(`/auth/local`, {localId});
    }
}

export const authService = new AuthService();