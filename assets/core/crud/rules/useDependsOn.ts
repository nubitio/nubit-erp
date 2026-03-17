import { useEffect } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type { Field } from '../../field/Field';

/**
 * For each field that declares `dependsOn`, invalidate its entity query
 * (keyed by the field's `url`) whenever one of the watched form values changes.
 *
 * Only fires when a dependency value actually changes (deep compare via JSON.stringify).
 *
 * This is a React Query invalidation pattern: when a dependency field changes,
 * the dependent field's options are refetched from the server.
 */
export function useDependsOn(fields: Field[], formData: Record<string, unknown>): void {
  const queryClient = useQueryClient();

  // Compute the current values of all dependency fields for comparison
  // eslint-disable-next-line react-hooks/exhaustive-deps
  const dependencyValues = JSON.stringify(
    fields
      .filter((f) => f.dependsOn?.length)
      .flatMap((f) => f.dependsOn!)
      .map((dep) => formData[dep]),
  );

  useEffect(() => {
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
