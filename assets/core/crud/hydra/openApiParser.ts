import type {
  HydraApiDoc,
  HydraResourceSchema,
  HydraFieldSchema,
  HydraSupportedProperty,
  OpenApiDoc,
  OpenApiProperty,
} from './types';

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Pluralizes an English word (snake_case or lowercase) with common rules.
 *
 * - Words ending in a consonant + 'y'  → replace 'y' with 'ies'
 *     category  → categories, company → companies
 * - Words ending in s/x/z/ch/sh       → append 'es'
 *     branch    → branches, box → boxes
 * - Everything else                   → append 's'
 *     warehouse → warehouses, product → products
 */
function pluralize(word: string): string {
  if (word.endsWith('y') && !['ay', 'ey', 'iy', 'oy', 'uy'].some((v) => word.endsWith(v))) {
    return word.slice(0, -1) + 'ies';
  }
  if (
    word.endsWith('s') ||
    word.endsWith('x') ||
    word.endsWith('z') ||
    word.endsWith('ch') ||
    word.endsWith('sh')
  ) {
    return word + 'es';
  }
  return word + 's';
}

/**
 * Derives the API URL from an Entrypoint property ID.
 * "#Entrypoint/branch"               -> "/api/branches"
 * "#Entrypoint/cashMovementCategory" -> "/api/cash_movement_categories"
 */
function deriveApiUrl(entrypointPropertyId: string): string {
  const name = entrypointPropertyId.split('/').pop() ?? '';
  const snake = name.replace(/([A-Z])/g, '_$1').toLowerCase();
  const plural = snake.endsWith('s') ? snake : pluralize(snake);
  return `/api/${plural}`;
}

/**
 * Safely extracts a string range from whatever `property.range` actually is.
 *
 * Real API Platform responses can return:
 *   - a plain string: "xmls:string", "#Company"
 *   - an IRI object: { "@id": "http://www.w3.org/2001/XMLSchema#string" }
 *   - null or undefined
 *   - rarely an array (ignored — returns undefined)
 */
export function normalizeRange(raw: unknown): string | undefined {
  if (typeof raw === 'string') return raw;
  if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
    const id = (raw as Record<string, unknown>)['@id'];
    if (typeof id === 'string') return id;
  }
  return undefined;
}

/**
 * Convert an OpenAPI property descriptor to a short-form xmls: range string.
 */
function mapOpenApiTypeToRange(prop: OpenApiProperty): string | undefined {
  if (prop.format === 'date-time') return 'xmls:dateTime';
  switch (prop.type) {
    case 'integer':
      return 'xmls:integer';
    case 'number':
      return 'xmls:decimal';
    case 'boolean':
      return 'xmls:boolean';
    case 'string':
      return 'xmls:string';
    default:
      return undefined;
  }
}

/**
 * Extracts the technical property name from a HydraSupportedProperty.
 *
 * The technical name (used in API JSON payloads) is found in sp.property['@id'],
 * which looks like "#Branch/businessName" or "#Category/name".
 * We extract the part after the last '/'.
 *
 * Fallback chain: @id suffix → property.label → title → ''
 */
function technicalName(sp: HydraSupportedProperty): string {
  const fromId = sp.property?.['@id']?.split('/').pop();
  if (fromId && fromId !== '') return fromId;
  // fallback chain: label → title
  return sp.property?.label ?? sp.title ?? '';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Parse a raw `/api/docs.jsonld` Hydra JSON-LD response into a typed
 * Record<className, HydraResourceSchema>.
 *
 * @throws Error if doc is not a valid Hydra ApiDocumentation.
 */
export function parseHydraDoc(doc: HydraApiDoc): Record<string, HydraResourceSchema> {
  const result: Record<string, HydraResourceSchema> = {};

  // Build URL map from the Entrypoint class
  const urlMap: Record<string, string> = {};
  const entrypoint = doc.supportedClass.find((c) => c['@id'] === '#Entrypoint');
  if (entrypoint) {
    for (const sp of entrypoint.supportedProperty ?? []) {
      const rawRange = sp.property?.range;
      const range = normalizeRange(rawRange); // safely handles string | object | null | undefined
      const propId = sp.property?.['@id']; // e.g. "#Entrypoint/branch"
      if (range && propId) {
        const className = range.replace('#', '');
        urlMap[className] = deriveApiUrl(propId);
      }
    }
  }

  for (const cls of doc.supportedClass) {
    if (cls['@id'] === '#Entrypoint') continue;

    const className = cls['@id'].replace('#', '');
    const fields: HydraFieldSchema[] = [];

    for (const sp of cls.supportedProperty ?? []) {
      const readable = sp.readable !== false;
      const writeable = sp.writeable !== false;

      // Skip fields that are not readable (write-only internal fields)
      if (!readable) continue;

      fields.push({
        name: technicalName(sp),
        range: normalizeRange(sp.property?.range),
        propertyType: sp.property?.['@type'] ?? 'rdf:Property',
        required: sp.required ?? false,
        readable,
        writeable,
      });
    }

    result[className] = {
      className,
      apiUrl:
        urlMap[className] ??
        `/api/${pluralize(
          className
            .replace(/([A-Z])/g, '_$1')
            .toLowerCase()
            .replace(/^_/, ''),
        )}`,
      fields,
    };
  }

  return result;
}

/**
 * Parse a raw `/api/docs.json` OpenAPI 3.1 response into the same
 * Record<className, HydraResourceSchema> output shape so that
 * `useResourceSchema` can consume it transparently.
 */
export function parseOpenApiDoc(doc: OpenApiDoc): Record<string, HydraResourceSchema> {
  const result: Record<string, HydraResourceSchema> = {};

  for (const [className, schema] of Object.entries(doc.components?.schemas ?? {})) {
    const fields: HydraFieldSchema[] = [];
    const required = new Set(schema.required ?? []);

    for (const [fieldName, prop] of Object.entries(schema.properties ?? {})) {
      const readable = !prop.writeOnly;
      const writeable = !prop.readOnly;

      // Skip write-only fields (not readable in the UI)
      if (!readable) continue;

      fields.push({
        name: fieldName,
        range: mapOpenApiTypeToRange(prop), // convert OpenAPI type/format → xmls: string
        propertyType: 'rdf:Property',
        required: required.has(fieldName),
        readable,
        writeable,
      });
    }

    result[className] = {
      className,
      apiUrl: `/api/${pluralize(
        className
          .replace(/([A-Z])/g, '_$1')
          .toLowerCase()
          .replace(/^_/, ''),
      )}`, // best-effort; no Entrypoint in OpenAPI
      fields,
    };
  }

  return result;
}
