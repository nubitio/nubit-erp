import {httpClient} from "../utils/HttpClient";
import {Observable} from "rxjs";

class AuthService {

    login(username: string, password: string): Observable<any> {
        return httpClient.post(`/auth/login`, {username, password});
    }

    logout(): Observable<any> {
        return httpClient.post(`/auth/logout`);
    }

    setLocal(localId: string): Observable<any> {
        return httpClient.post(`/auth/local`, {localId});
    }
}

export const authService = new AuthService();