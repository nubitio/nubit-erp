import { mapApiViolations } from '@nubitio/crud';

export interface FormViolations {
  /** Messages that belong under a field, keyed by field name. */
  fields: Record<string, string>;
  /** Anything that could not be placed on a field. */
  general: string | null;
}

/**
 * Reads a 422 `violations` payload (what the profile API answers, like API Platform). Other
 * failures were already reported by HttpClient, so they come back empty.
 */
export function violationsOf(error: unknown, knownFields: readonly string[]): FormViolations {
  const violations = (error as { data?: { violations?: unknown } } | null)?.data?.violations;
  if (!Array.isArray(violations)) return { fields: {}, general: null };

  const mapped = mapApiViolations(violations);
  const fields: Record<string, string> = {};
  const unplaced = [...mapped.unassigned];
  for (const [path, message] of Object.entries(mapped.fieldErrors)) {
    if (knownFields.includes(path)) fields[path] = message;
    else unplaced.push(message);
  }
  return { fields, general: unplaced.length > 0 ? unplaced.join(' ') : null };
}
