import { useMemo } from 'react';
import type { ResourceConfig, ResourcePermissions } from './ResourceConfig';

export type { ResourcePermissions };

/** Fully-resolved, all-required permissions (guaranteed booleans). */
export interface ResolvedPermissions {
  canAdd: boolean;
  canEdit: boolean;
  canDelete: boolean;
  canExport: boolean;
  canBulkDelete: boolean;
}

/** Resolve a single permission entry: callable → call it, boolean → use it, undefined → fallback. */
function resolve(value: boolean | (() => boolean) | undefined, fallback: boolean): boolean {
  if (value === undefined) return fallback;
  if (typeof value === 'function') return value();
  return value;
}

/**
 * Resolves RBAC permissions for a resource with the following precedence
 * (highest → lowest):
 *   1. `resource.permissions.canX` — callable or boolean
 *   2. `resource.allowX`           — legacy boolean alias
 *   3. platform defaults            — canAdd/Edit/Delete=true, canExport/BulkDelete=false
 *
 * Memoized on `resource.id` — stable reference across re-renders.
 */
export function usePermissions(resource: ResourceConfig): ResolvedPermissions {
  return useMemo(() => {
    const p = resource.permissions;
    return {
      canAdd: resolve(p?.canAdd, resource.allowAdd ?? true),
      canEdit: resolve(p?.canEdit, resource.allowEdit ?? true),
      canDelete: resolve(p?.canDelete, resource.allowDelete ?? true),
      canExport: resolve(p?.canExport, resource.allowExport ?? false),
      canBulkDelete: resolve(p?.canBulkDelete, false),
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resource.id]);
}
