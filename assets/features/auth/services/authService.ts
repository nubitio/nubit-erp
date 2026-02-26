import { httpClient } from '../../../shared/api/HttpClient';
import AuthResponse from '../../../shared/api/AuthResponse';
import { Constants } from '../../../core/common/Constants';

class AuthService {
  login(username: string, password: string) {
    return httpClient.post<AuthResponse>(Constants.API_BASE_URL + 'auth/login', {
      username,
      password,
    });
  }

  selectBranch(branchId: string) {
    return httpClient.post<AuthResponse>(Constants.API_BASE_URL + 'auth/select-branch', {
      branchId,
    });
  }

  logout() {
    return httpClient.post(Constants.API_BASE_URL + 'auth/logout');
  }
}

export const authService = new AuthService();
