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
import type { FieldType } from '../../field/FieldType';

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

/**
 * Determine the FieldType bucket for a given `range` string.
 *
 * Handles both the short-form `xmls:` prefix used by API Platform
 * AND the full XSD IRI form `http://www.w3.org/2001/XMLSchema#...`
 * that can appear when `property.range` is an IRI object `{ "@id": "..." }`
 * and has been normalised to a string by `normalizeRange`.
 *
 * Returns a string tag that the caller can switch on; not the FieldType enum
 * directly (to keep this function pure / dependency-free of FieldBuilders).
 */
type RangeTag = 'boolean' | 'dateTime' | 'integer' | 'decimal' | 'entity' | 'text';

export function resolveRangeTag(range: string | undefined, propertyType: string): RangeTag {
  if (!range) return 'text';

  // Full XSD IRI check — extract the local name after '#'
  if (range.startsWith('http://www.w3.org/2001/XMLSchema#')) {
    const local = range.split('#').pop() ?? '';
    if (local === 'boolean') return 'boolean';
    if (local === 'dateTime') return 'dateTime';
    if (local === 'integer') return 'integer';
    if (local === 'decimal' || local === 'float' || local === 'double') return 'decimal';
    if (local === 'string') return 'text';
    return 'text';
  }

  // Short-form xmls: prefix
  if (range === 'xmls:boolean') return 'boolean';
  if (range === 'xmls:dateTime') return 'dateTime';
  if (range === 'xmls:integer') return 'integer';
  if (range === 'xmls:decimal') return 'decimal';
  if (range === 'xmls:string') return 'text';

  // Entity reference — local hash class ref "#SomeClass"
  if (range.startsWith('#')) return 'entity';

  // HTTP IRI that is NOT an XSD type → treat as entity class IRI
  if (range.startsWith('http')) return 'entity';

  // Link property type (no range prefix match)
  if (propertyType === 'Link') return 'entity';

  // Unknown / fallback
  return 'text';
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
 *  4. range resolves to 'boolean'      → switchField
 *  5. range resolves to 'dateTime'     → datetimeField
 *  6. range resolves to 'integer'      → numberField with precision(0)
 *  7. range resolves to 'decimal'      → numberField
 *  8. range resolves to 'entity' OR propertyType === 'Link' → entityField
 *  9. range resolves to 'text' OR fallback  → textField
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

    const tag = resolveRangeTag(range, propertyType);

    // Rule 4 — boolean → switchField
    if (tag === 'boolean') {
      fields.push(switchField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 5 — date-time → datetimeField
    if (tag === 'dateTime') {
      fields.push(datetimeField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 6 — integer → numberField with precision 0
    if (tag === 'integer') {
      fields.push(numberField().name(name).label(label).required(required).precision(0).build());
      continue;
    }

    // Rule 7 — decimal → numberField
    if (tag === 'decimal') {
      fields.push(numberField().name(name).label(label).required(required).build());
      continue;
    }

    // Rule 8 — entity reference (range starts with '#' / http IRI) or Link type → entityField
    if (tag === 'entity' || propertyType === 'Link') {
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
