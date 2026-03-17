/**
 * TypeScript types that model an API Platform OpenAPI 3.1 / Hydra spec.
 *
 * These are pure data types — no React, no hooks, no side effects.
 */

/** Raw shape of a property from components.schemas[X].properties[Y] */
export interface HydraFieldSchema {
  type?: string; // "string" | "integer" | "number" | "boolean" | "array" | "object"
  format?: string; // "date-time" | "date" | "uri" | ...
  enum?: string[]; // present when it's an enum field
  'x-labels'?: Record<string, string>; // { "active": "Activo", "inactive": "Inactivo" }
  'x-resource'?: string; // "/api/categories" — for entity reference fields
  readOnly?: boolean;
  items?: { $ref?: string; type?: string }; // for array fields
  description?: string;
  nullable?: boolean;
}

/** A single resource schema extracted from components.schemas */
export interface HydraResourceSchema {
  name: string; // e.g. "Product"
  apiUrl: string; // e.g. "/api/products"
  properties: Record<string, HydraFieldSchema>;
  required: string[];
}

/** The full parsed API spec — one HydraResourceSchema per top-level resource */
export interface HydraApiSpec {
  version: string; // openapi version string
  resources: Record<string, HydraResourceSchema>; // keyed by apiUrl, e.g. "/api/products"
}
