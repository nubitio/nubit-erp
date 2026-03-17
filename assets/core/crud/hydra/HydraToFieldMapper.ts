import type { Field } from '../../field/Field';
import type { HydraResourceSchema, HydraFieldSchema } from './types';
import {
  textField,
  numberField,
  switchField,
  entityField,
  noneField,
} from '../../field/FieldBuilders';
import { datetimeField } from '../../field/FieldBuilders';

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Convert a camelCase or snake_case field name to a human-readable label.
 * Examples:
 *   "firstName"   → "First Name"
 *   "createdAt"   → "Created At"
 *   "x_resource"  → "X Resource"
 */
function toLabel(name: string): string {
  return name
    .replace(/_/g, ' ')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/^./, (c) => c.toUpperCase());
}

// ---------------------------------------------------------------------------
// Public mapper
// ---------------------------------------------------------------------------

/**
 * Map a single HydraResourceSchema to an array of Field objects using the
 * existing field builder utilities.
 *
 * Mapping rules (in priority order):
 *  1. name === 'id' or name === '@id'  → skip (return null)
 *  2. readable: false                  → skip (already filtered upstream, but defensive)
 *  3. writeable: false (display-only)  → noneField
 *  4. range === 'xmls:boolean'         → switchField
 *  5. range === 'xmls:dateTime'        → datetimeField
 *  6. range === 'xmls:integer'         → numberField with precision(0)
 *  7. range === 'xmls:decimal'         → numberField
 *  8. range starts with '#' (entity ref) OR propertyType === 'Link' → entityField
 *  9. range === 'xmls:string' OR fallback  → textField
 *
 * @pure — no React, no hooks, no side effects.
 */
export function mapHydraSchemaToFields(schema: HydraResourceSchema): Field[] {
  const fields: Field[] = [];

  for (const fieldSchema of schema.fields) {
    const { name, range, propertyType, required, readable, writeable } = fieldSchema;

    // Rule 1 — skip identity fields
    if (name === 'id' || name === '@id') {
      continue;
    }

    // Rule 2 — defensive: skip non-readable fields (should be filtered by parser)
    if (!readable) {
      continue;
    }

    const label = toLabel(name);

    // Rule 3 — display-only (writeable: false) → noneField
    if (!writeable) {
      fields.push(noneField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 4 — boolean → switchField
    if (range === 'xmls:boolean') {
      fields.push(switchField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 5 — date-time → datetimeField
    if (range === 'xmls:dateTime') {
      fields.push(datetimeField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 6 — integer → numberField with precision 0
    if (range === 'xmls:integer') {
      fields.push(numberField().name(name).label(label).required(required).precision(0).build());
      continue;
    }

    // Rule 7 — decimal → numberField
    if (range === 'xmls:decimal') {
      fields.push(numberField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 8 — entity reference (range starts with '#') or Link type → entityField
    if ((range && range.startsWith('#')) || propertyType === 'Link') {
      const resourceClass = range ? range.replace('#', '') : '';
      // Derive a guessed API URL from the class name; pages may override via useResourceSchema
      const url = resourceClass
        ? `/api/${resourceClass
            .replace(/([A-Z])/g, '_$1')
            .toLowerCase()
            .replace(/^_/, '')}s`
        : '';
      fields.push(
        entityField(url, 'id', 'name').name(name).label(label).required(required).build(),
      );
      continue;
    }

    // Rule 9 — xmls:string and unknown → textField (safe fallback)
    fields.push(textField().name(name).label(label).required(required).build());
  }

  return fields;
}
