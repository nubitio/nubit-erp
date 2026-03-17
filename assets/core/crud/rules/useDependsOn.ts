import { useEffect, useRef } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { Field } from '../../field/Field';

/**
 * For each field that declares `dependsOn`, invalidate its entity query
 * (keyed by the field's `url`) whenever one of the watched form values changes.
 *
 * Only fires when a dependency value actually changes (deep compare via JSON.stringify).
 * Skips the initial mount run to prevent spurious refetches when a dialog opens
 * with already-cached data (BUG-009).
 *
 * Also skips evaluation when `formData` is an empty object `{}` — this guards
 * against the one-render flash where SmartCrudPage initialises `formData` to `{}`
 * before the dialog populates it with the selected row's data.
 *
 * This is a React Query invalidation pattern: when a dependency field changes,
 * the dependent field's options are refetched from the server.
 */
export function useDependsOn(fields: Field[], formData: Record<string, unknown>): void {
  const queryClient = useQueryClient();

  // Guard: do nothing while formData is still the empty initial object.
  // This suppresses the `{}` flash that occurs before CrudPage's onFieldDataChanged
  // has fired for the first time after the dialog opens.
  const hasData = Object.keys(formData).length > 0;

  // Compute the current values of all dependency fields for comparison.
  // Include `hasData` in the serialised snapshot so the effect re-runs once
  // real data arrives, but the isMounted guard below blocks the invalidation
  // on the very first run (the initial mount with real data).
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const dependencyValues = JSON.stringify(
    hasData
      ? fields
          .filter((f) => f.dependsOn?.length)
          .flatMap((f) => f.dependsOn!)
          .map((dep) => formData[dep])
      : null,
  );

  // Mount guard: skip the very first execution of the effect so that opening
  // a dialog (which mounts this hook with its initial data) does not trigger
  // a spurious invalidation of already-cached entity queries.
  const isMounted = useRef(false);

  useEffect(() => {
    // Skip the first run (initial mount / first stable value).
    if (!isMounted.current) {
      isMounted.current = true;
      return;
    }

    // Also skip when formData is empty — no meaningful dependency values yet.
    if (!hasData) return;

    fields.forEach((field) => {
      if (!field.dependsOn?.length) return;
      // Invalidate the entity query keyed by the field's url so it reloads options
      const entityUrl = field.url ?? null;
      if (entityUrl) {
        queryClient.invalidateQueries({ queryKey: [entityUrl] });
      }
    });
    // Only re-run when the watched dependency values change
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dependencyValues]);
}
