import { describe, expect, it } from 'vitest';
import { extractEntityId, resolveEntityIri } from './inventoryEntityIri';

describe('inventoryEntityIri', () => {
  it('extracts id from @id when id is absent', () => {
    expect(extractEntityId({ '@id': '/api/products/abc-123', name: 'Demo' })).toBe('abc-123');
  });

  it('builds product IRI from @id object', () => {
    const iri = resolveEntityIri({ '@id': '/api/products/abc-123' }, 'products');
    expect(iri.endsWith('/api/products/abc-123')).toBe(true);
  });

  it('returns empty string when entity cannot be resolved', () => {
    expect(resolveEntityIri({ name: 'Sin id' }, 'products')).toBe('');
  });
});
