/**
 * Unit tests for SLICE-005 Wave 1 (BUG-001 fix):
 *   - HydraToFieldMapper (mapHydraSchemaToFields)
 *   - openApiParser (parseHydraDoc)
 */

import { describe, it, expect } from 'vitest';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
import { parseHydraDoc } from './openApiParser';
import { FieldType } from '../../field/FieldType';
import type { HydraResourceSchema, HydraApiDoc } from './types';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

/** A realistic HydraResourceSchema covering all mapping rule branches */
const productSchema: HydraResourceSchema = {
  className: 'Product',
  apiUrl: '/api/products',
  fields: [
    {
      name: 'id',
      range: 'xmls:integer',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: true,
    },
    {
      name: 'name',
      range: 'xmls:string',
      propertyType: 'rdf:Property',
      required: true,
      readable: true,
      writeable: true,
    },
    {
      name: 'price',
      range: 'xmls:decimal',
      propertyType: 'rdf:Property',
      required: true,
      readable: true,
      writeable: true,
    },
    {
      name: 'quantity',
      range: 'xmls:integer',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: true,
    },
    {
      name: 'active',
      range: 'xmls:boolean',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: true,
    },
    {
      name: 'createdAt',
      range: 'xmls:dateTime',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: false,
    },
    {
      name: 'category',
      range: '#Category',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: true,
    },
    {
      name: 'supplier',
      range: '#Supplier',
      propertyType: 'Link',
      required: false,
      readable: true,
      writeable: true,
    },
    {
      name: 'notes',
      range: 'xmls:string',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: true,
    },
    {
      name: 'legacyCode',
      range: undefined,
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: false,
    },
    {
      name: 'unknownType',
      range: 'some:UnknownRange',
      propertyType: 'rdf:Property',
      required: false,
      readable: true,
      writeable: true,
    },
  ],
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
    const schema: HydraResourceSchema = {
      className: 'Foo',
      apiUrl: '/api/foos',
      fields: [
        {
          name: '@id',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
        {
          name: 'title',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(findField(fields, '@id')).toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// Rule 2: readable: false → skip
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 2: readable:false → skipped', () => {
  it('does not include a field with readable:false', () => {
    const schema: HydraResourceSchema = {
      className: 'Test',
      apiUrl: '/api/tests',
      fields: [
        {
          name: 'internal',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: false,
          writeable: true,
        },
        {
          name: 'visible',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(findField(fields, 'internal')).toBeUndefined();
    expect(findField(fields, 'visible')).toBeDefined();
  });
});

// ---------------------------------------------------------------------------
// Rule 3: writeable: false → noneField (display-only)
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 3: writeable:false → noneField', () => {
  it('maps a writeable:false field to FieldType.NONE', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NONE);
  });

  it('maps a writeable:false field with no range to FieldType.NONE', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'legacyCode');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NONE);
  });

  it('writeable:false field is not required even when required:false', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt');
    expect(f!.required).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// Rule 4: range=xmls:boolean → switchField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 4: xmls:boolean → switchField', () => {
  it('maps range=xmls:boolean to FieldType.SWITCH', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'active');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.SWITCH);
  });
});

// ---------------------------------------------------------------------------
// Rule 5: range=xmls:dateTime → datetimeField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 5: xmls:dateTime → datetimeField', () => {
  it('maps a writeable xmls:dateTime field to FieldType.DATETIME', () => {
    const schema: HydraResourceSchema = {
      className: 'Event',
      apiUrl: '/api/events',
      fields: [
        {
          name: 'startAt',
          range: 'xmls:dateTime',
          propertyType: 'rdf:Property',
          required: true,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].type).toBe(FieldType.DATETIME);
  });

  it('writeable:false dateTime still maps to NONE (Rule 3 takes priority)', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt'); // writeable:false in fixture
    expect(f!.type).toBe(FieldType.NONE);
  });
});

// ---------------------------------------------------------------------------
// Rule 6: range=xmls:integer → numberField with precision 0
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 6: xmls:integer → numberField', () => {
  it('maps range=xmls:integer to FieldType.NUMBER', () => {
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
// Rule 7: range=xmls:decimal → numberField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 7: xmls:decimal → numberField', () => {
  it('maps range=xmls:decimal to FieldType.NUMBER', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'price');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NUMBER);
  });
});

// ---------------------------------------------------------------------------
// Rule 8: range starts with '#' → entityField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 8: "#Class" range → entityField', () => {
  it('maps a range starting with "#" to FieldType.ENTITY', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'category');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENTITY);
  });
});

// ---------------------------------------------------------------------------
// Rule 8b: propertyType=Link → entityField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 8b: propertyType=Link → entityField', () => {
  it('maps a propertyType="Link" field to FieldType.ENTITY', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'supplier');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENTITY);
  });
});

// ---------------------------------------------------------------------------
// Rule 9: xmls:string (default) → textField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — Rule 9: xmls:string → textField', () => {
  it('maps range=xmls:string (no special format) to FieldType.TEXT', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'name');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.TEXT);
  });

  it('maps range=xmls:string (notes) to FieldType.TEXT', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'notes');
    expect(f!.type).toBe(FieldType.TEXT);
  });
});

// ---------------------------------------------------------------------------
// Rule 9 fallback: unknown range → textField
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — fallback: unknown range → textField', () => {
  it('maps an unrecognised range to FieldType.TEXT as safe fallback', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'unknownType');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.TEXT);
  });

  it('maps a field with undefined range (and writeable:true) to FieldType.TEXT', () => {
    const schema: HydraResourceSchema = {
      className: 'Misc',
      apiUrl: '/api/misc',
      fields: [
        {
          name: 'mystery',
          range: undefined,
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].type).toBe(FieldType.TEXT);
  });
});

// ---------------------------------------------------------------------------
// required flag
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — required flag', () => {
  it('sets required=true for fields with required:true', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const nameField = findField(fields, 'name');
    const priceField = findField(fields, 'price');
    expect(nameField!.required).toBe(true);
    expect(priceField!.required).toBe(true);
  });

  it('sets required=false for fields with required:false', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'active');
    expect(f!.required).toBe(false);
  });

  it('required=true propagates to entityField', () => {
    const schema: HydraResourceSchema = {
      className: 'Item',
      apiUrl: '/api/items',
      fields: [
        {
          name: 'category',
          range: '#Category',
          propertyType: 'rdf:Property',
          required: true,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].required).toBe(true);
  });

  it('required=true propagates to numberField', () => {
    const schema: HydraResourceSchema = {
      className: 'Item',
      apiUrl: '/api/items',
      fields: [
        {
          name: 'amount',
          range: 'xmls:decimal',
          propertyType: 'rdf:Property',
          required: true,
          readable: true,
          writeable: true,
        },
      ],
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
    const schema: HydraResourceSchema = {
      className: 'Event',
      apiUrl: '/api/events',
      fields: [
        {
          name: 'publishedOn',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields[0].label).toBe('Published On');
  });

  it('single-word field keeps first letter capitalised', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'name');
    expect(f!.label).toBe('Name');
  });
});

// ---------------------------------------------------------------------------
// edge cases
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — edge cases', () => {
  it('returns an empty array for a schema with no fields', () => {
    const schema: HydraResourceSchema = {
      className: 'Empty',
      apiUrl: '/api/empties',
      fields: [],
    };
    expect(mapHydraSchemaToFields(schema)).toEqual([]);
  });

  it('returns an empty array for a schema whose only field is id', () => {
    const schema: HydraResourceSchema = {
      className: 'IdOnly',
      apiUrl: '/api/id-only',
      fields: [
        {
          name: 'id',
          range: 'xmls:integer',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
    };
    expect(mapHydraSchemaToFields(schema)).toEqual([]);
  });
});

// ===========================================================================
// parseHydraDoc tests
// ===========================================================================

const validHydraDoc: HydraApiDoc = {
  '@id': '/api/docs.jsonld',
  '@type': 'ApiDocumentation',
  supportedClass: [
    {
      '@id': '#Entrypoint',
      '@type': 'Class',
      title: 'Entrypoint',
      supportedProperty: [
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Entrypoint/product',
            '@type': 'Link',
            label: 'product',
            range: '#Product',
          },
          title: 'product',
        },
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Entrypoint/category',
            '@type': 'Link',
            label: 'category',
            range: '#Category',
          },
          title: 'category',
        },
      ],
    },
    {
      '@id': '#Product',
      '@type': 'Class',
      title: 'Product',
      supportedProperty: [
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Product/name',
            '@type': 'rdf:Property',
            label: 'name',
            range: 'xmls:string',
          },
          title: 'name',
          required: true,
          readable: true,
          writeable: true,
        },
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Product/price',
            '@type': 'rdf:Property',
            label: 'price',
            range: 'xmls:decimal',
          },
          title: 'price',
          required: false,
          readable: true,
          writeable: true,
        },
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Product/active',
            '@type': 'rdf:Property',
            label: 'active',
            range: 'xmls:boolean',
          },
          title: 'active',
          required: false,
          readable: true,
          writeable: true,
        },
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Product/createdAt',
            '@type': 'rdf:Property',
            label: 'createdAt',
            range: 'xmls:dateTime',
          },
          title: 'createdAt',
          required: false,
          readable: true,
          writeable: false,
        },
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Product/category',
            '@type': 'Link',
            label: 'category',
            range: '#Category',
          },
          title: 'category',
          required: false,
          readable: true,
          writeable: true,
        },
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Product/internal',
            '@type': 'rdf:Property',
            label: 'internal',
            range: 'xmls:string',
          },
          title: 'internal',
          required: false,
          readable: false,
          writeable: true,
        },
      ],
    },
    {
      '@id': '#Category',
      '@type': 'Class',
      title: 'Category',
      supportedProperty: [
        {
          '@type': 'SupportedProperty',
          property: {
            '@id': '#Category/title',
            '@type': 'rdf:Property',
            label: 'title',
            range: 'xmls:string',
          },
          title: 'title',
          required: true,
          readable: true,
          writeable: true,
        },
      ],
    },
  ],
};

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

describe('parseHydraDoc — happy path', () => {
  it('returns a resource map with one entry per non-Entrypoint class', () => {
    const resources = parseHydraDoc(validHydraDoc);
    expect(Object.keys(resources)).toHaveLength(2);
  });

  it('resource for Product is keyed by className "Product"', () => {
    const resources = parseHydraDoc(validHydraDoc);
    expect(resources['Product']).toBeDefined();
  });

  it('resource className is "Product"', () => {
    const resources = parseHydraDoc(validHydraDoc);
    expect(resources['Product'].className).toBe('Product');
  });

  it('apiUrl for Product is derived from Entrypoint property "#Entrypoint/product"', () => {
    const resources = parseHydraDoc(validHydraDoc);
    expect(resources['Product'].apiUrl).toBe('/api/products');
  });

  it('apiUrl for Category is derived from Entrypoint property "#Entrypoint/category"', () => {
    const resources = parseHydraDoc(validHydraDoc);
    // deriveApiUrl: "category" → snake "category" → consonant+'y' ending → "categories"
    expect(resources['Category'].apiUrl).toBe('/api/categories');
  });

  it('fields array includes all readable properties', () => {
    const resources = parseHydraDoc(validHydraDoc);
    const fieldNames = resources['Product'].fields.map((f) => f.name);
    expect(fieldNames).toContain('name');
    expect(fieldNames).toContain('price');
    expect(fieldNames).toContain('active');
    expect(fieldNames).toContain('createdAt');
    expect(fieldNames).toContain('category');
  });

  it('readable:false fields are excluded from the fields array', () => {
    const resources = parseHydraDoc(validHydraDoc);
    const fieldNames = resources['Product'].fields.map((f) => f.name);
    expect(fieldNames).not.toContain('internal');
  });

  it('field schemas preserve range information', () => {
    const resources = parseHydraDoc(validHydraDoc);
    const nameField = resources['Product'].fields.find((f) => f.name === 'name');
    const priceField = resources['Product'].fields.find((f) => f.name === 'price');
    const activeField = resources['Product'].fields.find((f) => f.name === 'active');
    expect(nameField?.range).toBe('xmls:string');
    expect(priceField?.range).toBe('xmls:decimal');
    expect(activeField?.range).toBe('xmls:boolean');
  });

  it('field schemas preserve propertyType information', () => {
    const resources = parseHydraDoc(validHydraDoc);
    const categoryField = resources['Product'].fields.find((f) => f.name === 'category');
    expect(categoryField?.propertyType).toBe('Link');
  });

  it('field schemas preserve required flag', () => {
    const resources = parseHydraDoc(validHydraDoc);
    const nameField = resources['Product'].fields.find((f) => f.name === 'name');
    expect(nameField?.required).toBe(true);
  });

  it('field schemas preserve writeable flag', () => {
    const resources = parseHydraDoc(validHydraDoc);
    const createdAt = resources['Product'].fields.find((f) => f.name === 'createdAt');
    expect(createdAt?.writeable).toBe(false);
  });

  it('falls back to /api/${className}s when no Entrypoint entry found for class', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Widget',
          '@type': 'Class',
          title: 'Widget',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Widget/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'name',
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    expect(resources['Widget']).toBeDefined();
    expect(resources['Widget'].apiUrl).toBe('/api/widgets');
  });

  it('Entrypoint class itself is not included in the resource map', () => {
    const resources = parseHydraDoc(validHydraDoc);
    expect(resources['Entrypoint']).toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// deriveApiUrl — URL derivation from camelCase Entrypoint property names
// ---------------------------------------------------------------------------

describe('parseHydraDoc — apiUrl derivation', () => {
  function makeDocWithEntrypoint(propId: string, range: string): HydraApiDoc {
    return {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Entrypoint',
          '@type': 'Class',
          title: 'Entrypoint',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: { '@id': propId, '@type': 'Link', label: 'x', range },
              title: 'x',
            },
          ],
        },
        {
          '@id': range,
          '@type': 'Class',
          title: range.replace('#', ''),
          supportedProperty: [],
        },
      ],
    };
  }

  it('derives /api/branches from "#Entrypoint/branch"', () => {
    const doc = makeDocWithEntrypoint('#Entrypoint/branch', '#Branch');
    // deriveApiUrl: "branch" → snake "branch" → ends in 'ch' → "branches"
    expect(parseHydraDoc(doc)['Branch'].apiUrl).toBe('/api/branches');
  });

  it('derives /api/cash_movement_categories from "#Entrypoint/cashMovementCategory"', () => {
    const doc = makeDocWithEntrypoint('#Entrypoint/cashMovementCategory', '#CashMovementCategory');
    expect(parseHydraDoc(doc)['CashMovementCategory'].apiUrl).toBe('/api/cash_movement_categories');
  });

  it('derives /api/products from "#Entrypoint/product"', () => {
    const doc = makeDocWithEntrypoint('#Entrypoint/product', '#Product');
    expect(parseHydraDoc(doc)['Product'].apiUrl).toBe('/api/products');
  });
});

// ---------------------------------------------------------------------------
// parseHydraDoc — fields shape
// ---------------------------------------------------------------------------

describe('parseHydraDoc — fields shape', () => {
  it('each field has a name string', () => {
    const resources = parseHydraDoc(validHydraDoc);
    for (const field of resources['Product'].fields) {
      expect(typeof field.name).toBe('string');
    }
  });

  it('each field has a propertyType string', () => {
    const resources = parseHydraDoc(validHydraDoc);
    for (const field of resources['Product'].fields) {
      expect(typeof field.propertyType).toBe('string');
    }
  });

  it('each field has a boolean required flag', () => {
    const resources = parseHydraDoc(validHydraDoc);
    for (const field of resources['Product'].fields) {
      expect(typeof field.required).toBe('boolean');
    }
  });

  it('each field has a boolean readable flag set to true (non-readable filtered out)', () => {
    const resources = parseHydraDoc(validHydraDoc);
    for (const field of resources['Product'].fields) {
      expect(field.readable).toBe(true);
    }
  });

  it('each field has a boolean writeable flag', () => {
    const resources = parseHydraDoc(validHydraDoc);
    for (const field of resources['Product'].fields) {
      expect(typeof field.writeable).toBe('boolean');
    }
  });

  it('a field with missing readable defaults to readable:true (not filtered)', () => {
    // SupportedProperty with no readable property should default to readable
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Thing',
          '@type': 'Class',
          title: 'Thing',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Thing/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'name',
              // no readable field — defaults to true per spec
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    expect(resources['Thing'].fields).toHaveLength(1);
    expect(resources['Thing'].fields[0].readable).toBe(true);
  });

  it('a field with missing writeable defaults to writeable:true', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Thing',
          '@type': 'Class',
          title: 'Thing',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Thing/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'name',
              // no writeable field — defaults to true per spec
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    expect(resources['Thing'].fields[0].writeable).toBe(true);
  });

  it('a field with missing required defaults to required:false', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Thing',
          '@type': 'Class',
          title: 'Thing',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Thing/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'name',
              // no required field — defaults to false per spec
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    expect(resources['Thing'].fields[0].required).toBe(false);
  });
});
