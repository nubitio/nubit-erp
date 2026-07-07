interface StoredUser {
  userId?: string | number;
  username?: string;
  role?: number;
  roles?: string[];
  permissions?: string[];
}

function getStoredItem<T>(key: string): T | null {
  if (typeof localStorage === 'undefined') return null;

  const item = localStorage.getItem(key);
  if (!item) return null;

  try {
    return JSON.parse(item) as T;
  } catch {
    return null;
  }
}

export const getSessionUser = () => getStoredItem<StoredUser>('user') ?? undefined;

export const userInformation = () => {
  const user = getSessionUser();
  const currentUserId = user?.userId;
  const currentUserName = user?.username ?? '';
  const currentUserRoleId = user?.role;
  const currentBranchId = getStoredItem<string>('selectedBranchId') ?? '';
  const currentBranchName = getStoredItem<string>('selectedBranchName') ?? '';

  return {
    currentUserId,
    currentUserName,
    currentUserRoleId,
    currentBranchId,
    currentBranchName,
  };
};

/**
 * Returns branch and user info from the active eFact session.
 * Reads localStorage synchronously; it does not subscribe to future changes.
 */
export function useSessionInfo() {
  return userInformation();
}
