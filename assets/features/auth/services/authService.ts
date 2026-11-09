import { httpClient } from '@shared/api/HttpClient';
import AuthResponse from '@shared/api/AuthResponse';
import { getCoreApiBaseUrl } from '@nubitio/core';
import type { DataRecord } from '@nubitio/core';
import { StorageUtils } from '@shared/lib/StorageUtils';

const API = getCoreApiBaseUrl();

class AuthService {
  /** `totpCode` is only sent on the second step, once the server asked for a second factor. */
  login(username: string, password: string, totpCode?: string) {
    return httpClient
      .post<AuthResponse>(API + 'auth/login', {
        username,
        password,
        ...(totpCode ? { totpCode } : {}),
      })
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

  /** Always resolves the same way: the server never reveals whether the account exists. */
  forgotPassword(usernameOrEmail: string) {
    return httpClient.post(API + 'auth/password/forgot', { username: usernameOrEmail });
  }

  resetPassword(token: string, password: string) {
    return httpClient.post<{ reset: boolean }>(API + 'auth/password/reset', { token, password });
  }

  previewInvitation(token: string) {
    return httpClient.get<{ email: string; roles: string[]; expiresAt: string }>(
      API + 'invitations/' + encodeURIComponent(token),
    );
  }

  acceptInvitation(token: string, password: string) {
    return httpClient.post<{ username: string }>(
      API + 'invitations/' + encodeURIComponent(token) + '/accept',
      { password },
    );
  }

  totpStatus() {
    return httpClient.get<{ enrolled: boolean; pending: boolean; recoveryCodesLeft: number }>(
      API + 'auth/totp',
    );
  }

  /** Starts enrolment. The secret and recovery codes are returned ONCE. */
  totpBegin() {
    return httpClient.post<{ secret: string; uri: string; recoveryCodes: string[] }>(
      API + 'auth/totp',
    );
  }

  totpConfirm(code: string) {
    return httpClient.post<{ enrolled: boolean }>(API + 'auth/totp/confirm', { code });
  }

  totpRecoveryCodes(code: string) {
    return httpClient.post<{ recoveryCodes: string[] }>(API + 'auth/totp/recovery-codes', { code });
  }

  /** The server requires the current code (or a recovery code): a stolen session cannot remove the factor. */
  totpDisable(code: string) {
    return httpClient.post<void>(API + 'auth/totp/disable', { code });
  }

  changePassword(currentPassword: string, newPassword: string) {
    return httpClient.post<{ message: string }>(API + 'auth/change-password', {
      currentPassword,
      newPassword,
    });
  }
}

export const authService = new AuthService();
