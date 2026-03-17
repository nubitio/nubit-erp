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
  range?: string; // e.g. "xmls:string", "#Company"
}

export interface HydraSupportedProperty {
  '@type': 'SupportedProperty';
  property: HydraProperty;
  title: string;
  required?: boolean;
  readable?: boolean;
  writeable?: boolean;
}

export interface HydraClass {
  '@id': string; // e.g. "#Branch"
  '@type': string; // e.g. "Class"
  title: string;
  supportedProperty: HydraSupportedProperty[];
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
}

/** Normalized resource descriptor derived from a HydraClass */
export interface HydraResourceSchema {
  className: string; // e.g. "Branch"
  apiUrl: string; // e.g. "/api/branches"
  fields: HydraFieldSchema[];
}
