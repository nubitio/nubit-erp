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
 * Derive permissions from `hydra:supportedOperation` method list.
 * Returns `undefined` for each permission when `supportedOperations` is empty
 * (signals "no info available — use the next fallback level").
 */
function fromOperations(
  supportedOperations: string[],
): { canAdd: boolean; canEdit: boolean; canDelete: boolean } | undefined {
  if (supportedOperations.length === 0) return undefined;
  return {
    canAdd: supportedOperations.includes('POST'),
    canEdit: supportedOperations.includes('PUT') || supportedOperations.includes('PATCH'),
    canDelete: supportedOperations.includes('DELETE'),
  };
}

/**
 * Resolves RBAC permissions for a resource with the following precedence
 * (highest → lowest):
 *   1. `resource.permissions.canX` — callable or boolean override
 *   2. `resource.allowX`           — legacy boolean alias
 *   3. inferred from `supportedOperations` (hydra:supportedOperation methods)
 *   4. platform defaults            — canAdd/Edit/Delete=true, canExport/BulkDelete=false
 *
 * The `supportedOperations` parameter comes from `useResourceSchema` and is
 * only consulted when neither a `resource.permissions.canX` nor a
 * `resource.allowX` value was explicitly provided by the caller.
 *
 * Memoized on `resource.id` — stable reference across re-renders.
 */
export function usePermissions(
  resource: ResourceConfig,
  supportedOperations: string[] = [],
): ResolvedPermissions {
  // Stabilize the ops array as a sorted joined string so that a new [] reference
  // on every render (e.g. from a default parameter) does not break memoization.
  const opsKey = supportedOperations.slice().sort().join(',');

  // eslint-disable-next-line react-hooks/exhaustive-deps
  return useMemo(() => {
    const p = resource.permissions;
    const inferred = fromOperations(supportedOperations);

    // For canAdd/canEdit/canDelete we use a 4-level waterfall:
    //   1. explicit permissions.canX callable/boolean
    //   2. explicit allowX boolean
    //   3. inferred from Hydra supportedOperations
    //   4. platform default (true)
    //
    // We only reach level 3 when both level 1 (p?.canX) and level 2 (resource.allowX)
    // are undefined — i.e. the user has not manually configured the permission.
    function resolveWithInferred(
      permValue: boolean | (() => boolean) | undefined,
      allowValue: boolean | undefined,
      inferredValue: boolean | undefined,
      platformDefault: boolean,
    ): boolean {
      // Level 1: explicit permissions override
      if (permValue !== undefined) return resolve(permValue, platformDefault);
      // Level 2: legacy allowX boolean
      if (allowValue !== undefined) return allowValue;
      // Level 3: inferred from Hydra operations
      if (inferredValue !== undefined) return inferredValue;
      // Level 4: platform default
      return platformDefault;
    }

    return {
      canAdd: resolveWithInferred(p?.canAdd, resource.allowAdd, inferred?.canAdd, true),
      canEdit: resolveWithInferred(p?.canEdit, resource.allowEdit, inferred?.canEdit, true),
      canDelete: resolveWithInferred(p?.canDelete, resource.allowDelete, inferred?.canDelete, true),
      // canExport and canBulkDelete have no Hydra-inferred counterpart; use the
      // existing 2-level resolution (explicit override → platform default).
      canExport: resolve(p?.canExport, resource.allowExport ?? false),
      canBulkDelete: resolve(p?.canBulkDelete, false),
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resource.id, opsKey]);
}
