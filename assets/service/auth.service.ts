import { httpClient } from '../core/http/HttpClient';
import AuthResponse from '../core/http/AuthResponse';
import { Constants } from '../core/common/Constants';

class AuthService {
  login(username: string, password: string, branchId?: string) {
    return httpClient.post<AuthResponse>(Constants.API_BASE_URL + 'auth/login', {
      username,
      password,
      branchId,
    });
  }

  logout() {
    return httpClient.post(Constants.API_BASE_URL + 'auth/logout');
  }
}

export const authService = new AuthService();
