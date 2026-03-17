import type { HydraApiSpec, HydraFieldSchema, HydraResourceSchema } from './types';

// ---------------------------------------------------------------------------
// Internal helpers for working with the raw OpenAPI document
// ---------------------------------------------------------------------------

interface RawSchema {
  type?: string;
  properties?: Record<string, unknown>;
  required?: string[];
}

interface RawDoc {
  openapi?: string;
  paths?: Record<string, unknown>;
  components?: {
    schemas?: Record<string, RawSchema>;
  };
}

/**
 * Type-guard: assert that `value` is a non-null object (not an array).
 */
function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Validate that the raw doc looks like an OpenAPI document.
 * Throws a descriptive Error if it does not.
 */
function assertValidDoc(doc: unknown): asserts doc is RawDoc {
  if (!isObject(doc)) {
    throw new Error('parseOpenApiSpec: document must be a non-null object, got ' + typeof doc);
  }

  if (typeof doc['openapi'] !== 'string') {
    throw new Error('parseOpenApiSpec: document is missing required string field "openapi"');
  }

  if (!isObject(doc['components'])) {
    throw new Error('parseOpenApiSpec: document is missing required object field "components"');
  }

  const components = doc['components'] as Record<string, unknown>;
  if (!isObject(components['schemas'])) {
    throw new Error(
      'parseOpenApiSpec: document is missing required object field "components.schemas"',
    );
  }
}

/**
 * Given a schema name (e.g. "Product") and the paths map, return the best
 * matching API URL.
 *
 * Heuristic (in order):
 *  1. A path that starts with `/api/` and whose first collection segment
 *     shares a common prefix of at least `min(lower.length, segment.length)-1`
 *     chars with the lowercase schema name — this handles English plurals
 *     like "product"→"products" and "category"→"categories".
 *  2. Any path that contains the lowercase name as a substring.
 *  3. Fall back to `/api/${name.toLowerCase()}s`.
 */
function resolveApiUrl(schemaName: string, paths: Record<string, unknown> | undefined): string {
  if (paths) {
    const lower = schemaName.toLowerCase();
    const pathKeys = Object.keys(paths);

    // Strip path parameters to get collection URLs, e.g. "/api/products/{id}" → "/api/products"
    const collectionPaths = Array.from(
      new Set(pathKeys.map((p) => p.replace(/\/\{[^}]+\}.*$/, ''))),
    );

    // Primary: fuzzy prefix match — handles "product"→"products", "category"→"categories"
    const primary = collectionPaths.find((p) => {
      if (!p.startsWith('/api/')) return false;
      // First path segment after /api/, lowercased
      const segment = p.slice('/api/'.length).split('/')[0].toLowerCase();
      // Require a shared prefix of at least min(lower.length, segment.length)-1 chars
      const sharedLen = Math.max(4, Math.min(lower.length, segment.length) - 1);
      return lower.slice(0, sharedLen) === segment.slice(0, sharedLen);
    });
    if (primary) return primary;

    // Secondary: substring fallback
    const secondary = collectionPaths.find(
      (p) => p.startsWith('/api/') && p.toLowerCase().includes(lower),
    );
    if (secondary) return secondary;
  }
  return `/api/${schemaName.toLowerCase()}s`;
}

/**
 * Parse a single raw property value into a typed HydraFieldSchema.
 */
function parseFieldSchema(raw: unknown): HydraFieldSchema {
  if (!isObject(raw)) {
    return {};
  }

  const schema: HydraFieldSchema = {};

  if (typeof raw['type'] === 'string') schema.type = raw['type'];
  if (typeof raw['format'] === 'string') schema.format = raw['format'];
  if (Array.isArray(raw['enum'])) schema.enum = raw['enum'] as string[];
  if (isObject(raw['x-labels'])) {
    schema['x-labels'] = raw['x-labels'] as Record<string, string>;
  }
  if (typeof raw['x-resource'] === 'string') schema['x-resource'] = raw['x-resource'];
  if (typeof raw['readOnly'] === 'boolean') schema.readOnly = raw['readOnly'];
  if (typeof raw['description'] === 'string') schema.description = raw['description'];
  if (typeof raw['nullable'] === 'boolean') schema.nullable = raw['nullable'];
  if (isObject(raw['items'])) {
    const items = raw['items'] as Record<string, unknown>;
    schema.items = {};
    if (typeof items['$ref'] === 'string') schema.items.$ref = items['$ref'];
    if (typeof items['type'] === 'string') schema.items.type = items['type'];
  }

  return schema;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Parse a raw `/api/docs.json` response into a typed HydraApiSpec.
 *
 * @throws Error if the document is malformed or missing required fields.
 */
export function parseOpenApiSpec(doc: unknown): HydraApiSpec {
  assertValidDoc(doc);

  const rawSchemas = doc.components!.schemas!;
  const paths = isObject(doc.paths) ? (doc.paths as Record<string, unknown>) : undefined;

  const resources: Record<string, HydraResourceSchema> = {};

  for (const [schemaName, rawSchema] of Object.entries(rawSchemas)) {
    // Skip non-object entries (e.g. $ref-only entries)
    if (!isObject(rawSchema)) continue;

    const rawProps = isObject(rawSchema['properties'])
      ? (rawSchema['properties'] as Record<string, unknown>)
      : {};

    const properties: Record<string, HydraFieldSchema> = {};
    for (const [propName, propValue] of Object.entries(rawProps)) {
      properties[propName] = parseFieldSchema(propValue);
    }

    const required: string[] = Array.isArray(rawSchema['required'])
      ? (rawSchema['required'] as string[])
      : [];

    const apiUrl = resolveApiUrl(schemaName, paths);

    const resource: HydraResourceSchema = {
      name: schemaName,
      apiUrl,
      properties,
      required,
    };

    resources[apiUrl] = resource;
  }

  return {
    version: doc.openapi!,
    resources,
  };
}
