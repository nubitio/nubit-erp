import { CoreHttpClient, GridData } from "@nubit/core";
import { DataRecord, Field, FieldOverride, FilterRule, LoadOption, SmartCrudFieldContract, SmartCrudRecord } from "@nubit/crud";
import { ReactNode } from "react";

//#region assets/api-platform/ApiPlatformRemoteDataSource.d.ts
type RemoteFilterDescriptor = unknown[];
type RemoteSortDescriptor = {
  selector?: string;
  desc?: boolean;
} | string;
type RemoteLoadOptions = DataRecord & {
  filter?: RemoteFilterDescriptor;
  sort?: RemoteSortDescriptor[];
  appendData?: DataRecord[];
  prependData?: DataRecord[];
  searchExpr?: unknown;
  searchValue?: unknown;
  searchOperation?: unknown;
};
type RemoteDataSourceOptions = {
  url: string;
  idField: string;
  byKeyUrl?: string | null;
  defaultFilterRules?: RemoteFilterDescriptor[];
  defaultSortRules?: RemoteSortDescriptor[];
  options?: LoadOption[];
  iriMode?: boolean;
  httpClient?: CoreHttpClient;
};
declare class ApiPlatformRemoteDataSource {
  private readonly config;
  private readonly httpClient;
  constructor(config: RemoteDataSourceOptions);
  makeFilterRules(filterRules: FilterRule[]): string;
  prepareLoadOptions(loadOptions: RemoteLoadOptions): RemoteLoadOptions;
  load(loadOptions: RemoteLoadOptions): Promise<GridData<DataRecord>>;
  byKey(key: unknown): Promise<DataRecord | null>;
  private fetchAll;
  private fetchById;
}
//#endregion
//#region assets/api-platform/types.d.ts
/**
 * TypeScript types that model a Hydra JSON-LD API documentation response.
 *
 * These are pure data types — no React, no hooks, no side effects.
 */
interface HydraProperty {
  '@id': string;
  '@type': string;
  label: string;
  supportedOperation?: HydraSupportedOperation[];
  /**
   * In real API Platform responses this can be:
   *   - a plain string: "xmls:string", "#Company"
   *   - an IRI object: { "@id": "http://www.w3.org/2001/XMLSchema#string" }
   *   - null or undefined
   * Use normalizeRange() in openApiParser.ts to safely extract a string.
   */
  range?: unknown;
}
/**
 * UI display hints injected by the backend via `#[ApiProperty(openapiContext: ['x-crud' => [...]])]`.
 * All properties are optional — absent means "use inferred value".
 */
interface CrudHints {
  /** Override whether the field is shown in the filter row. */
  filterable?: boolean;
  /** Override whether the field can be sorted in the grid. */
  sortable?: boolean;
  /** When true, the column is hidden from the grid by default. */
  hidden?: boolean;
  /** Column display order. */
  order?: number;
  /** Column width in pixels or as a CSS string. */
  width?: number;
}
interface HydraSupportedProperty {
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
  /**
   * UI CRUD hints injected by `TranslatedDocumentationNormalizer` from
   * `#[ApiProperty(openapiContext: ['x-crud' => [...]])]` on the PHP entity.
   */
  'x-crud'?: CrudHints;
}
/**
 * A single entry from `supportedOperation` on a Hydra class.
 * API Platform serialises the HTTP method as either `hydra:method` or `method`
 * depending on the AP version; we accept both so parsing never silently drops
 * an operation.
 *
 * Note: the outer array key is the compact `supportedOperation` (no `hydra:` prefix)
 * as confirmed from the /api/docs.jsonld wire format.
 */
interface HydraSupportedOperation {
  'hydra:method'?: string;
  method?: string;
}
interface HydraClass {
  '@id': string;
  '@type': string;
  title: string;
  supportedProperty: HydraSupportedProperty[];
  /**
   * Hydra search template describing server-side filterable query params.
   * Present on collection operations in API Platform JSON-LD responses.
   */
  'hydra:search'?: HydraSearchTemplate;
  /**
   * Supported HTTP operations for this class, e.g. GET, POST, PUT, PATCH, DELETE.
   * Used to infer canAdd / canEdit / canDelete permissions without manual config.
   *
   * Wire format (confirmed from /api/docs.jsonld): compact key `supportedOperation`
   * (no `hydra:` prefix). API Platform compact context strips the namespace prefix.
   */
  supportedOperation?: HydraSupportedOperation[];
}
/** Raw `hydra:IriTemplate` block found inside `hydra:search`. */
interface HydraSearchTemplate {
  'hydra:mapping'?: HydraIriTemplateMapping[];
}
/** A single entry in `hydra:mapping`. */
interface HydraIriTemplateMapping {
  /** The resource property this mapping targets, e.g. "name". */
  'hydra:property'?: string;
  /** The URL template variable, e.g. "name" or "order[createdAt]". */
  'hydra:variable'?: string;
  /** Whether this parameter is required. */
  'hydra:required'?: boolean;
}
interface HydraApiDoc {
  '@context'?: string;
  '@id': string;
  '@type': 'ApiDocumentation' | 'hydra:ApiDocumentation';
  supportedClass: HydraClass[];
}
/** Normalized field descriptor derived from a HydraSupportedProperty */
interface HydraFieldSchema {
  name: string;
  range?: string;
  propertyType: string;
  required: boolean;
  readable: boolean;
  writeable: boolean;
  /**
   * Human-readable label from `hydra:title` on the property descriptor.
   * When present and non-empty, the mapper uses this instead of the
   * auto-capitalised property name.
   */
  'hydra:title'?: string;
  /**
   * UI CRUD hints forwarded from `x-crud` on the raw `HydraSupportedProperty`.
   * When present, these values override inferred field properties in the mapper.
   */
  crudHints?: CrudHints;
}
/**
 * A single entry from a Hydra `hydra:search` / `hydra:mapping` block.
 * Describes a query parameter accepted by the server for filtering.
 */
interface HydraSearchMapping {
  /** The resource property name this filter applies to (e.g. "name", "status"). */
  property: string;
  /** The URL query parameter variable (e.g. "name", "order[createdAt]"). */
  variable: string;
  /** Whether the parameter is required by the server. */
  required: boolean;
}
/** Normalized resource descriptor derived from a HydraClass */
interface HydraResourceSchema {
  className: string;
  apiUrl: string;
  fields: HydraFieldSchema[];
  /**
   * Filters discoverable from `hydra:search.hydra:mapping` on the collection
   * operation. Absent/empty means no server-side filter info was found.
   */
  searchMappings?: HydraSearchMapping[];
  /**
   * HTTP methods supported by this resource class, uppercased.
   * e.g. ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].
   * Derived from `supportedOperation` (compact wire key) in the Hydra API doc.
   * Empty array means no operation info was found (fall back to platform defaults).
   */
  supportedOperations?: string[];
}
interface OpenApiProperty {
  type?: string;
  format?: string;
  readOnly?: boolean;
  writeOnly?: boolean;
  enum?: string[];
}
interface OpenApiSchema {
  properties?: Record<string, OpenApiProperty>;
  required?: string[];
}
interface OpenApiDoc {
  openapi: string;
  components: {
    schemas: Record<string, OpenApiSchema>;
  };
}
type ApiDoc = {
  format: 'hydra';
  doc: HydraApiDoc;
} | {
  format: 'openapi';
  doc: OpenApiDoc;
};
//#endregion
//#region assets/api-platform/openApiParser.d.ts
/**
 * Parse a raw `/api/docs.jsonld` Hydra JSON-LD response into a typed
 * Record<className, HydraResourceSchema>.
 *
 * @throws Error if doc is not a valid Hydra ApiDocumentation.
 */
declare function parseHydraDoc(doc: HydraApiDoc): Record<string, HydraResourceSchema>;
/**
 * Parse a raw `/api/docs.json` OpenAPI 3.1 response into the same
 * Record<className, HydraResourceSchema> output shape so that
 * `useResourceSchema` can consume it transparently.
 */
declare function parseOpenApiDoc(doc: OpenApiDoc): Record<string, HydraResourceSchema>;
//#endregion
//#region assets/api-platform/HydraToFieldMapper.d.ts
/**
 * Determine the FieldType bucket for a given `range` string.
 *
 * Handles both the short-form `xmls:` prefix used by API Platform
 * AND the full XSD IRI form `http://www.w3.org/2001/XMLSchema#...`
 * that can appear when `property.range` is an IRI object `{ "@id": "..." }`
 * and has been normalised to a string by `normalizeRange`.
 *
 * Returns a string tag that the caller can switch on; not the FieldType enum
 * directly (to keep this function pure / dependency-free of FieldBuilders).
 */
type RangeTag = 'boolean' | 'dateTime' | 'integer' | 'decimal' | 'entity' | 'text';
declare function resolveRangeTag(range: string | undefined, propertyType: string): RangeTag;
/**
 * Map a single HydraResourceSchema to an array of Field objects using the
 * existing field builder utilities.
 *
 * Mapping rules (in priority order):
 *  1. name === 'id' or name === '@id'  → always emit as a hidden identity field
 *                                         (visible: false, readonly: true, isIdentity: true)
 *                                         Required so native grids and forms always have a stable row key.
 *  2. readable: false                  → skip (already filtered upstream, but defensive)
 *  3. writeable: false (display-only)  → noneField
 *  4. range resolves to 'boolean'      → switchField
 *  5. range resolves to 'dateTime'     → datetimeField
 *  6. range resolves to 'integer'      → numberField with precision(0)
 *  7. range resolves to 'decimal'      → numberField
 *  8. range resolves to 'entity' OR propertyType === 'Link' → entityField
 *  9. range resolves to 'text' OR fallback  → textField
 *
 * For each field, `filterable` is `true` when the field's property name appears in
 * `schema.searchMappings` (from `hydra:search`). When `searchMappings` is empty
 * (e.g. the resource uses a catch-all data-grid filter that doesn't
 * enumerate field-level mappings), ALL fields default to `filterable: true`.
 *
 * After processing all schema fields, if no `id` field was found in the schema,
 * a synthetic hidden identity field is injected so the grid always has a key.
 *
 * @param schema - The Hydra resource schema to map.
 * @param urlLookup - Optional lookup function to resolve the API URL for a related
 *   entity class. When provided, Rule 8 uses this before falling back to the
 *   automatic pluralization heuristic. Example: `(cls) => resourceMap[cls]?.apiUrl`
 *
 * @pure — no React, no hooks, no side effects.
 */
declare function mapHydraSchemaToFields(schema: HydraResourceSchema, urlLookup?: (className: string) => string | undefined, schemaLookup?: (className: string) => HydraResourceSchema | undefined): Field[];
//#endregion
//#region assets/api-platform/useHydraMetadata.d.ts
/**
 * Stable query key used by useHydraMetadata.
 * Export this so that consumers (e.g. SmartCrudPage retry button) can
 * invalidate exactly the right query without coupling to an internal string.
 */
declare const API_DOC_QUERY_KEY: readonly ["api-doc-discovery"];
interface UseHydraMetadataResult {
  data: ApiDoc | undefined;
  isLoading: boolean;
  error: Error | undefined;
}
declare function useHydraMetadata(): UseHydraMetadataResult;
//#endregion
//#region assets/api-platform/useResourceSchema.d.ts
interface UseResourceSchemaResult {
  fields: Field[];
  isLoading: boolean;
  error: Error | undefined;
  /**
   * HTTP methods supported by this resource class, derived from
   * `hydra:supportedOperation` in the Hydra API doc.
   * e.g. ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
   * Empty array when no operation info is available (Hydra not used, or
   * manual fields supplied — the caller should fall back to platform defaults).
   */
  supportedOperations: string[];
}
declare function useResourceSchema<T extends SmartCrudRecord = DataRecord>(apiUrl: string, manualFields?: Field[], overrides?: FieldOverride[], fieldContract?: SmartCrudFieldContract<T>): UseResourceSchemaResult;
//#endregion
//#region assets/api-platform/SchemaContext.d.ts
interface SchemaProviderProps {
  children: ReactNode;
}
/**
 * Wrap your app (or a subtree) with `SchemaProvider` to share a single
 * `/api/docs.jsonld` fetch across all `SmartCrudPage` instances.
 */
declare function SchemaProvider({
  children
}: SchemaProviderProps): import("react/jsx-runtime").JSX.Element;
interface UseSchemaContextResult {
  data: UseHydraMetadataResult['data'];
  isLoading: boolean;
  isError: boolean;
  error: Error | undefined;
  refetch?: () => void;
}
/**
 * Returns the shared schema context if inside a `<SchemaProvider>`,
 * otherwise falls back to calling `useHydraMetadata()` directly.
 *
 * This makes `SmartCrudPage` work standalone without requiring a provider.
 */
declare function useSchemaContext(): UseSchemaContextResult;
//#endregion
export { API_DOC_QUERY_KEY, type ApiDoc, ApiPlatformRemoteDataSource, type FieldOverride, type HydraApiDoc, type OpenApiDoc, type RemoteDataSourceOptions, type RemoteFilterDescriptor, type RemoteLoadOptions, type RemoteSortDescriptor, SchemaProvider, type UseSchemaContextResult, mapHydraSchemaToFields, parseHydraDoc, parseOpenApiDoc, resolveRangeTag, useHydraMetadata, useResourceSchema, useSchemaContext };