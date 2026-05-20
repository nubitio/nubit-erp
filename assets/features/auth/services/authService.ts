import { businessHttpClient } from '@shared/api/network';
import AuthResponse from '@shared/api/AuthResponse';
import { API_BASE_URL } from '@app/constants';

class AuthService {
  login(username: string, password: string) {
    return businessHttpClient.post<AuthResponse>(API_BASE_URL + 'auth/login', {
      username,
      password,
    });
  }

  selectBranch(branchId: string) {
    return businessHttpClient.post<AuthResponse>(API_BASE_URL + 'auth/select-branch', {
      branchId,
    });
  }

  logout() {
    return businessHttpClient.post(API_BASE_URL + 'auth/logout');
  }

  changePassword(currentPassword: string, newPassword: string) {
    return businessHttpClient.post<{ message: string }>(
      API_BASE_URL + 'auth/change-password',
      { currentPassword, newPassword },
    );
  }
}

export const authService = new AuthService();
