import { describe, expect, it, vi } from 'vitest';
import type { ResourceStoreFactory } from '@nubitio/crud';
import { isInvitationRow, withUserDirectory } from './usersWithInvitations';

const USERS = '/api/users';
const DIRECTORY = '/api/user-directory';

/** A parent factory whose stores remember the url they were built for. */
const parent: ResourceStoreFactory = vi.fn((options) => ({
  load: vi.fn(async () => ({ data: [{ source: options.url }], totalCount: 1, summary: [] })),
  byKey: vi.fn(async () => ({ source: options.url })),
}));

describe('withUserDirectory', () => {
  const factory = withUserDirectory(parent, USERS, DIRECTORY);

  it('lists the users resource from the directory', async () => {
    const store = factory({ url: USERS, idField: 'id' });
    expect((await store.load({})).data).toEqual([{ source: DIRECTORY }]);
  });

  it('keeps reading a single user from the users endpoint', async () => {
    const store = factory({ url: USERS, idField: 'id' });
    expect(await store.byKey?.(5)).toEqual({ source: USERS });
  });

  it('does not touch any other resource', async () => {
    const store = factory({ url: '/api/roles', idField: 'id' });
    expect((await store.load({})).data).toEqual([{ source: '/api/roles' }]);
  });
});

describe('isInvitationRow', () => {
  it('tells an invitation from a user by its status', () => {
    expect(isInvitationRow({ id: 'invitation:a@b.test', status: 'invited' })).toBe(true);
    expect(isInvitationRow({ id: 1, status: 'active' })).toBe(false);
    expect(isInvitationRow(undefined)).toBe(false);
  });
});
