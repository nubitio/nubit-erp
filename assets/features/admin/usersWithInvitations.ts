import type { DataRecord, ResourceStoreFactory } from '@nubitio/crud';

export type UserStatus = 'active' | 'invited';

/** What the users grid shows for a person: a real user, or an invitation not yet accepted. */
export interface UserGridRow extends DataRecord {
  id: number | string;
  username: string;
  email?: string | null;
  displayName?: string | null;
  phone?: string | null;
  role: unknown;
  status: UserStatus;
  expiresAt?: string;
  lastLoginAt?: string | null;
}

export const isInvitationRow = (row: unknown): row is UserGridRow =>
  (row as UserGridRow | undefined)?.status === 'invited';

/**
 * The users resource, listed from the server's people directory (users and pending invitations in
 * one paged, filtered and sorted list) while everything else about a user — reading one, creating,
 * editing, deleting — keeps going to the users endpoint.
 */
export function withUserDirectory(
  parent: ResourceStoreFactory,
  usersUrl: string,
  directoryUrl: string,
): ResourceStoreFactory {
  return (options) => {
    const users = parent(options);
    if (options.url !== usersUrl) return users;

    const directory = parent({ ...options, url: directoryUrl, byKeyUrl: null });
    return { ...users, load: (loadOptions) => directory.load(loadOptions) };
  };
}
