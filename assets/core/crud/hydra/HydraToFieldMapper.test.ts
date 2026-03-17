/**
 * Unit tests for HydraToFieldMapper (mapHydraSchemaToFields) and openApiParser (parseHydraDoc).
 */

import { describe, it, expect } from 'vitest';
import { mapHydraSchemaToFields } from './HydraToFieldMapper';
import { parseHydraDoc } from './openApiParser';
import { FieldType } from '../../field/FieldType';
import type { HydraResourceSchema, HydraApiDoc, CrudHints } from './types';

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

function findField(fields: ReturnType<typeof mapHydraSchemaToFields>, name: string) {
  return fields.find((f) => f.name === name);
}

describe('mapHydraSchemaToFields — Rule 1: id and @id always emitted as hidden identity field', () => {
  it('includes a field named "id" in the output', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'id')).toBeDefined();
  });

  it('"id" field has visible: false (not rendered as a column)', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'id')!.visible).toBe(false);
  });

  it('"id" field has readonly: true (not editable in DxForm)', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'id')!.readonly).toBe(true);
  });

  it('"id" field has isIdentity: true', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'id')!.isIdentity).toBe(true);
  });

  it('"id" field has visibleOnForm: false (not rendered in DxForm)', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    expect(findField(fields, 'id')!.visibleOnForm).toBe(false);
  });

  it('includes a field named "@id" as hidden identity when schema uses @id', () => {
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
    // Note: @id is normalised to "id" in our emitted field
    const fields = mapHydraSchemaToFields(schema);
    const idField = findField(fields, 'id');
    expect(idField).toBeDefined();
    expect(idField!.visible).toBe(false);
    expect(idField!.isIdentity).toBe(true);
  });
});

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

describe('mapHydraSchemaToFields — Rule 4: xmls:boolean → switchField', () => {
  it('maps range=xmls:boolean to FieldType.SWITCH', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'active');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.SWITCH);
  });
});

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
    const f = fields.find((x) => x.name === 'startAt');
    expect(f!.type).toBe(FieldType.DATETIME);
  });

  it('writeable:false dateTime still maps to NONE (Rule 3 takes priority)', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'createdAt'); // writeable:false in fixture
    expect(f!.type).toBe(FieldType.NONE);
  });
});

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

describe('mapHydraSchemaToFields — Rule 7: xmls:decimal → numberField', () => {
  it('maps range=xmls:decimal to FieldType.NUMBER', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'price');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.NUMBER);
  });
});

describe('mapHydraSchemaToFields — Rule 8: "#Class" range → entityField', () => {
  it('maps a range starting with "#" to FieldType.ENTITY', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'category');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENTITY);
  });
});

describe('mapHydraSchemaToFields — Rule 8b: propertyType=Link → entityField', () => {
  it('maps a propertyType="Link" field to FieldType.ENTITY', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'supplier');
    expect(f).toBeDefined();
    expect(f!.type).toBe(FieldType.ENTITY);
  });
});

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
    const f = fields.find((x) => x.name === 'mystery');
    expect(f!.type).toBe(FieldType.TEXT);
  });
});

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
    const f = fields.find((x) => x.name === 'category');
    expect(f!.required).toBe(true);
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
    const f = fields.find((x) => x.name === 'amount');
    expect(f!.required).toBe(true);
  });
});

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
    const f = fields.find((x) => x.name === 'publishedOn');
    expect(f!.label).toBe('Published On');
  });

  it('single-word field keeps first letter capitalised', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const f = findField(fields, 'name');
    expect(f!.label).toBe('Name');
  });
});

describe('mapHydraSchemaToFields — edge cases', () => {
  it('returns only the synthetic hidden id field for a schema with no fields', () => {
    const schema: HydraResourceSchema = {
      className: 'Empty',
      apiUrl: '/api/empties',
      fields: [],
    };
    const fields = mapHydraSchemaToFields(schema);
    expect(fields).toHaveLength(1);
    expect(fields[0].name).toBe('id');
    expect(fields[0].isIdentity).toBe(true);
    expect(fields[0].visible).toBe(false);
  });

  it('returns only the hidden id field for a schema whose only field is id', () => {
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
    const fields = mapHydraSchemaToFields(schema);
    expect(fields).toHaveLength(1);
    expect(fields[0].name).toBe('id');
    expect(fields[0].isIdentity).toBe(true);
    expect(fields[0].visible).toBe(false);
  });
});

describe('mapHydraSchemaToFields — Rule 8: entityField URL pluralization', () => {
  function makeEntitySchema(range: string): HydraResourceSchema {
    return {
      className: 'Host',
      apiUrl: '/api/hosts',
      fields: [
        {
          name: 'ref',
          range,
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
    };
  }

  it('Category → /api/categories (consonant+y → ies)', () => {
    const fields = mapHydraSchemaToFields(makeEntitySchema('#Category'));
    const f = fields.find((x) => x.name === 'ref');
    expect(f!.type).toBe(FieldType.ENTITY);
    expect(f!.url).toBe('/api/categories');
  });

  it('Branch → /api/branches (ch → es)', () => {
    const fields = mapHydraSchemaToFields(makeEntitySchema('#Branch'));
    const f = fields.find((x) => x.name === 'ref');
    expect(f!.type).toBe(FieldType.ENTITY);
    expect(f!.url).toBe('/api/branches');
  });

  it('Product → /api/products (regular → s)', () => {
    const fields = mapHydraSchemaToFields(makeEntitySchema('#Product'));
    const f = fields.find((x) => x.name === 'ref');
    expect(f!.type).toBe(FieldType.ENTITY);
    expect(f!.url).toBe('/api/products');
  });
});

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

describe('mapHydraSchemaToFields — filterable from hydra:search mappings', () => {
  /** Schema with searchMappings for "name" and "status" */
  const schemaWithSearch: HydraResourceSchema = {
    className: 'Item',
    apiUrl: '/api/items',
    fields: [
      {
        name: 'name',
        range: 'xmls:string',
        propertyType: 'rdf:Property',
        required: false,
        readable: true,
        writeable: true,
      },
      {
        name: 'status',
        range: 'xmls:string',
        propertyType: 'rdf:Property',
        required: false,
        readable: true,
        writeable: true,
      },
      {
        name: 'description',
        range: 'xmls:string',
        propertyType: 'rdf:Property',
        required: false,
        readable: true,
        writeable: true,
      },
    ],
    searchMappings: [
      { property: 'name', variable: 'name', required: false },
      { property: 'status', variable: 'status', required: false },
    ],
  };

  it('field with matching searchMapping has filterable: true', () => {
    const fields = mapHydraSchemaToFields(schemaWithSearch);
    const nameField = fields.find((f) => f.name === 'name');
    expect(nameField).toBeDefined();
    expect(nameField!.filterable).toBe(true);
  });

  it('second field with matching searchMapping also has filterable: true', () => {
    const fields = mapHydraSchemaToFields(schemaWithSearch);
    const statusField = fields.find((f) => f.name === 'status');
    expect(statusField).toBeDefined();
    expect(statusField!.filterable).toBe(true);
  });

  it('field without a matching searchMapping has filterable: false', () => {
    const fields = mapHydraSchemaToFields(schemaWithSearch);
    const descField = fields.find((f) => f.name === 'description');
    expect(descField).toBeDefined();
    expect(descField!.filterable).toBe(false);
  });

  it('all fields are filterable: true when schema has no searchMappings', () => {
    const schemaNoSearch: HydraResourceSchema = {
      className: 'Thing',
      apiUrl: '/api/things',
      fields: [
        {
          name: 'title',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
      // searchMappings absent
    };
    const fields = mapHydraSchemaToFields(schemaNoSearch);
    const titleField = fields.find((f) => f.name === 'title');
    expect(titleField).toBeDefined();
    expect(titleField!.filterable).toBe(true);
  });

  it('all fields are filterable: true when searchMappings is an empty array', () => {
    const schemaEmptySearch: HydraResourceSchema = {
      className: 'Thing',
      apiUrl: '/api/things',
      fields: [
        {
          name: 'title',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
      searchMappings: [],
    };
    const fields = mapHydraSchemaToFields(schemaEmptySearch);
    const titleField = fields.find((f) => f.name === 'title');
    expect(titleField).toBeDefined();
    expect(titleField!.filterable).toBe(true);
  });

  it('filterable:true is set correctly on non-text field types (e.g. integer)', () => {
    const schema: HydraResourceSchema = {
      className: 'Price',
      apiUrl: '/api/prices',
      fields: [
        {
          name: 'amount',
          range: 'xmls:integer',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
        },
      ],
      searchMappings: [{ property: 'amount', variable: 'amount', required: false }],
    };
    const fields = mapHydraSchemaToFields(schema);
    const amountField = fields.find((f) => f.name === 'amount');
    expect(amountField).toBeDefined();
    expect(amountField!.filterable).toBe(true);
    expect(amountField!.type).toBe(FieldType.NUMBER);
  });
});

// ---------------------------------------------------------------------------
// BUG-010 — synthetic id field injection when schema has no explicit id
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — BUG-010: synthetic id injection', () => {
  it('schema with no explicit id field still emits an id field', () => {
    const schema: HydraResourceSchema = {
      className: 'NoId',
      apiUrl: '/api/no-ids',
      fields: [
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
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
  });

  it('synthetic id field is marked visible: false', () => {
    const schema: HydraResourceSchema = {
      className: 'NoId',
      apiUrl: '/api/no-ids',
      fields: [
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
    const idField = fields.find((f) => f.name === 'id');
    expect(idField!.visible).toBe(false);
  });

  it('synthetic id field is marked readOnly: true', () => {
    const schema: HydraResourceSchema = {
      className: 'NoId',
      apiUrl: '/api/no-ids',
      fields: [
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
    const idField = fields.find((f) => f.name === 'id');
    expect(idField!.readonly).toBe(true);
  });

  it('synthetic id field is marked isIdentity: true', () => {
    const schema: HydraResourceSchema = {
      className: 'NoId',
      apiUrl: '/api/no-ids',
      fields: [
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
    const idField = fields.find((f) => f.name === 'id');
    expect(idField!.isIdentity).toBe(true);
  });

  it('synthetic id field is marked visibleOnForm: false', () => {
    const schema: HydraResourceSchema = {
      className: 'NoId',
      apiUrl: '/api/no-ids',
      fields: [
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
    const idField = fields.find((f) => f.name === 'id');
    expect(idField!.visibleOnForm).toBe(false);
  });

  it('synthetic id is prepended (first field) when not in schema', () => {
    const schema: HydraResourceSchema = {
      className: 'NoId',
      apiUrl: '/api/no-ids',
      fields: [
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
    expect(fields[0].name).toBe('id');
    expect(fields[0].isIdentity).toBe(true);
  });

  it('explicit id in schema is emitted with visible: false and readOnly: true', () => {
    // productSchema has name='id', range='xmls:integer'
    const fields = mapHydraSchemaToFields(productSchema);
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.visible).toBe(false);
    expect(idField!.readonly).toBe(true);
    expect(idField!.isIdentity).toBe(true);
  });

  it('explicit id in schema is not duplicated when schema already has id', () => {
    const fields = mapHydraSchemaToFields(productSchema);
    const idFields = fields.filter((f) => f.name === 'id');
    expect(idFields).toHaveLength(1);
  });
});

// ---------------------------------------------------------------------------
// BUG-011 — id field type depends on declared range; safe default is string
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — BUG-011: id field type resolution', () => {
  /** Build a minimal schema with an explicit id property at the given range */
  function schemaWithIdRange(range: string | undefined): HydraResourceSchema {
    return {
      className: 'Item',
      apiUrl: '/api/items',
      fields: [
        {
          name: 'id',
          range,
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
  }

  it('explicit id with range xmls:integer → identity field has type "number"', () => {
    const fields = mapHydraSchemaToFields(schemaWithIdRange('xmls:integer'));
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.type).toBe(FieldType.NUMBER);
    expect(idField!.isIdentity).toBe(true);
  });

  it('explicit id with range xmls:string → identity field has type "text"', () => {
    const fields = mapHydraSchemaToFields(schemaWithIdRange('xmls:string'));
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.type).toBe(FieldType.TEXT);
    expect(idField!.isIdentity).toBe(true);
  });

  it('explicit id with no declared range → identity field has type "text" (safe default)', () => {
    const fields = mapHydraSchemaToFields(schemaWithIdRange(undefined));
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.type).toBe(FieldType.TEXT);
    expect(idField!.isIdentity).toBe(true);
  });

  it('schema with NO id property → synthetic injected id has type "text" (never number)', () => {
    const schema: HydraResourceSchema = {
      className: 'Category',
      apiUrl: '/api/categories',
      fields: [
        {
          name: 'name',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: true,
          readable: true,
          writeable: true,
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.type).toBe(FieldType.TEXT);
    expect(idField!.isIdentity).toBe(true);
    // UUID / string keys must not be coerced to NaN by DevExtreme
    expect(idField!.type).not.toBe(FieldType.NUMBER);
  });

  it('explicit id with full XSD IRI integer range → identity field has type "number"', () => {
    const fields = mapHydraSchemaToFields(
      schemaWithIdRange('http://www.w3.org/2001/XMLSchema#integer'),
    );
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.type).toBe(FieldType.NUMBER);
    expect(idField!.isIdentity).toBe(true);
  });

  it('explicit id with anyURI range → identity field has type "text" (UUID-safe default)', () => {
    const fields = mapHydraSchemaToFields(schemaWithIdRange('xmls:anyURI'));
    const idField = fields.find((f) => f.name === 'id');
    expect(idField).toBeDefined();
    expect(idField!.type).toBe(FieldType.TEXT);
    expect(idField!.isIdentity).toBe(true);
  });
});

// ---------------------------------------------------------------------------
// SLICE-002 — hydra:title label preference
// ---------------------------------------------------------------------------

describe('mapHydraSchemaToFields — SLICE-002: hydra:title label preference', () => {
  it('uses hydra:title as the field label when present and non-empty', () => {
    const schema: HydraResourceSchema = {
      className: 'Category',
      apiUrl: '/api/categories',
      fields: [
        {
          name: 'name',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
          'hydra:title': 'Nombre',
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'name');
    expect(f).toBeDefined();
    expect(f!.label).toBe('Nombre');
  });

  it('falls back to capitalised property name when hydra:title is absent', () => {
    const schema: HydraResourceSchema = {
      className: 'Category',
      apiUrl: '/api/categories',
      fields: [
        {
          name: 'name',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
          // no 'hydra:title'
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'name');
    expect(f).toBeDefined();
    expect(f!.label).toBe('Name');
  });

  it('falls back to capitalised property name when hydra:title is an empty string', () => {
    const schema: HydraResourceSchema = {
      className: 'Category',
      apiUrl: '/api/categories',
      fields: [
        {
          name: 'name',
          range: 'xmls:string',
          propertyType: 'rdf:Property',
          required: false,
          readable: true,
          writeable: true,
          'hydra:title': '',
        },
      ],
    };
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'name');
    expect(f).toBeDefined();
    expect(f!.label).toBe('Name');
  });
});

// ---------------------------------------------------------------------------
// SLICE-002 integration — parseHydraDoc picks up the translated title from the
// OUTER SupportedProperty wrapper, not from the inner property.label
// ---------------------------------------------------------------------------

describe('parseHydraDoc — SLICE-002 integration: outer title flows to hydra:title', () => {
  it('parseHydraDoc copies the outer SupportedProperty title into field hydra:title', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Category',
          '@type': 'Class',
          title: 'Category',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                // inner property — only has the raw technical name as label
                '@id': '#Category/name',
                '@type': 'rdf:Property',
                label: 'name', // ← raw name, NOT translated
                range: 'xmls:string',
              },
              // outer SupportedProperty — has the translated title
              title: 'Nombre', // ← translated label we want
              required: false,
              readable: true,
              writeable: true,
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    const nameField = resources['Category'].fields.find((f) => f.name === 'name');
    expect(nameField).toBeDefined();
    expect(nameField!['hydra:title']).toBe('Nombre');
  });

  it('end-to-end: parseHydraDoc + mapHydraSchemaToFields produces label "Nombre"', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Category',
          '@type': 'Class',
          title: 'Category',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Category/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'Nombre',
              required: false,
              readable: true,
              writeable: true,
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    const fields = mapHydraSchemaToFields(resources['Category']);
    const nameField = fields.find((f) => f.name === 'name');
    expect(nameField).toBeDefined();
    expect(nameField!.label).toBe('Nombre');
  });

  it('inner property.label does NOT leak into field.label (only outer title is used)', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Category',
          '@type': 'Class',
          title: 'Category',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Category/name',
                '@type': 'rdf:Property',
                label: 'name', // raw technical name — must NOT become the label
                range: 'xmls:string',
              },
              title: 'Nombre', // translated — must become the label
              required: false,
              readable: true,
              writeable: true,
            },
          ],
        },
      ],
    };
    const resources = parseHydraDoc(doc);
    const fields = mapHydraSchemaToFields(resources['Category']);
    const nameField = fields.find((f) => f.name === 'name');
    expect(nameField).toBeDefined();
    // The label must be the translated outer title, not the raw inner label
    expect(nameField!.label).toBe('Nombre');
    expect(nameField!.label).not.toBe('name');
  });
});

// ---------------------------------------------------------------------------
// SLICE-004 T2 — x-crud hints override inferred field values
// ---------------------------------------------------------------------------

/** Minimal helper to build a HydraResourceSchema with one non-id field */
function makeSchemaWithHints(
  fieldName: string,
  hints: CrudHints | undefined,
  searchMappings?: HydraResourceSchema['searchMappings'],
): HydraResourceSchema {
  return {
    className: 'Widget',
    apiUrl: '/api/widgets',
    fields: [
      {
        name: fieldName,
        range: 'xmls:string',
        propertyType: 'rdf:Property',
        required: false,
        readable: true,
        writeable: true,
        crudHints: hints,
      },
    ],
    searchMappings,
  };
}

describe('mapHydraSchemaToFields — x-crud hints', () => {
  // -------------------------------------------------------------------------
  // filterable overrides
  // -------------------------------------------------------------------------

  it('x-crud.filterable: false overrides the filterable:true default (empty searchMappings)', () => {
    // When searchMappings is empty, inference sets filterable=true for all fields.
    // The hint must be able to override this to false.
    const schema = makeSchemaWithHints('title', { filterable: false }, []);
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    expect(f!.filterable).toBe(false);
  });

  it('x-crud.filterable: true overrides filterable:false (field not in searchMappings)', () => {
    // When searchMappings contains other fields but NOT this one,
    // inference sets filterable=false. The hint must override this to true.
    const schema = makeSchemaWithHints('title', { filterable: true }, [
      { property: 'other', variable: 'other', required: false },
    ]);
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    expect(f!.filterable).toBe(true);
  });

  // -------------------------------------------------------------------------
  // sortable override
  // -------------------------------------------------------------------------

  it('x-crud.sortable: false sets the field as non-sortable', () => {
    // Default sortable from builder is true; hint must override to false.
    const schema = makeSchemaWithHints('title', { sortable: false });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    expect(f!.sortable).toBe(false);
  });

  it('x-crud.sortable: true preserves sortable when already true (no regression)', () => {
    const schema = makeSchemaWithHints('title', { sortable: true });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f!.sortable).toBe(true);
  });

  // -------------------------------------------------------------------------
  // hidden override
  // -------------------------------------------------------------------------

  it('x-crud.hidden: true hides the field (visible: false)', () => {
    const schema = makeSchemaWithHints('title', { hidden: true });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    expect(f!.visible).toBe(false);
  });

  it('x-crud.hidden: false leaves the field visible (no change from default)', () => {
    const schema = makeSchemaWithHints('title', { hidden: false });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    // hidden:false should NOT hide the field; visible default is true
    expect(f!.visible).toBe(true);
  });

  // -------------------------------------------------------------------------
  // order override
  // -------------------------------------------------------------------------

  it('x-crud.order: 2 sets the column order to 2', () => {
    const schema = makeSchemaWithHints('title', { order: 2 });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    expect(f!.order).toBe(2);
  });

  it('x-crud.order: 0 sets the column order to 0', () => {
    const schema = makeSchemaWithHints('title', { order: 0 });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f!.order).toBe(0);
  });

  // -------------------------------------------------------------------------
  // width override
  // -------------------------------------------------------------------------

  it('x-crud.width: 120 sets the column width to 120', () => {
    const schema = makeSchemaWithHints('title', { width: 120 });
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    expect(f!.width).toBe(120);
  });

  // -------------------------------------------------------------------------
  // No regression when x-crud is absent
  // -------------------------------------------------------------------------

  it('absent x-crud leaves inferred values unchanged (no regression)', () => {
    // No crudHints → all defaults from inference apply.
    const schema = makeSchemaWithHints('title', undefined, []);
    const fields = mapHydraSchemaToFields(schema);
    const f = fields.find((x) => x.name === 'title');
    expect(f).toBeDefined();
    // No searchMappings (empty array) → filterable defaults to true
    expect(f!.filterable).toBe(true);
    // Default sortable from builder is true
    expect(f!.sortable).toBe(true);
    // Default visible is true
    expect(f!.visible).toBe(true);
    // No order set
    expect(f!.order).toBeUndefined();
    // No width set
    expect(f!.width).toBeUndefined();
  });

  // -------------------------------------------------------------------------
  // Integration: parseHydraDoc forwards x-crud to crudHints
  // -------------------------------------------------------------------------

  it('parseHydraDoc forwards x-crud from the raw doc into field crudHints', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Category',
          '@type': 'Class',
          title: 'Category',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Category/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'Nombre',
              required: false,
              readable: true,
              writeable: true,
              'x-crud': { filterable: true, sortable: true, order: 0 },
            },
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Category/parent',
                '@type': 'Link',
                label: 'parent',
                range: '#Category',
              },
              title: 'Categoría padre',
              required: false,
              readable: true,
              writeable: true,
              'x-crud': { filterable: true, sortable: false, order: 1 },
            },
          ],
        },
      ],
    };

    const resources = parseHydraDoc(doc);
    const nameSchema = resources['Category'].fields.find((f) => f.name === 'name');
    const parentSchema = resources['Category'].fields.find((f) => f.name === 'parent');

    expect(nameSchema?.crudHints).toEqual({ filterable: true, sortable: true, order: 0 });
    expect(parentSchema?.crudHints).toEqual({ filterable: true, sortable: false, order: 1 });
  });

  it('end-to-end: parseHydraDoc + mapHydraSchemaToFields applies x-crud hints', () => {
    const doc: HydraApiDoc = {
      '@id': '/api/docs.jsonld',
      '@type': 'ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Category',
          '@type': 'Class',
          title: 'Category',
          supportedProperty: [
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Category/name',
                '@type': 'rdf:Property',
                label: 'name',
                range: 'xmls:string',
              },
              title: 'Nombre',
              required: false,
              readable: true,
              writeable: true,
              'x-crud': { filterable: true, sortable: true, order: 0 },
            },
            {
              '@type': 'SupportedProperty',
              property: {
                '@id': '#Category/parent',
                '@type': 'Link',
                label: 'parent',
                range: '#Category',
              },
              title: 'Categoría padre',
              required: false,
              readable: true,
              writeable: true,
              'x-crud': { filterable: true, sortable: false, order: 1 },
            },
          ],
        },
      ],
    };

    const resources = parseHydraDoc(doc);
    const fields = mapHydraSchemaToFields(resources['Category']);

    const nameField = fields.find((f) => f.name === 'name');
    const parentField = fields.find((f) => f.name === 'parent');

    expect(nameField?.filterable).toBe(true);
    expect(nameField?.sortable).toBe(true);
    expect(nameField?.order).toBe(0);

    expect(parentField?.filterable).toBe(true);
    expect(parentField?.sortable).toBe(false);
    expect(parentField?.order).toBe(1);
  });
});
