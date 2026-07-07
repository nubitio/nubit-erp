import { getSessionUser } from './sessionInfo';

/**
 * Granular permission check against the keys granted at login
 * (see PermissionCatalog on the backend). Super admins carry the
 * '*' wildcard and pass every check.
 *
 * Reads localStorage synchronously; permissions refresh on re-login.
 */
export function hasPermission(permission: string): boolean {
  const permissions = getSessionUser()?.permissions ?? [];

  return permissions.includes('*') || permissions.includes(permission);
}

export function hasAnyPermission(...keys: string[]): boolean {
  return keys.some(hasPermission);
}

export function usePermissions() {
  return { hasPermission, hasAnyPermission };
}
