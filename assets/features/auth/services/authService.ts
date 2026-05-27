import { httpClient } from '@shared/api/HttpClient';
import AuthResponse from '@shared/api/AuthResponse';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();

class AuthService {
  login(username: string, password: string) {
    return httpClient.post<AuthResponse>(API + 'auth/login', {
      username,
      password,
    });
  }

  selectBranch(branchId: string) {
    return httpClient.post<AuthResponse>(API + 'auth/select-branch', {
      branchId,
    });
  }

  logout() {
    return httpClient.post(API + 'auth/logout');
  }

  changePassword(currentPassword: string, newPassword: string) {
    return httpClient.post<{ message: string }>(
      API + 'auth/change-password',
      { currentPassword, newPassword },
    );
  }
}

export const authService = new AuthService();
