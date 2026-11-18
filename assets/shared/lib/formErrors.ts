import { mapApiViolations } from '@nubitio/crud';
import type { FieldPath, FieldValues, UseFormSetError } from 'react-hook-form';

export const GENERIC_SAVE_ERROR = 'No se pudo guardar. Inténtalo de nuevo.';

export interface ServerErrorOutcome {
  /** A message for the form as a whole: violations on fields the form does not have, or no violations at all. */
  formError: string | null;
}

function dataOf(error: unknown): Record<string, unknown> | undefined {
  const data = (error as { data?: unknown } | null)?.data;
  return data && typeof data === 'object' ? (data as Record<string, unknown>) : undefined;
}

/** API Platform answers with `detail` for domain errors, `title` / `message` for others. */
function messageOf(data: Record<string, unknown> | undefined): string | null {
  for (const key of ['detail', 'message', 'title'] as const) {
    const value = data?.[key];
    if (typeof value === 'string' && value.trim() !== '') return value;
  }
  return null;
}

/**
 * Puts the violations of a failed save back on the form's fields.
 *
 * A 422 from API Platform lists `violations: [{ propertyPath, message }]`; each one becomes a
 * field error, and the first is focused so the user lands on what to fix. Anything the form
 * cannot place (a field it does not render, or an error that is not a violation list at all) is
 * returned as `formError` for the caller to show on the form instead of being dropped.
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly FieldPath<T>[],
): ServerErrorOutcome {
  const data = dataOf(error);
  const violations = data?.['violations'];

  // A server fault (5xx) says nothing the user can act on, and its `detail` can carry internals
  // (host names, SQL, stack frames in debug). Show a generic message; the real one is in the logs.
  const status = (error as { status?: unknown } | null)?.status;
  if (typeof status === 'number' && status >= 500) {
    return { formError: GENERIC_SAVE_ERROR };
  }

  if (!Array.isArray(violations)) {
    return { formError: messageOf(data) ?? GENERIC_SAVE_ERROR };
  }

  const mapped = mapApiViolations(violations);
  const unplaced: string[] = [...mapped.unassigned];
  let focused = false;

  for (const [path, message] of Object.entries(mapped.fieldErrors)) {
    if ((fields as readonly string[]).includes(path)) {
      setError(path as FieldPath<T>, { type: 'server', message }, { shouldFocus: !focused });
      focused = true;
    } else {
      unplaced.push(message);
    }
  }

  return { formError: unplaced.length > 0 ? unplaced.join(' ') : null };
}
