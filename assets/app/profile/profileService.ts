import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

export type Density = 'normal' | 'compact';
export type ThemeMode = 'light' | 'dark' | 'auto';

/** `null` = never chosen on the account (the browser's own setting stands). */
export interface AccountPreferences {
  density: Density | null;
  theme: ThemeMode | null;
}

export interface Profile {
  id: number;
  username: string;
  displayName: string | null;
  email: string | null;
  phone: string | null;
  avatarUrl: string | null;
  preferences: AccountPreferences;
}

export interface ProfileUpdate {
  displayName?: string | null;
  phone?: string | null;
  preferences?: Partial<{ density: Density; theme: ThemeMode }>;
}

export interface SessionInfo {
  id: number;
  createdAt: string;
  lastUsedAt: string | null;
  expiresAt: string;
  userAgent: string | null;
  ipAddress: string | null;
}

export const AVATAR_MAX_BYTES = 2 * 1024 * 1024;
export const AVATAR_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

export const profileService = {
  get: () => httpClient.get<Profile>(`${API}profile`),

  update: (patch: ProfileUpdate) => httpClient.patch<Profile>(`${API}profile`, patch),

  /** The server asks for the current password: a stolen session must not be able to repoint recovery. */
  changeEmail: (email: string, currentPassword: string) =>
    httpClient.post<Profile>(`${API}profile/email`, { email, currentPassword }),

  uploadAvatar: (file: File) => {
    const body = new FormData();
    body.append('file', file);
    return httpClient.post<Profile>(`${API}profile/avatar`, body);
  },

  removeAvatar: () => httpClient.delete<Profile>(`${API}profile/avatar`),

  /** Only exists while the identity module is enabled server-side. */
  sessions: () => httpClient.get<{ sessions: SessionInfo[] }>(`${API}auth/sessions`),

  revokeSession: (id: number) => httpClient.delete<void>(`${API}auth/sessions/${id}`),
};
