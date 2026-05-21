import { describe, expect, it } from 'vitest';
import { toDashCase, toSnakeCase, pluralize, normalizeRange, parseHydraDoc } from './openApiParser';
import type { HydraApiDoc } from './types';

// ── toDashCase ────────────────────────────────────────────────────────────────

describe('toDashCase', () => {
  it('lowercases plain names', () => {
    expect(toDashCase('Category')).toBe('category');
  });

  it('converts camelCase to dash-case', () => {
    expect(toDashCase('cashMovementCategory')).toBe('cash-movement-category');
  });

  it('converts PascalCase to dash-case', () => {
    expect(toDashCase('SunatCatalog')).toBe('sunat-catalog');
    expect(toDashCase('CashMovementCategory')).toBe('cash-movement-category');
  });

  it('keeps already-lowercase-dashed strings unchanged', () => {
    expect(toDashCase('warehouse')).toBe('warehouse');
  });
});

// ── toSnakeCase ───────────────────────────────────────────────────────────────

describe('toSnakeCase', () => {
  it('converts PascalCase to snake_case', () => {
    expect(toSnakeCase('Category')).toBe('category');
    expect(toSnakeCase('CashMovementCategory')).toBe('cash_movement_category');
  });

  it('converts camelCase to snake_case', () => {
    expect(toSnakeCase('cashMovementCategory')).toBe('cash_movement_category');
  });
});

// ── pluralize ─────────────────────────────────────────────────────────────────

describe('pluralize', () => {
  it('appends s for regular words', () => {
    expect(pluralize('warehouse')).toBe('warehouses');
    expect(pluralize('product')).toBe('products');
  });

  it('handles consonant+y → ies', () => {
    expect(pluralize('category')).toBe('categories');
    expect(pluralize('company')).toBe('companies');
  });

  it('keeps vowel+y words with s', () => {
    expect(pluralize('day')).toBe('days');
    expect(pluralize('key')).toBe('keys');
  });

  it('appends es for s/x/z/ch/sh endings', () => {
    expect(pluralize('branch')).toBe('branches');
    expect(pluralize('box')).toBe('boxes');
  });
});

// ── normalizeRange ────────────────────────────────────────────────────────────

describe('normalizeRange', () => {
  it('returns string ranges unchanged', () => {
    expect(normalizeRange('xmls:string')).toBe('xmls:string');
    expect(normalizeRange('#Company')).toBe('#Company');
  });

  it('returns undefined for null/undefined', () => {
    expect(normalizeRange(null)).toBeUndefined();
    expect(normalizeRange(undefined)).toBeUndefined();
  });

  it('extracts @id from object ranges', () => {
    expect(normalizeRange({ '@id': 'http://www.w3.org/2001/XMLSchema#integer' })).toBe(
      'http://www.w3.org/2001/XMLSchema#integer',
    );
  });
});

// ── parseHydraDoc ─────────────────────────────────────────────────────────────

describe('parseHydraDoc', () => {
  const minimalDoc: HydraApiDoc = {
    '@context': '/api/contexts/Entrypoint',
    '@id': '/api',
    '@type': 'hydra:ApiDocumentation',
    supportedClass: [
      {
        '@id': '#Entrypoint',
        '@type': 'hydra:Class',
        supportedProperty: [
          {
            property: { '@id': '#Entrypoint/product', range: '#Product' },
            readable: true,
            writeable: false,
            required: false,
          },
        ],
        supportedOperation: [],
      },
      {
        '@id': '#Product',
        '@type': 'hydra:Class',
        supportedProperty: [
          {
            property: {
              '@id': '#Product/name',
              '@type': 'rdf:Property',
              range: 'xmls:string',
            },
            readable: true,
            writeable: true,
            required: true,
            label: 'Nombre',
          },
          {
            property: {
              '@id': '#Product/price',
              '@type': 'rdf:Property',
              range: 'xmls:decimal',
            },
            readable: true,
            writeable: true,
            required: false,
            label: 'Precio',
          },
          {
            property: {
              '@id': '#Product/internalCode',
              '@type': 'rdf:Property',
              range: 'xmls:string',
            },
            readable: false,
            writeable: true,
            required: false,
            label: 'Código interno',
          },
        ],
        supportedOperation: [],
      },
    ],
  };

  it('parses class names and excludes the Entrypoint class', () => {
    const result = parseHydraDoc(minimalDoc);
    expect(Object.keys(result)).toContain('Product');
    expect(Object.keys(result)).not.toContain('Entrypoint');
  });

  it('derives the API URL from the Entrypoint property', () => {
    const result = parseHydraDoc(minimalDoc);
    expect(result['Product'].apiUrl).toBe('/api/products');
  });

  it('parses readable properties as fields', () => {
    const result = parseHydraDoc(minimalDoc);
    const fieldNames = result['Product'].fields.map((f) => f.name);
    expect(fieldNames).toContain('name');
    expect(fieldNames).toContain('price');
  });

  it('skips non-readable (write-only) properties', () => {
    const result = parseHydraDoc(minimalDoc);
    const fieldNames = result['Product'].fields.map((f) => f.name);
    expect(fieldNames).not.toContain('internalCode');
  });

  it('marks required fields correctly', () => {
    const result = parseHydraDoc(minimalDoc);
    const nameField = result['Product'].fields.find((f) => f.name === 'name');
    const priceField = result['Product'].fields.find((f) => f.name === 'price');
    expect(nameField?.required).toBe(true);
    expect(priceField?.required).toBe(false);
  });

  it('returns empty object for a doc with no non-entrypoint classes', () => {
    const emptyDoc: HydraApiDoc = {
      '@context': '/api/contexts/Entrypoint',
      '@id': '/api',
      '@type': 'hydra:ApiDocumentation',
      supportedClass: [
        {
          '@id': '#Entrypoint',
          '@type': 'hydra:Class',
          supportedProperty: [],
          supportedOperation: [],
        },
      ],
    };
    const result = parseHydraDoc(emptyDoc);
    expect(Object.keys(result)).toHaveLength(0);
  });
});
