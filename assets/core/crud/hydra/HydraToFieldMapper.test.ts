/**
 * Unit tests for SLICE-005 Wave 1:
 *   - HydraToFieldMapper (mapHydraSchemaToFields)
 *   - openApiParser (parseOpenApiSpec)
 */

import { describe, it, expect } from 'vitest';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
import { parseOpenApiSpec } from './openApiParser';
import { FieldType } from '../../field/FieldType';
import type { HydraResourceSchema } from './types';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** A realistic HydraResourceSchema covering all mapping rule branches */
const productSchema: HydraResourceSchema = {
  name: 'Product',
  apiUrl: '/api/products',
  required: ['name', 'price'],
  properties: {
    id: { type: 'integer', readOnly: true },
    '@id': { type: 'string', format: 'uri', readOnly: true },
    name: { type: 'string' },
    price: { type: 'number' },
    quantity: { type: 'integer' },
    active: { type: 'boolean' },
    createdAt: { type: 'string', format: 'date-time', readOnly: true },
    publishedOn: { type: 'string', format: 'date' },
    status: {
      type: 'string',
      enum: ['active', 'inactive', 'pending'],
      'x-labels': { active: 'Activo', inactive: 'Inactivo', pending: 'Pendiente' },
    },
    statusNoLabels: {
      type: 'string',
      enum: ['a', 'b'],
    },
    category: {
      type: 'string',
      format: 'uri',
      'x-resource': '/api/categories',
    },
    tags: { type: 'array', items: { type: 'string' } },
    notes: { type: 'string' },
    legacyCode: { readOnly: true }, // readOnly but no type
    unknownType: { type: 'object' }, // falls through to textField
  },
};

// ---------------------------------------------------------------------------
// Helper: find a field by name in the mapped output
// ---------------------------------------------------------------------------
function findField(fields: ReturnType<typeof mapHydraSchemaToFields>, name: string) {
  return fields.find((f) => f.name === name);
}

// ---------------------------------------------------------------------------
// mapHydraSchemaToFields — Rule 1: skip id / @id
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 1: skip id and @id', () => {
  it('does not include a field named "id"', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'id')).toBeUndefined();
  });

  it('does not include a field named "@id"', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, '@id')).toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// Rule 2: readOnly → noneField (display-only)
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 2: readOnly → noneField', () => {
  it('maps a readOnly field (with type) to FieldType.NONE', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NONE);
  });

  it('maps a readOnly field (without type) to FieldType.NONE', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'legacyCode');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NONE);
  });

  it('readOnly field is not required even when it is not in required[]', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt');
    expect(f!.required).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// Rule 3: boolean → switchField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 3: boolean → switchField', () => {
  it('maps type=boolean to FieldType.SWITCH', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'active');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.SWITCH);
  });
});

// ---------------------------------------------------------------------------
// Rule 4: string + format=date-time → datetimeField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 4: date-time → datetimeField', () => {
  it('maps a non-readOnly string date-time field to FieldType.DATETIME', () => {
    const schema: HydraResourceSchema = {
      name: 'Event',
      apiUrl: '/api/events',
      required: ['startAt'],
      properties: {
        startAt: { type: 'string', format: 'date-time' },
      },
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].type).toBe(FieldType.DATETIME);
  });

  it('readOnly date-time still maps to NONE (Rule 2 takes priority)', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt'); // readOnly in fixture
    expect(f!.type).toBe(FieldType.NONE);
  });
});

// ---------------------------------------------------------------------------
// Rule 5: string + format=date → dateField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 5: date → dateField', () => {
  it('maps type=string, format=date to FieldType.DATE', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'publishedOn');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.DATE);
  });
});

// ---------------------------------------------------------------------------
// Rule 6: string + enum → enumField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 6: string + enum → enumField', () => {
  it('maps type=string with enum to FieldType.ENUM', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'status');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENUM);
  });

  it('enum field data array uses x-labels as text when present', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'status');
    expect(f!.data).toEqual([
      { value: 'active', text: 'Activo' },
      { value: 'inactive', text: 'Inactivo' },
      { value: 'pending', text: 'Pendiente' },
    ]);
  });

  it('enum field without x-labels uses value as text', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'statusNoLabels');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENUM);
    expect(f!.data).toEqual([
      { value: 'a', text: 'a' },
      { value: 'b', text: 'b' },
    ]);
  });
});

// ---------------------------------------------------------------------------
// Rule 7: string + format=uri + x-resource → entityField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 7: string + uri + x-resource → entityField', () => {
  it('maps a resource-reference field to FieldType.ENTITY', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'category');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENTITY);
  });

  it('entity field url is set from x-resource value', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'category');
    expect(f!.url).toBe('/api/categories');
  });

  it('entity field without x-resource but with uri format becomes a textField fallback', () => {
    const schema: HydraResourceSchema = {
      name: 'Foo',
      apiUrl: '/api/foos',
      required: [],
      properties: {
        relatedUrl: { type: 'string', format: 'uri' }, // no x-resource
      },
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].type).toBe(FieldType.TEXT);
  });
});

// ---------------------------------------------------------------------------
// Rule 8: integer → numberField with precision 0
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 8: integer → numberField', () => {
  it('maps type=integer to FieldType.NUMBER', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'quantity');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NUMBER);
  });

  it('integer field has precision 0', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'quantity');
    expect(f!.precision).toBe(0);
  });
});

// ---------------------------------------------------------------------------
// Rule 9: number → numberField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 9: number → numberField', () => {
  it('maps type=number to FieldType.NUMBER', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'price');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NUMBER);
  });
});

// ---------------------------------------------------------------------------
// Rule 10: string (default) → textField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 10: plain string → textField', () => {
  it('maps type=string (no format, no enum) to FieldType.TEXT', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'name');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.TEXT);
  });

  it('maps type=string (notes) to FieldType.TEXT', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'notes');
    expect(f!.type).toBe(FieldType.TEXT);
  });
});

// ---------------------------------------------------------------------------
// Rule 11: array → skip
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 11: array is skipped', () => {
  it('does not include array-type fields in the output', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'tags')).toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// Rule 12: unknown type → textField fallback
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 12: unknown type → textField fallback', () => {
  it('maps type=object to FieldType.TEXT as safe fallback', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'unknownType');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.TEXT);
  });

  it('maps a property with no type to FieldType.TEXT as safe fallback', () => {
    const schema: HydraResourceSchema = {
      name: 'Misc',
      apiUrl: '/api/misc',
      required: [],
      properties: {
        mystery: {}, // no type, no readOnly
      },
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].type).toBe(FieldType.TEXT);
  });
});

// ---------------------------------------------------------------------------
// required flag
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — required flag', () => {
  it('sets required=true for fields listed in schema.required', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const nameField = findField(fields, 'name');
    const priceField = findField(fields, 'price');
    expect(nameField!.required).toBe(true);
    expect(priceField!.required).toBe(true);
  });

  it('sets required=false for fields NOT in schema.required', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'active');
    expect(f!.required).toBe(false);
  });

  it('required=true propagates to enumField', () => {
    const schema: HydraResourceSchema = {
      name: 'Item',
      apiUrl: '/api/items',
      required: ['status'],
      properties: {
        status: { type: 'string', enum: ['a', 'b'] },
      },
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].required).toBe(true);
  });

  it('required=true propagates to entityField', () => {
    const schema: HydraResourceSchema = {
      name: 'Item',
      apiUrl: '/api/items',
      required: ['category'],
      properties: {
        category: { type: 'string', format: 'uri', 'x-resource': '/api/categories' },
      },
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].required).toBe(true);
  });
});

// ---------------------------------------------------------------------------
// label generation
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — label generation', () => {
  it('camelCase field name is converted to a human-readable label', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'publishedOn');
    expect(f!.label).toBe('Published On');
  });

  it('single-word field keeps first letter capitalised', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'name');
    expect(f!.label).toBe('Name');
  });
});

// ---------------------------------------------------------------------------
// empty schema
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — edge cases', () => {
  it('returns an empty array for a schema with no properties', () => {
    const schema: HydraResourceSchema = {
      name: 'Empty',
      apiUrl: '/api/empties',
      required: [],
      properties: {},
    };
    expect(mapHydraSchemaToFields(schema)).toEqual([]);
  });

  it('returns an empty array for a schema whose only property is id', () => {
    const schema: HydraResourceSchema = {
      name: 'IdOnly',
      apiUrl: '/api/id-only',
      required: [],
      properties: {
        id: { type: 'integer' },
      },
    };
    expect(mapHydraSchemaToFields(schema)).toEqual([]);
  });
});

// ===========================================================================
// parseOpenApiSpec tests
// ===========================================================================

const validDoc = {
  openapi: '3.1.0',
  paths: {
    '/api/products': {},
    '/api/products/{id}': {},
    '/api/categories': {},
    '/api/categories/{id}': {},
  },
  components: {
    schemas: {
      Product: {
        type: 'object',
        properties: {
          id: { type: 'integer', readOnly: true },
          name: { type: 'string' },
          price: { type: 'number' },
          active: { type: 'boolean' },
          createdAt: { type: 'string', format: 'date-time' },
          status: {
            type: 'string',
            enum: ['active', 'inactive'],
            'x-labels': { active: 'Activo', inactive: 'Inactivo' },
          },
          category: { type: 'string', format: 'uri', 'x-resource': '/api/categories' },
        },
        required: ['name'],
      },
      Category: {
        type: 'object',
        properties: {
          id: { type: 'integer', readOnly: true },
          title: { type: 'string' },
        },
        required: ['title'],
      },
    },
  },
};

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

describe('parseOpenApiSpec — happy path', () => {
  it('returns a HydraApiSpec with the correct openapi version', () => {
    const spec = parseOpenApiSpec(validDoc);
    expect(spec.version).toBe('3.1.0');
  });

  it('produces one resource per schema in components.schemas', () => {
    const spec = parseOpenApiSpec(validDoc);
    expect(Object.keys(spec.resources)).toHaveLength(2);
  });

  it('resource for Product is keyed by its resolved apiUrl', () => {
    const spec = parseOpenApiSpec(validDoc);
    expect(spec.resources['/api/products']).toBeDefined();
  });

  it('resource name is the schema key ("Product")', () => {
    const spec = parseOpenApiSpec(validDoc);
    expect(spec.resources['/api/products'].name).toBe('Product');
  });

  it('resource required array contains the correct field names', () => {
    const spec = parseOpenApiSpec(validDoc);
    expect(spec.resources['/api/products'].required).toContain('name');
  });

  it('resource properties include all defined fields', () => {
    const spec = parseOpenApiSpec(validDoc);
    const props = spec.resources['/api/products'].properties;
    expect(props['id']).toBeDefined();
    expect(props['name']).toBeDefined();
    expect(props['price']).toBeDefined();
    expect(props['active']).toBeDefined();
    expect(props['createdAt']).toBeDefined();
    expect(props['status']).toBeDefined();
    expect(props['category']).toBeDefined();
  });

  it('property schemas preserve type information', () => {
    const spec = parseOpenApiSpec(validDoc);
    const props = spec.resources['/api/products'].properties;
    expect(props['name'].type).toBe('string');
    expect(props['price'].type).toBe('number');
    expect(props['active'].type).toBe('boolean');
  });

  it('property schemas preserve enum and x-labels', () => {
    const spec = parseOpenApiSpec(validDoc);
    const statusProp = spec.resources['/api/products'].properties['status'];
    expect(statusProp.enum).toEqual(['active', 'inactive']);
    expect(statusProp['x-labels']).toEqual({ active: 'Activo', inactive: 'Inactivo' });
  });

  it('property schemas preserve readOnly flag', () => {
    const spec = parseOpenApiSpec(validDoc);
    const idProp = spec.resources['/api/products'].properties['id'];
    expect(idProp.readOnly).toBe(true);
  });

  it('property schemas preserve x-resource', () => {
    const spec = parseOpenApiSpec(validDoc);
    const catProp = spec.resources['/api/products'].properties['category'];
    expect(catProp['x-resource']).toBe('/api/categories');
  });

  it('falls back to /api/${name}s when no matching path is found', () => {
    const doc = {
      openapi: '3.0.0',
      components: {
        schemas: {
          Widget: {
            type: 'object',
            properties: { name: { type: 'string' } },
            required: [],
          },
        },
      },
    };
    const spec = parseOpenApiSpec(doc);
    expect(spec.resources['/api/widgets']).toBeDefined();
  });

  it('empty required array is preserved as []', () => {
    const spec = parseOpenApiSpec(validDoc);
    expect(spec.resources['/api/categories'].required).toEqual(['title']);
  });
});

// ---------------------------------------------------------------------------
// Error path
// ---------------------------------------------------------------------------

describe('parseOpenApiSpec — error path', () => {
  it('throws a descriptive Error when doc is null', () => {
    expect(() => parseOpenApiSpec(null)).toThrowError(/parseOpenApiSpec/);
  });

  it('throws a descriptive Error when doc is a string', () => {
    expect(() => parseOpenApiSpec('not an object')).toThrowError(/parseOpenApiSpec/);
  });

  it('throws a descriptive Error when doc is missing "openapi" key', () => {
    const bad = { components: { schemas: {} } };
    expect(() => parseOpenApiSpec(bad)).toThrowError(/"openapi"/);
  });

  it('throws a descriptive Error when doc is missing "components"', () => {
    const bad = { openapi: '3.1.0' };
    expect(() => parseOpenApiSpec(bad)).toThrowError(/"components"/);
  });

  it('throws a descriptive Error when "components.schemas" is missing', () => {
    const bad = { openapi: '3.1.0', components: {} };
    expect(() => parseOpenApiSpec(bad)).toThrowError(/"components.schemas"/);
  });

  it('throws a descriptive Error when doc is an array', () => {
    expect(() => parseOpenApiSpec([])).toThrowError(/parseOpenApiSpec/);
  });

  it('throws a descriptive Error when doc is a number', () => {
    expect(() => parseOpenApiSpec(42)).toThrowError(/parseOpenApiSpec/);
  });
});
