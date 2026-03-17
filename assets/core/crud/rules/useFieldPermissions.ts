import { useMemo } from 'react';
import type { Field } from '../../field/Field';

/**
 * Filters and marks fields based on user roles.
 * IMPORTANT: This is UI-only. Backend is the real authorization source.
 *
 * - If field.permissions.visible is set and userRoles has no intersection → filter out the field
 * - If field.permissions.editable is set and userRoles has no intersection → mark field as disabled
 *
 * NOTE: An existing auth hook `useAuth` is available at `assets/contexts/auth.tsx`,
 * which exposes `user?.roles: string[]`. Wave 2 can call:
 *   const { user } = useAuth();
 *   const permittedFields = useFieldPermissions(fields, user?.roles ?? []);
 */
export function useFieldPermissions(fields: Field[], userRoles: string[]): Field[] {
  return useMemo(() => {
    return fields.reduce<Field[]>((acc, field) => {
      const perms = field.permissions;
      if (!perms) {
        acc.push(field);
        return acc;
      }
      // visibility gate
      if (perms.visible && !perms.visible.some((r) => userRoles.includes(r))) {
        return acc; // omit the field entirely
      }
      // editability gate
      if (perms.editable && !perms.editable.some((r) => userRoles.includes(r))) {
        acc.push({ ...field, disabledWhen: () => true }); // force disabled
        return acc;
      }
      acc.push(field);
      return acc;
    }, []);
  }, [fields, userRoles]);
}
