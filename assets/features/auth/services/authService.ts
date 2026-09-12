import { httpClient } from '@shared/api/HttpClient';
import AuthResponse from '@shared/api/AuthResponse';
import { getCoreApiBaseUrl } from '@nubitio/core';
import type { DataRecord } from '@nubitio/core';
import { StorageUtils } from '@shared/lib/StorageUtils';

const API = getCoreApiBaseUrl();

class AuthService {
  login(username: string, password: string) {
    return httpClient
      .post<AuthResponse>(API + 'auth/login', { username, password })
      .then((response) => {
        if (response.data.token) {
          StorageUtils.setItem('auth_token', response.data.token);
        }

        return response;
      });
  }

  consumeImpersonationToken(token: string) {
    return httpClient.post<{ ok: boolean; user?: DataRecord }>(
      API + 'auth/impersonate/consume',
      { token },
    );
  }

  selectBranch(branchId: string) {
    return httpClient
      .post<AuthResponse>(API + 'auth/select-branch', {
        branchId,
        response_mode: 'json',
      })
      .then((response) => {
        if (response.data.token) {
          StorageUtils.setItem('auth_token', response.data.token);
        }

        return response;
      });
  }

  logout() {
    StorageUtils.removeItem('auth_token');
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
