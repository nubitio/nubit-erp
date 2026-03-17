/**
 * TypeScript types that model a Hydra JSON-LD API documentation response.
 *
 * These are pure data types — no React, no hooks, no side effects.
 */

// ---------------------------------------------------------------------------
// Raw Hydra JSON-LD shapes (from /api/docs.jsonld)
// ---------------------------------------------------------------------------

export interface HydraProperty {
  '@id': string;
  '@type': string; // e.g. "rdf:Property", "Link"
  label: string;
  /**
   * In real API Platform responses this can be:
   *   - a plain string: "xmls:string", "#Company"
   *   - an IRI object: { "@id": "http://www.w3.org/2001/XMLSchema#string" }
   *   - null or undefined
   * Use normalizeRange() in openApiParser.ts to safely extract a string.
   */
  range?: unknown;
}

export interface HydraSupportedProperty {
  '@type': 'SupportedProperty';
  property: HydraProperty;
  /**
   * Human-readable translated label provided by the backend (e.g. via API
   * Platform `description` → `title` on the outer SupportedProperty wrapper).
   * This is the field we want for display — e.g. "Nombre" for a Spanish locale.
   * Preferred over the auto-derived capitalised property name when present.
   *
   * Note: the inner `property.label` holds the raw technical property name
   * (e.g. "name") and is NOT translated. Only the outer `title` / `hydra:title`
   * on this wrapper carries the translated human-readable label.
   */
  title: string;
  /**
   * Alternative JSON-LD key for the same translated title, present in some
   * API Platform response variants.
   */
  'hydra:title'?: string;
  required?: boolean;
  readable?: boolean;
  writeable?: boolean;
}

export interface HydraClass {
  '@id': string; // e.g. "#Branch"
  '@type': string; // e.g. "Class"
  title: string;
  supportedProperty: HydraSupportedProperty[];
  /**
   * Hydra search template describing server-side filterable query params.
   * Present on collection operations in API Platform JSON-LD responses.
   */
  'hydra:search'?: HydraSearchTemplate;
}

/** Raw `hydra:IriTemplate` block found inside `hydra:search`. */
export interface HydraSearchTemplate {
  'hydra:mapping'?: HydraIriTemplateMapping[];
}

/** A single entry in `hydra:mapping`. */
export interface HydraIriTemplateMapping {
  /** The resource property this mapping targets, e.g. "name". */
  'hydra:property'?: string;
  /** The URL template variable, e.g. "name" or "order[createdAt]". */
  'hydra:variable'?: string;
  /** Whether this parameter is required. */
  'hydra:required'?: boolean;
}

export interface HydraApiDoc {
  '@id': string;
  '@type': 'ApiDocumentation';
  supportedClass: HydraClass[];
}

// ---------------------------------------------------------------------------
// Internal normalized types (used by mapper and ResourceSchema)
// ---------------------------------------------------------------------------

/** Normalized field descriptor derived from a HydraSupportedProperty */
export interface HydraFieldSchema {
  name: string;
  range?: string; // from property.range — e.g. "xmls:string", "#Company"
  propertyType: string; // from property["@type"] — "rdf:Property" | "Link"
  required: boolean;
  readable: boolean;
  writeable: boolean;
  /**
   * Human-readable label from `hydra:title` on the property descriptor.
   * When present and non-empty, the mapper uses this instead of the
   * auto-capitalised property name.
   */
  'hydra:title'?: string;
}

/**
 * A single entry from a Hydra `hydra:search` / `hydra:mapping` block.
 * Describes a query parameter accepted by the server for filtering.
 */
export interface HydraSearchMapping {
  /** The resource property name this filter applies to (e.g. "name", "status"). */
  property: string;
  /** The URL query parameter variable (e.g. "name", "order[createdAt]"). */
  variable: string;
  /** Whether the parameter is required by the server. */
  required: boolean;
}

/** Normalized resource descriptor derived from a HydraClass */
export interface HydraResourceSchema {
  className: string; // e.g. "Branch"
  apiUrl: string; // e.g. "/api/branches"
  fields: HydraFieldSchema[];
  /**
   * Filters discoverable from `hydra:search.hydra:mapping` on the collection
   * operation. Absent/empty means no server-side filter info was found.
   */
  searchMappings?: HydraSearchMapping[];
}

// ---------------------------------------------------------------------------
// Raw OpenAPI 3.1 shapes (from /api/docs.json)
// ---------------------------------------------------------------------------

export interface OpenApiProperty {
  type?: string;
  format?: string;
  readOnly?: boolean;
  writeOnly?: boolean;
  enum?: string[];
}

export interface OpenApiSchema {
  properties?: Record<string, OpenApiProperty>;
  required?: string[];
}

export interface OpenApiDoc {
  openapi: string; // "3.x.x"
  components: {
    schemas: Record<string, OpenApiSchema>;
  };
}

// ---------------------------------------------------------------------------
// Discriminated union for the API discovery result
// ---------------------------------------------------------------------------

export type ApiDoc = { format: 'hydra'; doc: HydraApiDoc } | { format: 'openapi'; doc: OpenApiDoc };
