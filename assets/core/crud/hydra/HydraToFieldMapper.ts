import type { Field } from '../../field/Field';
import type { HydraResourceSchema } from './types';
import {
  textField,
  numberField,
  switchField,
  enumField,
  entityField,
  noneField,
} from '../../field/FieldBuilders';
import { datetimeField, dateField } from '../../field/FieldBuilders';

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
 *  2. readOnly: true                   → noneField (display-only / label)
 *  3. type === 'boolean'               → switchField
 *  4. type === 'string' && format === 'date-time'  → datetimeField
 *  5. type === 'string' && format === 'date'       → dateField
 *  6. type === 'string' && enum                    → enumField
 *  7. type === 'string' && format === 'uri' && x-resource → entityField
 *  8. type === 'integer'               → numberField (integer precision)
 *  9. type === 'number'                → numberField
 * 10. type === 'string' (default)      → textField
 * 11. type === 'array'                 → skip (multiselect — TODO SLICE-006)
 * 12. anything else                    → textField (safe fallback)
 *
 * Also sets `required: true` on fields whose name appears in schema.required.
 *
 * @pure — no React, no hooks, no side effects.
 */
export function mapHydraSchemaToFields(schema: HydraResourceSchema): Field[] {
  const fields: Field[] = [];

  for (const [propName, propSchema] of Object.entries(schema.properties)) {
    // Rule 1 — skip identity fields
    if (propName === 'id' || propName === '@id') {
      continue;
    }

    const isRequired = schema.required.includes(propName);
    const label = toLabel(propName);

    // Rule 2 — readOnly fields become display-only (label/none)
    if (propSchema.readOnly === true) {
      fields.push(noneField().name(propName).label(label).required(isRequired).build());
      continue;
    }

    // Rule 3 — boolean → switchField
    if (propSchema.type === 'boolean') {
      fields.push(switchField().name(propName).label(label).required(isRequired).build());
      continue;
    }

    // Rule 4 — date-time → datetimeField
    if (propSchema.type === 'string' && propSchema.format === 'date-time') {
      fields.push(datetimeField().name(propName).label(label).required(isRequired).build());
      continue;
    }

    // Rule 5 — date → dateField
    if (propSchema.type === 'string' && propSchema.format === 'date') {
      fields.push(dateField().name(propName).label(label).required(isRequired).build());
      continue;
    }

    // Rule 6 — enum string → enumField
    if (propSchema.type === 'string' && Array.isArray(propSchema.enum)) {
      const xLabels = propSchema['x-labels'] ?? {};
      const options = propSchema.enum.map((value) => ({
        value,
        text: xLabels[value] ?? value,
      }));
      fields.push(enumField(options).name(propName).label(label).required(isRequired).build());
      continue;
    }

    // Rule 7 — uri string with x-resource → entityField
    if (propSchema.type === 'string' && propSchema.format === 'uri' && propSchema['x-resource']) {
      fields.push(
        entityField(propSchema['x-resource'], 'id', 'name')
          .name(propName)
          .label(label)
          .required(isRequired)
          .build(),
      );
      continue;
    }

    // Rule 8 — integer → numberField
    if (propSchema.type === 'integer') {
      fields.push(
        numberField().name(propName).label(label).required(isRequired).precision(0).build(),
      );
      continue;
    }

    // Rule 9 — number → numberField
    if (propSchema.type === 'number') {
      fields.push(numberField().name(propName).label(label).required(isRequired).build());
      continue;
    }

    // Rule 11 — array → skip (TODO SLICE-006)
    if (propSchema.type === 'array') {
      continue;
    }

    // Rule 10 + 12 — string (default) and anything else → textField
    fields.push(textField().name(propName).label(label).required(isRequired).build());
  }

  return fields;
}
