import { createCoreHttpClient, useCoreConfig, useCoreHttpClient } from "@nubit/core";
import { datetimeField, entityField, noneField, numberField, resolveSmartCrudFields, switchField, textField } from "@nubit/crud";
import { useQuery } from "@tanstack/react-query";
import { createContext, useContext } from "react";
import { jsx } from "react/jsx-runtime";
//#region assets/api-platform/ApiPlatformRemoteDataSource.ts
const defaultHttpClient = createCoreHttpClient({ baseUrl: "" });
function findPreloadedItem(loadOptions, idField, id) {
	const prependData = Array.isArray(loadOptions.prependData) ? loadOptions.prependData : [];
	const appendData = Array.isArray(loadOptions.appendData) ? loadOptions.appendData : [];
	return [...prependData, ...appendData].find((item) => item[idField] === id || item["@id"] === id) ?? null;
}
function addIriField(url, item) {
	const itemId = item["id"] ?? item["code"] ?? item["uuid"] ?? item["slug"];
	const iri = typeof item["@id"] === "string" ? item["@id"] : typeof itemId === "string" || typeof itemId === "number" ? `${url}/${itemId}` : void 0;
	return {
		...item,
		_iri: iri
	};
}
function normalizeFilterRules(filterRules, defaultFilterRules) {
	const filters = [];
	defaultFilterRules.forEach((rule) => filters.push(rule));
	if (filterRules.length === 3 && typeof filterRules[0] !== "object") filters.push([
		filterRules[0],
		filterRules[1],
		filterRules[2]
	]);
	else filterRules.forEach((rule) => {
		filters.push(Array.isArray(rule) ? rule : [rule]);
	});
	return filters;
}
function applyLoadOptionDefaults(loadOptions, options) {
	const nextOptions = { ...loadOptions };
	options.forEach((option) => {
		for (const key in option) if (Object.prototype.hasOwnProperty.call(option, key)) nextOptions[key] = option[key];
	});
	return nextOptions;
}
function normalizeSortRules(sortRules, defaultSortRules, idField) {
	const [firstSort] = sortRules;
	const firstSelector = typeof firstSort === "string" ? firstSort : firstSort?.selector;
	if (sortRules.length === 1 && firstSelector === idField && defaultSortRules.length === 0) return [];
	if (sortRules.length === 1 && firstSelector === idField) return [...defaultSortRules];
	return sortRules;
}
function stripAdapterOnlyParams(loadOptions) {
	const nextOptions = { ...loadOptions };
	delete nextOptions.searchOperation;
	if (nextOptions.searchExpr === void 0) delete nextOptions.searchValue;
	return nextOptions;
}
function getFlatIriFilter(filter) {
	if (!Array.isArray(filter)) return null;
	return filter.length === 1 && Array.isArray(filter[0]) ? filter[0] : filter;
}
var ApiPlatformRemoteDataSource = class {
	config;
	httpClient;
	constructor(config) {
		this.config = config;
		this.httpClient = config.httpClient ?? defaultHttpClient;
	}
	makeFilterRules(filterRules) {
		return filterRules.map((filter) => `filter[]=["${filter.field}","${filter.operator}","${filter.value}"]`).join("&");
	}
	prepareLoadOptions(loadOptions) {
		const withDefaults = applyLoadOptionDefaults(loadOptions, this.config.options ?? []);
		const normalizedFilters = normalizeFilterRules(withDefaults.filter ?? [], this.config.defaultFilterRules ?? []);
		return stripAdapterOnlyParams({
			...withDefaults,
			filter: normalizedFilters,
			sort: normalizeSortRules(withDefaults.sort ?? [], this.config.defaultSortRules ?? [], this.config.idField)
		});
	}
	async load(loadOptions) {
		const preparedOptions = this.prepareLoadOptions(loadOptions);
		if (this.config.iriMode) {
			const flatFilter = getFlatIriFilter(preparedOptions.filter);
			if (Array.isArray(flatFilter) && flatFilter.length === 3 && flatFilter[0] === "_iri" && flatFilter[1] === "=" && typeof flatFilter[2] === "string") {
				const item = await this.byKey(flatFilter[2]);
				return {
					data: item ? [addIriField(this.config.url, item)] : [],
					totalCount: item ? 1 : 0,
					summary: null
				};
			}
			const result = await this.fetchAll(preparedOptions);
			return {
				...result,
				data: result.data.map((item) => addIriField(this.config.url, item))
			};
		}
		return this.fetchAll(preparedOptions);
	}
	async byKey(key) {
		const id = typeof key === "object" && key !== null ? key[this.config.idField] : key;
		if (id === void 0) return null;
		const loadOptions = applyLoadOptionDefaults({}, this.config.options ?? []);
		const item = await this.fetchById(this.config.byKeyUrl ?? this.config.url, id, loadOptions);
		return this.config.iriMode && item ? addIriField(this.config.url, item) : item;
	}
	async fetchAll(loadOptions) {
		const response = await this.httpClient.get(this.config.url, { params: loadOptions });
		let data = response.data;
		if (loadOptions.appendData) data = [...data, ...loadOptions.appendData];
		if (loadOptions.prependData) data = [...loadOptions.prependData, ...data];
		const headerCount = Number(response.headers.get("x-total-count"));
		return {
			data,
			totalCount: Number.isFinite(headerCount) ? headerCount : data.length,
			summary: null
		};
	}
	async fetchById(url, id, loadOptions) {
		const preloadedItem = findPreloadedItem(loadOptions, this.config.idField, id);
		if (preloadedItem) return preloadedItem;
		const resolvedUrl = typeof id === "string" && id.startsWith("/") ? id : `${url}/${id}`;
		return this.httpClient.get(resolvedUrl).then((response) => response.data).catch(() => findPreloadedItem(loadOptions, this.config.idField, id));
	}
};
//#endregion
//#region assets/api-platform/openApiParser.ts
/**
* Converts a camelCase or PascalCase name to dash-case.
* Mirrors API Platform's DashPathSegmentNameGenerator behaviour.
* Examples:
*   "Category"              → "category"
*   "SunatCatalog"          → "sunat-catalog"
*   "CashMovementCategory"  → "cash-movement-category"
*   "cashMovementCategory"  → "cash-movement-category"
*/
function toDashCase(name) {
	return name.replace(/([A-Z])/g, "-$1").toLowerCase().replace(/^-/, "");
}
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
function pluralize(word) {
	if (word.endsWith("y") && ![
		"ay",
		"ey",
		"iy",
		"oy",
		"uy"
	].some((v) => word.endsWith(v))) return word.slice(0, -1) + "ies";
	if (word.endsWith("s") || word.endsWith("x") || word.endsWith("z") || word.endsWith("ch") || word.endsWith("sh")) return word + "es";
	return word + "s";
}
/** Entrypoint property names that are already in plural form (dash-case). */
const ALREADY_PLURAL = new Set([
	"purchases",
	"sales",
	"invoices",
	"transfers",
	"movements",
	"shifts",
	"outbounds",
	"cash-movements",
	"cash-registers",
	"cash-shifts"
]);
/**
* Derives the API URL from an Entrypoint property ID.
* "#Entrypoint/branch"               -> "/api/branches"
* "#Entrypoint/cashMovementCategory" -> "/api/cash-movement-categories"
*/
function deriveApiUrl(entrypointPropertyId) {
	const dashed = toDashCase(entrypointPropertyId.split("/").pop() ?? "");
	return `/api/${ALREADY_PLURAL.has(dashed) ? dashed : pluralize(dashed)}`;
}
/**
* Safely extracts a string range from whatever `property.range` actually is.
*
* Real API Platform responses can return:
*   - a plain string: "xmls:string", "#Company"
*   - an IRI object: { "@id": "http://www.w3.org/2001/XMLSchema#string" }
*   - null or undefined
*   - a Hydra collection range array where the resource class lives under
*     `owl:equivalentClass.owl:allValuesFrom.@id`
*/
function normalizeRange(raw) {
	if (typeof raw === "string") return raw;
	if (Array.isArray(raw)) for (const item of raw) {
		if (!item || typeof item !== "object") continue;
		const equivalentClass = item["owl:equivalentClass"];
		if (!equivalentClass || typeof equivalentClass !== "object") continue;
		const allValuesFrom = equivalentClass["owl:allValuesFrom"];
		if (!allValuesFrom || typeof allValuesFrom !== "object") continue;
		const id = allValuesFrom["@id"];
		if (typeof id === "string") return id;
	}
	if (raw && typeof raw === "object" && !Array.isArray(raw)) {
		const id = raw["@id"];
		if (typeof id === "string") return id;
	}
}
/**
* Convert an OpenAPI property descriptor to a short-form xmls: range string.
*/
function mapOpenApiTypeToRange(prop) {
	if (prop.format === "date-time") return "xmls:dateTime";
	switch (prop.type) {
		case "integer": return "xmls:integer";
		case "number": return "xmls:decimal";
		case "boolean": return "xmls:boolean";
		case "string": return "xmls:string";
		default: return;
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
function technicalName(sp) {
	const fromId = sp.property?.["@id"]?.split("/").pop();
	if (fromId && fromId !== "") return fromId;
	return sp.property?.label ?? sp.title ?? "";
}
/**
* Extract `hydra:search` mappings from a HydraClass.
* Returns an empty array if the class has no `hydra:search` block,
* or if any mapping entry is missing the required `hydra:property` field.
* Never throws.
*/
function extractSearchMappings(cls) {
	const searchBlock = cls["hydra:search"];
	if (!searchBlock) return [];
	const mappings = searchBlock["hydra:mapping"] ?? [];
	const result = [];
	for (const mapping of mappings) {
		const property = mapping["hydra:property"];
		const variable = mapping["hydra:variable"];
		if (!property || !variable) continue;
		result.push({
			property,
			variable,
			required: mapping["hydra:required"] ?? false
		});
	}
	return result;
}
/**
* Extract the HTTP method names from `supportedOperation` on a HydraClass.
* Returns an uppercase string array, e.g. ['GET', 'POST', 'PATCH', 'DELETE'].
* Returns an empty array when the class has no `supportedOperation` block.
*
* Wire format (confirmed from /api/docs.jsonld): compact key `supportedOperation`
* (no `hydra:` prefix). API Platform compact context strips the namespace prefix.
*
* Accepts both `hydra:method` and `method` field names inside each operation entry
* to be safe across different API Platform serialisation variants.
*/
function extractSupportedOperations(cls) {
	const operations = cls["supportedOperation"];
	if (!operations || operations.length === 0) return [];
	return operations.map((op) => (op["hydra:method"] ?? op["method"] ?? "").toUpperCase()).filter(Boolean);
}
function extractEntrypointCollectionOperations(doc) {
	const entrypoint = doc["supportedClass"].find((c) => c["@id"] === "#Entrypoint");
	if (!entrypoint) return {};
	const collectionOperations = {};
	for (const sp of entrypoint.supportedProperty ?? []) {
		const rawRange = sp.property?.range;
		const range = normalizeRange(rawRange);
		if (!range) continue;
		const className = range.replace("#", "");
		collectionOperations[className] = (sp.property?.supportedOperation ?? []).map((op) => (op["hydra:method"] ?? op["method"] ?? "").toUpperCase()).filter(Boolean);
	}
	return collectionOperations;
}
/**
* Parse a raw `/api/docs.jsonld` Hydra JSON-LD response into a typed
* Record<className, HydraResourceSchema>.
*
* @throws Error if doc is not a valid Hydra ApiDocumentation.
*/
function parseHydraDoc(doc) {
	const result = {};
	const collectionOperationsMap = extractEntrypointCollectionOperations(doc);
	const urlMap = {};
	const entrypoint = doc["supportedClass"].find((c) => c["@id"] === "#Entrypoint");
	if (entrypoint) for (const sp of entrypoint.supportedProperty ?? []) {
		const rawRange = sp.property?.range;
		const range = normalizeRange(rawRange);
		const propId = sp.property?.["@id"];
		if (range && propId) {
			const className = range.replace("#", "");
			urlMap[className] = deriveApiUrl(propId);
		}
	}
	for (const cls of doc["supportedClass"]) {
		if (cls["@id"] === "#Entrypoint") continue;
		const className = cls["@id"].replace("#", "");
		const fields = [];
		for (const sp of cls.supportedProperty ?? []) {
			const readable = sp.readable !== false;
			const writeable = sp.writeable !== false;
			if (!readable) continue;
			fields.push({
				name: technicalName(sp),
				range: normalizeRange(sp.property?.range),
				propertyType: sp.property?.["@type"] ?? "rdf:Property",
				required: sp.required ?? false,
				readable,
				writeable,
				"hydra:title": sp["title"] ?? sp["hydra:title"] ?? void 0,
				crudHints: sp["x-crud"]
			});
		}
		result[className] = {
			className,
			apiUrl: urlMap[className] ?? `/api/${pluralize(toDashCase(className))}`,
			fields,
			searchMappings: extractSearchMappings(cls),
			supportedOperations: Array.from(new Set([...collectionOperationsMap[className] ?? [], ...extractSupportedOperations(cls)]))
		};
	}
	return result;
}
/**
* Parse a raw `/api/docs.json` OpenAPI 3.1 response into the same
* Record<className, HydraResourceSchema> output shape so that
* `useResourceSchema` can consume it transparently.
*/
function parseOpenApiDoc(doc) {
	const result = {};
	for (const [className, schema] of Object.entries(doc.components?.schemas ?? {})) {
		const fields = [];
		const required = new Set(schema.required ?? []);
		for (const [fieldName, prop] of Object.entries(schema.properties ?? {})) {
			const readable = !prop.writeOnly;
			const writeable = !prop.readOnly;
			if (!readable) continue;
			fields.push({
				name: fieldName,
				range: mapOpenApiTypeToRange(prop),
				propertyType: "rdf:Property",
				required: required.has(fieldName),
				readable,
				writeable
			});
		}
		result[className] = {
			className,
			apiUrl: `/api/${pluralize(toDashCase(className))}`,
			fields
		};
	}
	return result;
}
//#endregion
//#region assets/api-platform/HydraToFieldMapper.ts
/**
* Returns true when the given `range` string declares an XSD integer type.
* Supports both short-form `xmls:` prefixes and full XSD IRIs.
*
* Used to decide whether the `id` field should be a `numberField()` (integer
* primary key) or a `textField()` (UUID / string primary key — safe default).
*/
function isIntegerRange(range) {
	if (!range) return false;
	if (range.startsWith("http://www.w3.org/2001/XMLSchema#")) {
		const local = range.split("#").pop() ?? "";
		return local === "integer" || local === "int" || local === "long";
	}
	return range === "xmls:integer" || range === "xmls:int" || range === "xmls:long" || range === "xsd:integer" || range === "xsd:int" || range === "xsd:long";
}
/**
* Convert a camelCase or snake_case field name to a human-readable label.
* Examples:
*   "firstName"   → "First Name"
*   "createdAt"   → "Created At"
*   "x_resource"  → "X Resource"
*/
function toLabel(name) {
	return name.replace(/_/g, " ").replace(/([a-z])([A-Z])/g, "$1 $2").replace(/^./, (c) => c.toUpperCase());
}
function resolveRangeTag(range, propertyType) {
	if (!range) return "text";
	if (range.startsWith("http://www.w3.org/2001/XMLSchema#")) {
		const local = range.split("#").pop() ?? "";
		if (local === "boolean") return "boolean";
		if (local === "dateTime") return "dateTime";
		if (local === "integer") return "integer";
		if (local === "decimal" || local === "float" || local === "double") return "decimal";
		if (local === "string") return "text";
		return "text";
	}
	if (range === "xmls:boolean") return "boolean";
	if (range === "xmls:dateTime") return "dateTime";
	if (range === "xmls:integer") return "integer";
	if (range === "xmls:decimal") return "decimal";
	if (range === "xmls:string") return "text";
	if (range.startsWith("#")) return "entity";
	if (range.startsWith("http")) return "entity";
	if (propertyType === "Link") return "entity";
	return "text";
}
/**
* Apply `x-crud` backend hints on top of an already-built Field.
*
* Hints override inferred values; inference is the fallback when hints are absent.
* Only properties that are explicitly set (`!== undefined`) are applied.
*
* `hidden: true` sets `visible: false` so the column is hidden in the grid.
* `order` maps to the `Field.order` property used by native grid column ordering.
*/
function applyCrudHints(field, hints) {
	if (!hints) return;
	if (hints.filterable !== void 0) field.filterable = hints.filterable;
	if (hints.sortable !== void 0) field.sortable = hints.sortable;
	if (hints.hidden !== void 0 && hints.hidden) field.visible = false;
	if (hints.order !== void 0) field.order = hints.order;
	if (hints.width !== void 0) field.width = hints.width;
}
function resolveEntityValueField(relatedSchema) {
	if (!relatedSchema) return "_iri";
	const fieldNames = new Set(relatedSchema.fields.map((field) => field.name));
	if (fieldNames.has("id") || fieldNames.has("@id")) return "_iri";
	return "_iri";
}
function isStringLikeField(field) {
	return field.range === void 0 || field.range === "xmls:string" || field.range === "xsd:string";
}
function resolveEntityTextField(relatedSchema, valueField) {
	if (!relatedSchema) return "name";
	for (const fieldName of [
		"name",
		"businessName",
		"description",
		"fullNumber",
		"title",
		"series"
	]) {
		const match = relatedSchema.fields.find((field) => field.name === fieldName && isStringLikeField(field));
		if (match) return match.name;
	}
	const firstStringField = relatedSchema.fields.find((field) => field.name !== valueField && isStringLikeField(field));
	if (firstStringField) return firstStringField.name;
	return valueField;
}
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
function mapHydraSchemaToFields(schema, urlLookup, schemaLookup) {
	const fields = [];
	const filterableProperties = new Set((schema.searchMappings ?? []).map((m) => m.property));
	let hasIdField = false;
	for (const fieldSchema of schema.fields) {
		const { name, range, propertyType, required, readable, writeable } = fieldSchema;
		if (name === "id" || name === "@id") {
			hasIdField = true;
			const idField = {
				...isIntegerRange(range) ? numberField().name("id").label("Id").required(false).precision(0).build() : textField().name("id").label("Id").required(false).build(),
				isIdentity: true,
				visible: false,
				readonly: true,
				filterable: false,
				sortable: false,
				hideable: false,
				visibleOnForm: false
			};
			fields.push(idField);
			continue;
		}
		if (!readable) continue;
		const hydraTitle = fieldSchema["hydra:title"];
		const label = hydraTitle && hydraTitle.trim() !== "" ? hydraTitle : toLabel(name);
		const filterable = filterableProperties.size === 0 ? true : filterableProperties.has(name);
		if (!writeable) {
			const field = {
				...noneField().name(name).label(label).required(required).build(),
				filterable
			};
			applyCrudHints(field, fieldSchema.crudHints);
			fields.push(field);
			continue;
		}
		const tag = resolveRangeTag(range, propertyType);
		if (tag === "boolean") {
			const field = {
				...switchField().name(name).label(label).required(required).build(),
				filterable
			};
			applyCrudHints(field, fieldSchema.crudHints);
			fields.push(field);
			continue;
		}
		if (tag === "dateTime") {
			const field = {
				...datetimeField().name(name).label(label).required(required).build(),
				filterable
			};
			applyCrudHints(field, fieldSchema.crudHints);
			fields.push(field);
			continue;
		}
		if (tag === "integer") {
			const field = {
				...numberField().name(name).label(label).required(required).precision(0).build(),
				filterable
			};
			applyCrudHints(field, fieldSchema.crudHints);
			fields.push(field);
			continue;
		}
		if (tag === "decimal") {
			const field = {
				...numberField().name(name).label(label).required(required).build(),
				filterable
			};
			applyCrudHints(field, fieldSchema.crudHints);
			fields.push(field);
			continue;
		}
		if (tag === "entity" || propertyType === "Link") {
			const resourceClass = range ? range.replace("#", "") : "";
			const relatedSchema = resourceClass ? schemaLookup?.(resourceClass) : void 0;
			const url = resourceClass ? urlLookup?.(resourceClass) ?? `/api/${pluralize(toDashCase(resourceClass))}` : "";
			const valueField = resolveEntityValueField(relatedSchema);
			const field = {
				...entityField(url, valueField, resolveEntityTextField(relatedSchema, valueField)).name(name).label(label).required(required).build(),
				filterable
			};
			applyCrudHints(field, fieldSchema.crudHints);
			fields.push(field);
			continue;
		}
		const field = {
			...textField().name(name).label(label).required(required).build(),
			filterable
		};
		applyCrudHints(field, fieldSchema.crudHints);
		fields.push(field);
	}
	if (!hasIdField) {
		const syntheticId = {
			...textField().name("id").label("Id").required(false).build(),
			isIdentity: true,
			visible: false,
			readonly: true,
			filterable: false,
			sortable: false,
			hideable: false,
			visibleOnForm: false
		};
		fields.unshift(syntheticId);
	}
	return fields;
}
//#endregion
//#region assets/api-platform/useHydraMetadata.ts
const JSONLD_URL = "/api/docs.jsonld";
const JSON_URL = "/api/docs.json";
const isDev = typeof process !== "undefined" && process.env?.NODE_ENV !== "production";
/**
* Try to fetch and validate a Hydra JSON-LD doc.
* Returns the typed HydraApiDoc on success, or throws on failure.
*/
async function fetchHydraDoc(httpClient, url) {
	const { data: json } = await httpClient.get(url);
	const jsonType = json["@type"];
	if (jsonType !== "ApiDocumentation" && jsonType !== "hydra:ApiDocumentation" || !Array.isArray(json["supportedClass"])) throw new Error("useHydraMetadata: response is not a valid Hydra ApiDocumentation");
	return json;
}
/**
* Try to fetch and validate an OpenAPI 3.1 doc.
* Returns the typed OpenApiDoc on success, or throws on failure.
*/
async function fetchOpenApiDoc(httpClient, url) {
	const { data: json } = await httpClient.get(url);
	if (typeof json.openapi !== "string" || !json.openapi.startsWith("3")) throw new Error("useHydraMetadata: response is not a valid OpenAPI 3.x doc");
	return json;
}
/**
* Stable query key used by useHydraMetadata.
* Export this so that consumers (e.g. SmartCrudPage retry button) can
* invalidate exactly the right query without coupling to an internal string.
*/
const API_DOC_QUERY_KEY = ["api-doc-discovery"];
/**
* Waterfall discovery:
*   1. Try /api/docs.jsonld (prefer Hydra — richer type info)
*   2. Fall back to /api/docs.json (OpenAPI 3.1)
*   3. Throw an Error if both fail so React Query sets isError = true
*/
async function fetchApiDoc(httpClient) {
	try {
		return {
			format: "hydra",
			doc: await fetchHydraDoc(httpClient, JSONLD_URL)
		};
	} catch {}
	try {
		return {
			format: "openapi",
			doc: await fetchOpenApiDoc(httpClient, JSON_URL)
		};
	} catch {}
	throw new Error(`Failed to load API documentation from ${JSONLD_URL} and ${JSON_URL}. Make sure the API server is running.`);
}
function useHydraMetadata() {
	const httpClient = useCoreHttpClient();
	const { locale } = useCoreConfig();
	const { data, error, isPending } = useQuery({
		queryKey: [...API_DOC_QUERY_KEY, locale],
		queryFn: () => fetchApiDoc(httpClient),
		staleTime: isDev ? 0 : 5 * 6e4,
		refetchOnWindowFocus: isDev,
		refetchOnReconnect: isDev
	});
	return {
		data,
		isLoading: isPending,
		error: error ?? void 0
	};
}
//#endregion
//#region assets/api-platform/SchemaContext.tsx
/**
* SchemaContext — shared parsed schema, no per-page re-fetch.
*
* Provides a single shared `useHydraMetadata()` call at the provider level
* so that multiple `SmartCrudPage` instances mounted simultaneously share
* one `/api/docs.jsonld` request instead of each issuing their own.
*
* ## Usage
*
* ```tsx
* // In your app root (optional — SmartCrudPage still works without it):
* <SchemaProvider>
*   <App />
* </SchemaProvider>
* ```
*
* ## Fallback behaviour
* If `useSchemaContext()` is called outside a `<SchemaProvider>`, it falls
* back to calling `useHydraMetadata()` directly, keeping backward compat.
*/
/** Sentinel value used to detect "outside a provider" */
const OUTSIDE_PROVIDER = Symbol("OUTSIDE_PROVIDER");
const SchemaContext = createContext(OUTSIDE_PROVIDER);
/**
* Wrap your app (or a subtree) with `SchemaProvider` to share a single
* `/api/docs.jsonld` fetch across all `SmartCrudPage` instances.
*/
function SchemaProvider({ children }) {
	const metadata = useHydraMetadata();
	return /* @__PURE__ */ jsx(SchemaContext.Provider, {
		value: metadata,
		children
	});
}
/**
* Returns the shared schema context if inside a `<SchemaProvider>`,
* otherwise falls back to calling `useHydraMetadata()` directly.
*
* This makes `SmartCrudPage` work standalone without requiring a provider.
*/
function useSchemaContext() {
	const ctx = useContext(SchemaContext);
	const fallback = useHydraMetadata();
	if (ctx === OUTSIDE_PROVIDER) return {
		data: fallback.data,
		isLoading: fallback.isLoading,
		isError: fallback.error !== void 0,
		error: fallback.error
	};
	return {
		data: ctx.data,
		isLoading: ctx.isLoading,
		isError: ctx.error !== void 0,
		error: ctx.error
	};
}
//#endregion
//#region assets/api-platform/useResourceSchema.ts
/**
* Normalizes an API URL by stripping any leading slash so that
* 'api/categories' and '/api/categories' are treated as equal.
*/
function normalizeUrl(url) {
	return url.startsWith("/") ? url.slice(1) : url;
}
function useResourceSchema(apiUrl, manualFields, overrides, fieldContract) {
	const { data, isLoading, error } = useSchemaContext();
	const resolveWithRuntimeErrors = (resolver, supportedOperations = []) => {
		try {
			return {
				fields: resolver(),
				isLoading: false,
				error: void 0,
				supportedOperations
			};
		} catch (runtimeError) {
			return {
				fields: [],
				isLoading: false,
				error: runtimeError instanceof Error ? runtimeError : new Error(String(runtimeError)),
				supportedOperations
			};
		}
	};
	if (fieldContract?.source === "manual") return resolveWithRuntimeErrors(() => resolveSmartCrudFields({ contract: fieldContract }));
	if (manualFields && manualFields.length > 0) return {
		fields: manualFields,
		isLoading: false,
		error: void 0,
		supportedOperations: []
	};
	if (!data) return {
		fields: [],
		isLoading,
		error,
		supportedOperations: []
	};
	const resourceMap = data.format === "hydra" ? parseHydraDoc(data.doc) : parseOpenApiDoc(data.doc);
	const normalizedInput = normalizeUrl(apiUrl);
	const resourceSchema = Object.values(resourceMap).find((r) => normalizeUrl(r.apiUrl) === normalizedInput);
	if (!resourceSchema) return {
		fields: [],
		isLoading: false,
		error: /* @__PURE__ */ new Error(`No schema found for ${apiUrl} in API doc`),
		supportedOperations: []
	};
	const inferred = mapHydraSchemaToFields(resourceSchema, (className) => resourceMap[className]?.apiUrl, (className) => resourceMap[className]);
	return resolveWithRuntimeErrors(() => resolveSmartCrudFields({
		baselineFields: inferred,
		contract: fieldContract,
		legacyOverrides: fieldContract ? void 0 : overrides
	}), resourceSchema.supportedOperations ?? []);
}
//#endregion
export { API_DOC_QUERY_KEY, ApiPlatformRemoteDataSource, SchemaProvider, mapHydraSchemaToFields, parseHydraDoc, parseOpenApiDoc, resolveRangeTag, useHydraMetadata, useResourceSchema, useSchemaContext };
