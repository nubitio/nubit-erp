import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();

const iriToId = (iri: string) => iri.split('/').filter(Boolean).pop() ?? iri;

/** Extracts the resource id from an API entity value (IRI string, @id, id, or _iri). */
export function extractEntityId(value: unknown): string {
  if (value && typeof value === 'object') {
    const obj = value as { id?: string; '@id'?: string; _iri?: string };
    if (typeof obj.id === 'string' && obj.id !== '') {
      return obj.id;
    }
    if (typeof obj['@id'] === 'string' && obj['@id'] !== '') {
      return iriToId(obj['@id']);
    }
    if (typeof obj._iri === 'string' && obj._iri !== '') {
      return iriToId(obj._iri);
    }
    return '';
  }

  if (typeof value === 'string' && value !== '') {
    return iriToId(value);
  }

  return '';
}

/** Builds a canonical ApiPlatform IRI for warehouses or products. */
export function resolveEntityIri(value: unknown, collection: 'warehouses' | 'products'): string {
  const base = API.endsWith('/') ? API : `${API}/`;

  if (typeof value === 'string' && value !== '') {
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }
    if (value.startsWith('/')) {
      const origin = base.replace(/\/api\/?$/, '');
      return `${origin}${value}`;
    }
    return `${base}${collection}/${value}`;
  }

  if (value && typeof value === 'object') {
    const obj = value as { id?: string; '@id'?: string; _iri?: string };
    if (typeof obj._iri === 'string' && obj._iri !== '') {
      return obj._iri;
    }
    if (typeof obj['@id'] === 'string' && obj['@id'] !== '') {
      const iri = obj['@id'];
      if (iri.startsWith('http://') || iri.startsWith('https://')) {
        return iri;
      }
      const origin = base.replace(/\/api\/?$/, '');
      return `${origin}${iri}`;
    }
    if (typeof obj.id === 'string' && obj.id !== '') {
      return `${base}${collection}/${obj.id}`;
    }
  }

  return '';
}
