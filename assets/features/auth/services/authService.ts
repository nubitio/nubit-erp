import { businessHttpClient } from '@shared/api/network';
import AuthResponse from '@shared/api/AuthResponse';
import { Constants } from '@core/common/Constants';

class AuthService {
  login(username: string, password: string) {
    return businessHttpClient.post<AuthResponse>(Constants.API_BASE_URL + 'auth/login', {
      username,
      password,
    });
  }

  selectBranch(branchId: string) {
    return businessHttpClient.post<AuthResponse>(Constants.API_BASE_URL + 'auth/select-branch', {
      branchId,
    });
  }

  logout() {
    return businessHttpClient.post(Constants.API_BASE_URL + 'auth/logout');
  }
}

export const authService = new AuthService();
