/**
 * devHint — logs a `defineResource(...)` snippet to the console in development
 * mode when SmartCrudPage has auto-inferred fields from the Hydra API doc.
 *
 * Rules:
 *  - Only runs when `import.meta.env.DEV` is `true`.
 *  - Fires at most once per resource URL across the entire app lifetime
 *    (module-level Set prevents duplicate logs on re-renders or StrictMode
 *    double-invocation).
 *  - Uses `console.info` prefixed with `[SmartCRUD]`.
 *  - Is a complete no-op in production builds (tree-shakeable).
 */

import type { Field } from '../field/Field';

/** Tracks resource URLs that have already been hinted to avoid duplicate logs. */
const hintedUrls = new Set<string>();

/**
 * Derives a one-line field descriptor string for use in the hint snippet.
 * Produces e.g. `{ key: 'name', label: 'Name', type: 'text' }`.
 */
function fieldToHintLine(field: Field): string {
  const readOnlyPart = field.readonly ? ', readOnly: true' : '';
  return `    { key: '${field.name}', label: '${field.label}', type: '${field.type}'${readOnlyPart} },`;
}

/**
 * Log a dev hint for auto-inferred fields the first time a resource URL is
 * mounted with auto-discovery active.
 *
 * @param resourceUrl - The `apiUrl` passed to SmartCrudPage (normalized form).
 * @param fields      - The auto-inferred `Field[]` resolved by useResourceSchema.
 */
export function logDevHint(resourceUrl: string, fields: Field[]): void {
  if (!import.meta.env.DEV) return;

  if (hintedUrls.has(resourceUrl)) return;
  hintedUrls.add(resourceUrl);

  // Exclude hidden identity fields from the hint — developers should not need
  // to manually define the system-generated id field in their resource config.
  const hintableFields = fields.filter((f) => !f.isIdentity);
  const fieldLines = hintableFields.map(fieldToHintLine).join('\n');

  // eslint-disable-next-line no-console
  console.info(
    `[SmartCRUD] Auto-inferred fields for "${resourceUrl}". To customise, use:\n\n` +
      `defineResource('${resourceUrl}', {\n` +
      `  fields: [\n` +
      `${fieldLines}\n` +
      `  ]\n` +
      `})`,
  );
}
