import { createEndpoints } from '@shared/api/endpoints';

export const userEndpoints = createEndpoints('users');
/** Read-only: users and pending invitations in one list. Writes go to `userEndpoints`. */
export const userDirectoryEndpoints = createEndpoints('user-directory');
export const configEndpoints = createEndpoints('configs');
export const branchEndpoints = createEndpoints('branches');
