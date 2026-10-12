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
        } else {
          StorageUtils.removeItem('auth_token');
        }

        return response;
      });
  }

  consumeImpersonationToken(token: string) {
    // Authenticate this exchange with the explicit support credential rather
    // than a possibly stale session already present on the tenant domain.
    return httpClient.post<{ ok: boolean; user?: DataRecord }>(
      API + 'auth/impersonate/consume',
      { token },
      { headers: { Authorization: `Bearer ${token}` } },
    );
  }

  selectBranch(branchId: string) {
    return httpClient.post<AuthResponse>(API + 'auth/select-branch', {
      branchId,
    });
  }

  logout() {
    StorageUtils.removeItem('auth_token');
    return httpClient.post(API + 'auth/logout');
  }

  changePassword(currentPassword: string, newPassword: string) {
    return httpClient.post<{ message: string }>(API + 'auth/change-password', {
      currentPassword,
      newPassword,
    });
  }
}

export const authService = new AuthService();
