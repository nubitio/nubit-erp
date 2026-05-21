Object.defineProperty(exports, Symbol.toStringTag, { value: "Module" });
//#region \0rolldown/runtime.js
var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __exportAll = (all, no_symbols) => {
	let target = {};
	for (var name in all) __defProp(target, name, {
		get: all[name],
		enumerable: true
	});
	if (!no_symbols) __defProp(target, Symbol.toStringTag, { value: "Module" });
	return target;
};
var __copyProps = (to, from, except, desc) => {
	if (from && typeof from === "object" || typeof from === "function") for (var keys = __getOwnPropNames(from), i = 0, n = keys.length, key; i < n; i++) {
		key = keys[i];
		if (!__hasOwnProp.call(to, key) && key !== except) __defProp(to, key, {
			get: ((k) => from[k]).bind(null, key),
			enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable
		});
	}
	return to;
};
var __reExport = (target, mod, secondTarget) => (__copyProps(target, mod, "default"), secondTarget && __copyProps(secondTarget, mod, "default"));
var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", {
	value: mod,
	enumerable: true
}) : target, mod));
//#endregion
let react = require("react");
react = __toESM(react, 1);
require("./datagrid/NativeDataGridView.scss");
let react_router_dom = require("react-router-dom");
let react_jsx_runtime = require("react/jsx-runtime");
let react_i18next = require("react-i18next");
require("./dialog/CrudDialogView.scss");
let _nubit_ui = require("@nubit/ui");
require("./form/NativeFormView.scss");
let luxon = require("luxon");
let _tanstack_react_query = require("@tanstack/react-query");
let _nubit_api_platform = require("@nubit/api-platform");
let i18next = require("i18next");
i18next = __toESM(i18next, 1);
//#region assets/core/crud/defineResource.ts
const stringResourceCache = /* @__PURE__ */ new Map();
function cacheKey(apiUrl, overrides) {
	if (!overrides) return apiUrl;
	try {
		return `${apiUrl}::${JSON.stringify(overrides)}`;
	} catch {
		return null;
	}
}
/**
* Derives the resource `id` from a URL string.
* '/api/products'        → 'products'
* '/api/voucher-series'  → 'voucher-series'
* 'http://host/a/b/items' → 'items'
*/
function deriveId(apiUrl) {
	const stripped = apiUrl.replace(/\/+$/, "");
	return (stripped.split("/").pop() ?? stripped).split("?")[0];
}
function defineResource(configOrUrl, overrides) {
	if (typeof configOrUrl === "string") {
		const apiUrl = configOrUrl;
		const key = cacheKey(apiUrl, overrides);
		if (key !== null && stringResourceCache.has(key)) return stringResourceCache.get(key);
		const resource = {
			id: overrides?.id ?? deriveId(apiUrl),
			title: "",
			fields: [],
			...overrides,
			apiUrl
		};
		if (key !== null) stringResourceCache.set(key, resource);
		return resource;
	}
	return configOrUrl;
}
//#endregion
//#region assets/core/crud/usePermissions.ts
/** Resolve a single permission entry: callable → call it, boolean → use it, undefined → fallback. */
function resolve(value, fallback) {
	if (value === void 0) return fallback;
	if (typeof value === "function") return value();
	return value;
}
/**
* Derive permissions from `hydra:supportedOperation` method list.
* Returns `undefined` for each permission when `supportedOperations` is empty
* (signals "no info available — use the next fallback level").
*/
function fromOperations(supportedOperations) {
	if (supportedOperations.length === 0) return void 0;
	return {
		canAdd: supportedOperations.includes("POST"),
		canEdit: supportedOperations.includes("PUT") || supportedOperations.includes("PATCH"),
		canDelete: supportedOperations.includes("DELETE")
	};
}
/**
* Resolves RBAC permissions for a resource with the following precedence
* (highest → lowest):
*   1. `resource.permissions.canX` — callable or boolean override
*   2. `resource.allowX`           — legacy boolean alias
*   3. inferred from `supportedOperations` (hydra:supportedOperation methods)
*   4. platform defaults            — canAdd/Edit/Delete=true, canExport/BulkDelete=false
*
* The `supportedOperations` parameter comes from `useResourceSchema` and is
* only consulted when neither a `resource.permissions.canX` nor a
* `resource.allowX` value was explicitly provided by the caller.
*
* Memoized on `resource.id` — stable reference across re-renders.
*/
function usePermissions(resource, supportedOperations = []) {
	const opsKey = supportedOperations.slice().sort().join(",");
	return (0, react.useMemo)(() => {
		const p = resource.permissions;
		const inferred = fromOperations(supportedOperations);
		function resolveWithInferred(permValue, allowValue, inferredValue, platformDefault) {
			if (permValue !== void 0) return resolve(permValue, platformDefault);
			if (allowValue !== void 0) return allowValue;
			if (inferredValue !== void 0) return inferredValue;
			return platformDefault;
		}
		return {
			canAdd: resolveWithInferred(p?.canAdd, resource.allowAdd, inferred?.canAdd, true),
			canEdit: resolveWithInferred(p?.canEdit, resource.allowEdit, inferred?.canEdit, true),
			canDelete: resolveWithInferred(p?.canDelete, resource.allowDelete, inferred?.canDelete, true),
			canExport: resolve(p?.canExport, resource.allowExport ?? false),
			canBulkDelete: resolve(p?.canBulkDelete, false)
		};
	}, [resource.id, opsKey]);
}
//#endregion
//#region assets/core/crud/useSelectionState.ts
function useSelectionState(identityField) {
	const [selectedIds, setSelectedIds] = (0, react.useState)([]);
	const onSelectionChanged = (0, react.useCallback)((selectedRows) => {
		setSelectedIds(selectedRows.map((row) => {
			const val = row[identityField];
			if (typeof val === "string" || typeof val === "number") return val;
			return String(val);
		}));
	}, [identityField]);
	const clearSelection = (0, react.useCallback)(() => {
		setSelectedIds([]);
	}, []);
	return {
		selectedIds,
		selectedCount: selectedIds.length,
		hasSelection: selectedIds.length > 0,
		clearSelection,
		onSelectionChanged
	};
}
//#endregion
//#region assets/core/crud/useColumnPreset.ts
function readFromStorage(resourceId) {
	try {
		return localStorage.getItem(`column-preset:${resourceId}`);
	} catch {
		return null;
	}
}
function writeToStorage(resourceId, key) {
	try {
		if (key === null) localStorage.removeItem(`column-preset:${resourceId}`);
		else localStorage.setItem(`column-preset:${resourceId}`, key);
	} catch {}
}
function resolveVisibleColumns(resource, activeKey) {
	if (activeKey === null || !resource.columnPresets?.length) return null;
	return resource.columnPresets.find((p) => p.key === activeKey)?.columns ?? null;
}
function useColumnPreset(resource) {
	const [activePreset, setActivePresetState] = (0, react.useState)(() => {
		if (!resource.columnPresets?.length) return null;
		const stored = readFromStorage(resource.id);
		if (stored && resource.columnPresets.some((p) => p.key === stored)) return stored;
		return resource.defaultPreset ?? null;
	});
	return {
		activePreset,
		setPreset: (0, react.useCallback)((key) => {
			writeToStorage(resource.id, key);
			setActivePresetState(key);
		}, [resource.id]),
		visibleColumns: resolveVisibleColumns(resource, activePreset)
	};
}
//#endregion
//#region assets/core/event/createCrudEvents.ts
/**
* Generic factory that builds a typed set of CRUD event name strings for a
* given `prefix`.  The returned object is `const`-asserted so every value is
* a template-literal type — callers get full type inference without having to
* cast anything.
*
* This helper belongs in `core/` because it has no feature-specific logic; it
* is used by both the core event bus and by every feature module that needs
* its own event namespace.
*
* @example
* const productEvents = createCrudEvents('product');
* // productEvents.ADD === 'product:add'
*/
const createCrudEvents = (prefix) => ({
	ADD: `${prefix}:add`,
	EDIT: `${prefix}:edit`,
	DELETE: `${prefix}:delete`,
	SAVE: `${prefix}:save`,
	CANCEL: `${prefix}:cancel`,
	SUCCESS: `${prefix}:success`,
	LOADING: `${prefix}:loading`
});
//#endregion
//#region assets/core/event/createScopedEventBus.ts
/**
* Creates a scoped event bus for the given resource.
*
* Internally this generates an 8-character random scope token and delegates to
* `createCrudEvents(`${resourceId}:${scopeId}`)`. This means each call produces
* a unique set of event name strings, even for the same `resourceId`, which
* prevents accidental cross-instance event coupling when multiple CRUD pages
* for the same resource are mounted simultaneously.
*
* @param resourceId - The resource id (e.g. `'product'`).
* @returns A `ScopedFormEventNames` object with a stable `_scopeId` tag.
*/
function createScopedEventBus(resourceId) {
	const scopeId = Math.random().toString(36).slice(2, 10);
	return {
		...createCrudEvents(`${resourceId}:${scopeId}`),
		_scopeId: scopeId
	};
}
//#endregion
//#region assets/core/crud/resolveCrudResource.ts
function resolveCrudResource(resource, overrides = {}) {
	const events = resource.events ?? createScopedEventBus(resource.id);
	return {
		...resource,
		...overrides,
		events
	};
}
//#endregion
//#region assets/core/crud/useCrudPage.ts
function useCrudPage(resource) {
	const resolvedResource = (0, react.useMemo)(() => resolveCrudResource(resource), [resource]);
	const events = resolvedResource.events;
	const formRef = (0, react.useRef)(null);
	const fields = (0, react.useMemo)(() => {
		if (typeof resolvedResource.fields === "function") return resolvedResource.fields(formRef);
		return resolvedResource.fields;
	}, [resolvedResource.fields]);
	return {
		events,
		resource: resolvedResource,
		fields,
		formRef,
		permissions: usePermissions(resolvedResource, resolvedResource._supportedOperations ?? []),
		selectionState: useSelectionState((0, react.useMemo)(() => {
			return (typeof resolvedResource.fields === "function" ? fields : resolvedResource.fields).find((f) => f.isIdentity)?.name ?? "id";
		}, [fields, resolvedResource.fields])),
		presetState: useColumnPreset(resolvedResource)
	};
}
//#endregion
//#region assets/core/datagrid/DataGridEvents.ts
const DATA_GRID_EVENTS = {
	SELECTION_CHANGED: "datagrid:selection.changed",
	TOOLBAR_EVENT: "datagrid:toolbar.event"
};
//#endregion
//#region assets/core/http/CoreHttpClient.ts
function joinUrl(baseUrl, url) {
	if (/^https?:\/\//.test(url) || url.startsWith("/")) return url;
	return `${baseUrl.replace(/\/+$/, "")}/${url.replace(/^\/+/, "")}`;
}
function serializeParams(params) {
	const parts = [];
	function append(prefix, value) {
		if (value === null || value === void 0) return;
		if (Array.isArray(value)) {
			value.forEach((item, index) => append(`${prefix}[${index}]`, item));
			return;
		}
		if (typeof value === "object") {
			Object.entries(value).forEach(([key, nestedValue]) => {
				append(`${prefix}[${key}]`, nestedValue);
			});
			return;
		}
		parts.push(`${encodeURIComponent(prefix)}=${encodeURIComponent(String(value))}`);
	}
	Object.entries(params).forEach(([key, value]) => append(key, value));
	return parts.join("&");
}
function withParams(url, params) {
	if (!params || Object.keys(params).length === 0) return url;
	const query = serializeParams(params);
	if (!query) return url;
	return `${url}${url.includes("?") ? "&" : "?"}${query}`;
}
function createHttpError(message, status, data) {
	return Object.assign(new Error(message), {
		status,
		data
	});
}
async function readResponseBody(response, responseType) {
	if (response.status === 204) return;
	if (responseType === "arraybuffer") return await response.arrayBuffer();
	if (responseType === "blob") return await response.blob();
	if (responseType === "text") return await response.text();
	return await response.json();
}
var CoreHttpClient = class {
	config;
	refreshPromise = null;
	constructor(config = {}) {
		this.config = config;
	}
	headers(extraHeaders) {
		const browserLocale = typeof navigator !== "undefined" ? navigator.language?.split("-")[0] : void 0;
		return {
			"Accept-Language": this.config.locale ?? browserLocale ?? "es",
			...extraHeaders
		};
	}
	async refreshSession() {
		const refreshPath = this.config.refreshPath ?? "auth/refresh";
		const refreshUrl = joinUrl(this.config.baseUrl ?? "/api/", refreshPath);
		this.refreshPromise ??= globalThis.fetch(refreshUrl, {
			method: "POST",
			credentials: this.config.credentials ?? "include"
		}).then(async (response) => {
			if (!response.ok) throw createHttpError("Session refresh failed", response.status, await this.safeErrorData(response));
		}).finally(() => {
			this.refreshPromise = null;
		});
		return this.refreshPromise;
	}
	async safeErrorData(response) {
		try {
			return await response.json();
		} catch {
			return {};
		}
	}
	async request(method, url, body, config, retryOnUnauthorized = true) {
		const requestUrl = withParams(joinUrl(this.config.baseUrl ?? "/api/", url), config?.params);
		const headers = this.headers(config?.headers);
		if (body !== void 0 && body !== null && !(body instanceof FormData)) headers["Content-Type"] = headers["Content-Type"] ?? "application/json";
		const response = await globalThis.fetch(requestUrl, {
			method,
			headers,
			credentials: this.config.credentials ?? "include",
			signal: config?.signal,
			body: body === void 0 || body === null ? void 0 : body instanceof FormData ? body : JSON.stringify(body)
		});
		if (response.ok) return {
			response,
			data: await readResponseBody(response, config?.responseType),
			headers: response.headers,
			status: response.status
		};
		const errorData = await this.safeErrorData(response);
		const error = createHttpError(errorData.detail ?? errorData.message ?? "HTTP request failed", response.status, errorData);
		const loginPath = this.config.loginPath ?? "auth/login";
		const refreshPath = this.config.refreshPath ?? "auth/refresh";
		const isAuthEndpoint = url.includes(loginPath) || url.includes(refreshPath);
		if (response.status === 401 && retryOnUnauthorized && !isAuthEndpoint) try {
			await this.refreshSession();
			return this.request(method, url, body, config, false);
		} catch (refreshError) {
			const unauthorizedError = refreshError instanceof Error ? Object.assign(refreshError, { status: 401 }) : error;
			this.config.onUnauthorized?.(unauthorizedError);
			throw unauthorizedError;
		}
		if (response.status === 401) this.config.onUnauthorized?.(error);
		else this.config.onError?.(error);
		throw error;
	}
	get(url, config) {
		return this.request("GET", url, void 0, config);
	}
	post(url, data, config) {
		return this.request("POST", url, data, config);
	}
	put(url, data, config) {
		return this.request("PUT", url, data, config);
	}
	patch(url, data, config) {
		return this.request("PATCH", url, data, {
			...config,
			headers: {
				...config?.headers,
				"Content-Type": "application/merge-patch+json"
			}
		});
	}
	delete(url, config) {
		return this.request("DELETE", url, void 0, config);
	}
};
function createCoreHttpClient(config) {
	return new CoreHttpClient(config);
}
//#endregion
//#region assets/core/http/CoreHttpContext.tsx
const CoreHttpContext = (0, react.createContext)(createCoreHttpClient());
function CoreHttpProvider({ children, client, config }) {
	const value = (0, react.useMemo)(() => client ?? createCoreHttpClient(config), [client, config]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CoreHttpContext.Provider, {
		value,
		children
	});
}
function useCoreHttpClient() {
	return (0, react.useContext)(CoreHttpContext);
}
//#endregion
//#region assets/core/data/ApiPlatformRemoteDataSource.ts
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
//#region assets/core/field/FieldType.ts
let FieldType = /* @__PURE__ */ function(FieldType) {
	FieldType["NONE"] = "none";
	FieldType["TEXT"] = "text";
	FieldType["PASSWORD"] = "password";
	FieldType["TEXTAREA"] = "textarea";
	FieldType["DATE"] = "date";
	FieldType["DATETIME"] = "datetime";
	FieldType["NUMBER"] = "number";
	FieldType["CURRENCY"] = "currency";
	FieldType["SELECT"] = "select";
	FieldType["ENUM"] = "enum";
	FieldType["ENTITY"] = "entity";
	FieldType["RADIO"] = "radio";
	FieldType["SWITCH"] = "switch";
	FieldType["CHECKBOX"] = "checkbox";
	FieldType["FILE"] = "file";
	FieldType["TAGS"] = "tags";
	FieldType["HTML"] = "html";
	return FieldType;
}({});
//#endregion
//#region assets/core/event/EventHook.ts
const listeners = /* @__PURE__ */ new Map();
function subscribe$1(name, handler) {
	if (!listeners.has(name)) listeners.set(name, /* @__PURE__ */ new Set());
	const set = listeners.get(name);
	set.add(handler);
	const subscription = {
		unsubscribe: () => {
			set.delete(handler);
		},
		add: (teardown) => {
			const outer = subscription.unsubscribe;
			subscription.unsubscribe = () => {
				outer();
				teardown();
			};
			return subscription;
		}
	};
	return subscription;
}
const useEvents = () => {
	const subsRef = (0, react.useRef)([]);
	(0, react.useEffect)(() => {
		return () => {
			subsRef.current.forEach((sub) => sub.unsubscribe());
			subsRef.current = [];
		};
	}, []);
	const on = (name, handler) => {
		const sub = subscribe$1(name, handler);
		subsRef.current.push(sub);
		return sub;
	};
	const emit = (name, payload) => {
		dispatch(name, payload);
	};
	return [on, emit];
};
const dispatch = (name, payload) => {
	listeners.get(name)?.forEach((handler) => {
		try {
			handler(payload);
		} catch {}
	});
};
//#endregion
//#region assets/core/i18n/useCoreTranslation.ts
function useCoreTranslation() {
	const { t } = (0, react_i18next.useTranslation)("core");
	return { t: (key, options) => t(key, options) };
}
//#endregion
//#region assets/core/runtime/CoreRuntimeContext.tsx
const defaultRuntime = {
	notify: () => void 0,
	confirm: (message) => {
		if (typeof window === "undefined") return false;
		return window.confirm(message);
	}
};
const CoreRuntimeContext = (0, react.createContext)(defaultRuntime);
function CoreRuntimeProvider({ children, runtime }) {
	const value = (0, react.useMemo)(() => ({
		...defaultRuntime,
		...runtime
	}), [runtime]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CoreRuntimeContext.Provider, {
		value,
		children
	});
}
function useCoreRuntime() {
	return (0, react.useContext)(CoreRuntimeContext);
}
//#endregion
//#region assets/core/datagrid/NativeDataGridView.tsx
function getPageRange(current, total) {
	if (total <= 7) return Array.from({ length: total }, (_, i) => i);
	const result = [0];
	if (current > 3) result.push(null);
	for (let i = Math.max(1, current - 1); i <= Math.min(total - 2, current + 1); i++) result.push(i);
	if (current < total - 4) result.push(null);
	result.push(total - 1);
	return result;
}
const PAGE_SIZE_OPTIONS = [
	10,
	20,
	50
];
function getIdField(fields) {
	return fields.find((field) => field.isIdentity)?.name ?? "id";
}
function getVisibleFields(options) {
	return options.fields.filter((field) => field.visible && !field.hidden).filter((field) => options.visibleColumns == null || options.visibleColumns.includes(field.name)).sort((a, b) => (a.order ?? Number.MAX_SAFE_INTEGER) - (b.order ?? Number.MAX_SAFE_INTEGER));
}
function getPrimitiveDisplay(value) {
	if (value === null || value === void 0) return "";
	if (value instanceof Date) return value.toLocaleDateString();
	if (typeof value === "boolean") return value ? "Si" : "No";
	if (typeof value === "object") return "";
	return String(value);
}
function getEntityDisplayValue(field, value) {
	if (value && typeof value === "object") {
		const record = value;
		return getPrimitiveDisplay(record[field.textField] ?? record["name"] ?? record["businessName"]);
	}
	return getPrimitiveDisplay(value);
}
function getEnumDisplayValue(field, value) {
	const match = Array.isArray(field.data) ? field.data.find((item) => item["value"] === value || item[field.valueField] === value) : void 0;
	return getPrimitiveDisplay(match?.["text"] ?? match?.[field.textField] ?? value);
}
function getCellText(field, row) {
	if (field.formatter) return "";
	const value = row[field.name];
	if (field.type === "entity") return getEntityDisplayValue(field, value);
	if (field.type === "enum" || field.type === "switch") return getEnumDisplayValue(field, value);
	return getPrimitiveDisplay(value);
}
function renderCell(field, row, rowIndex, columnIndex) {
	const value = row[field.name];
	if (field.formatter) return field.formatter({
		value,
		data: row,
		rowIndex,
		columnIndex
	});
	if (field.type === "entity") return getEntityDisplayValue(field, value);
	if (field.type === "enum" || field.type === "switch") return getEnumDisplayValue(field, value);
	return getPrimitiveDisplay(value);
}
function buildToolbarButtons(items = []) {
	return items.flatMap((item, index) => {
		return (Array.isArray(item) ? item : [item]).map((button, subIndex) => {
			if (button.visible === false) return null;
			return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
				type: "button",
				className: "app-button app-button--secondary app-button--sm",
				onClick: button.onClick,
				disabled: button.disabled,
				title: button.hint,
				"aria-label": button.hint ?? button.text,
				children: [button.icon && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
					className: `ph ${button.icon}`,
					"aria-hidden": "true"
				}), button.text && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: button.text })]
			}, `${index}-${subIndex}`);
		});
	});
}
function getDefaultFilterOperator(field) {
	if (field.selectedFilterOperation) return field.selectedFilterOperation;
	switch (field.type) {
		case "number":
		case "currency":
		case "date":
		case "datetime":
		case "enum":
		case "select":
		case "switch":
		case "entity": return "=";
		default: return "contains";
	}
}
function getFilterOperators(field) {
	switch (field.type) {
		case "number":
		case "currency": return [
			{
				value: "=",
				label: "="
			},
			{
				value: "<>",
				label: "≠"
			},
			{
				value: ">",
				label: ">"
			},
			{
				value: ">=",
				label: "≥"
			},
			{
				value: "<",
				label: "<"
			},
			{
				value: "<=",
				label: "≤"
			}
		];
		case "date":
		case "datetime": return [
			{
				value: "=",
				label: "="
			},
			{
				value: ">",
				label: ">"
			},
			{
				value: ">=",
				label: "≥"
			},
			{
				value: "<",
				label: "<"
			},
			{
				value: "<=",
				label: "≤"
			}
		];
		case "enum":
		case "select":
		case "switch":
		case "entity": return [{
			value: "=",
			label: "="
		}];
		default: return [
			{
				value: "contains",
				label: "~"
			},
			{
				value: "notcontains",
				label: "!~"
			},
			{
				value: "startswith",
				label: "^"
			},
			{
				value: "=",
				label: "="
			},
			{
				value: "<>",
				label: "≠"
			}
		];
	}
}
function computeDefaultOperators(fields) {
	const defaults = {};
	fields.forEach((field) => {
		if (field.filterable) defaults[field.name] = getDefaultFilterOperator(field);
	});
	return defaults;
}
function buildFilterExpression(filters, operators) {
	return Object.entries(filters).filter(([, value]) => value.trim() !== "").map(([fieldName, value]) => [
		fieldName,
		operators[fieldName] ?? "contains",
		value
	]);
}
function FilterCell({ field, operator, remoteOptions, value, onClear, onInputChange, onSelectChange, onOperatorChange }) {
	if (field.type === "enum" || field.type === "select" || field.type === "switch" || field.type === "entity") {
		const isEntity = field.type === "entity";
		const items = isEntity ? remoteOptions : field.data ?? [];
		return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("select", {
			className: "native-datagrid__filter-select",
			value,
			"aria-label": `Filtrar ${field.label}`,
			onChange: (e) => onSelectChange(e.target.value),
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
				value: "",
				children: "Todos"
			}), isEntity ? items.map((item) => {
				const v = field.valueField === "_iri" ? String(item["_iri"] ?? item["@id"] ?? item["id"] ?? "") : String(item[field.valueField] ?? item["id"] ?? "");
				return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
					value: v,
					children: String(item[field.textField] ?? item["name"] ?? item["businessName"] ?? "")
				}, v);
			}) : items.map((item) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
				value: String(item["value"] ?? ""),
				children: String(item["text"] ?? item["value"] ?? "")
			}, String(item["value"] ?? "")))]
		});
	}
	const operators = getFilterOperators(field);
	const inputType = field.type === "number" || field.type === "currency" ? "number" : field.type === "date" ? "date" : field.type === "datetime" ? "datetime-local" : "text";
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "native-datagrid__filter-wrap",
		children: [
			operators.length > 1 ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("select", {
				className: "native-datagrid__filter-operator",
				value: operator,
				"aria-label": "Operador de filtro",
				onChange: (e) => onOperatorChange(e.target.value),
				children: operators.map((op) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
					value: op.value,
					children: op.label
				}, op.value))
			}) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
				className: "ph ph-magnifying-glass native-datagrid__filter-icon",
				"aria-hidden": "true"
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
				type: inputType,
				className: "native-datagrid__filter-input",
				value,
				"aria-label": `Filtrar ${field.label}`,
				onChange: (e) => onInputChange(e.target.value)
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
				type: "button",
				className: `native-datagrid__filter-clear${value ? " is-active" : ""}`,
				"aria-label": "Limpiar filtro",
				tabIndex: value ? 0 : -1,
				onClick: onClear,
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
					className: "ph ph-x",
					"aria-hidden": "true"
				})
			})
		]
	});
}
function SummaryView({ summaryFields, rows }) {
	if (!summaryFields?.length) return null;
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "native-datagrid__summary",
		children: summaryFields.map((summary, index) => {
			const values = rows.map((row) => Number(row[summary.column ?? ""] ?? 0));
			const value = summary.summaryType === "sum" ? values.reduce((total, next) => total + (Number.isFinite(next) ? next : 0), 0) : values.length;
			return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: summary.customizeText ? summary.customizeText({
				value,
				valueText: String(value)
			}) : summary.displayFormat ? summary.displayFormat.replace("{0}", String(value)) : String(value) }, index);
		})
	});
}
function DetailRows({ fields, url }) {
	const httpClient = useCoreHttpClient();
	const [rows, setRows] = (0, react.useState)([]);
	const [loading, setLoading] = (0, react.useState)(true);
	(0, react.useEffect)(() => {
		const source = new ApiPlatformRemoteDataSource({
			url,
			idField: getIdField(fields),
			httpClient
		});
		setLoading(true);
		source.load({}).then((result) => setRows(result.data)).finally(() => setLoading(false));
	}, [
		fields,
		httpClient,
		url
	]);
	if (loading) return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "native-datagrid__loading",
		children: "Cargando..."
	});
	if (rows.length === 0) return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "native-datagrid__empty",
		children: "Sin detalle"
	});
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "native-datagrid__detail-content",
		children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("table", {
			className: "native-datagrid__detail-table",
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("thead", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tr", { children: fields.filter((field) => field.visible && !field.hidden).map((field) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", { children: field.label }, field.name)) }) }), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tbody", { children: rows.map((row, rowIndex) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tr", { children: fields.filter((field) => field.visible && !field.hidden).map((field, columnIndex) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", { children: renderCell(field, row, rowIndex, columnIndex) }, field.name)) }, String(row[getIdField(fields)] ?? rowIndex))) })]
		})
	});
}
const NativeDataGridView = (0, react.forwardRef)((options, ref) => {
	const { t } = useCoreTranslation();
	const { notify } = useCoreRuntime();
	const httpClient = useCoreHttpClient();
	const navigate = (0, react_router_dom.useNavigate)();
	const [on, emit] = useEvents();
	const idField = (0, react.useMemo)(() => getIdField(options.fields), [options.fields]);
	const visibleFields = (0, react.useMemo)(() => getVisibleFields(options), [options]);
	const [rows, setRows] = (0, react.useState)([]);
	const rowsRef = (0, react.useRef)([]);
	const [totalCount, setTotalCount] = (0, react.useState)(0);
	const [selectedKeys, setSelectedKeys] = (0, react.useState)([]);
	const [filters, setFilters] = (0, react.useState)({});
	const [filterInputs, setFilterInputs] = (0, react.useState)({});
	const filterDebounceRef = (0, react.useRef)(void 0);
	const [filterOperators, setFilterOperators] = (0, react.useState)(() => computeDefaultOperators(options.fields));
	const [filterRemoteOptions, setFilterRemoteOptions] = (0, react.useState)({});
	const loadedFilterOptionsRef = (0, react.useRef)(/* @__PURE__ */ new Set());
	const [sort, setSort] = (0, react.useState)(options.sort ?? []);
	const [pageSize, setPageSize] = (0, react.useState)(options.pageSize ?? 20);
	const [confirmRow, setConfirmRow] = (0, react.useState)(null);
	const [page, setPage] = (0, react.useState)(0);
	const [expandedKeys, setExpandedKeys] = (0, react.useState)(() => /* @__PURE__ */ new Set());
	const [isGridLoading, setIsGridLoading] = (0, react.useState)(!options.manualLoad);
	const [loadingMessage, setLoadingMessage] = (0, react.useState)("");
	const onContentReadyRef = (0, react.useRef)(options.onContentReady);
	(0, react.useEffect)(() => {
		onContentReadyRef.current = options.onContentReady;
	}, [options.onContentReady]);
	const source = (0, react.useMemo)(() => new ApiPlatformRemoteDataSource({
		url: options.url,
		idField,
		defaultFilterRules: options.filter,
		defaultSortRules: sort,
		httpClient
	}), [
		httpClient,
		idField,
		options.filter,
		options.url,
		sort
	]);
	const loadRows = (0, react.useCallback)(async () => {
		if (options.manualLoad) return rowsRef.current;
		setIsGridLoading(true);
		const loadOptions = {
			filter: buildFilterExpression(filters, filterOperators),
			sort
		};
		if (options.paging ?? true) {
			loadOptions.skip = page * pageSize;
			loadOptions.take = pageSize;
		}
		const result = await source.load(loadOptions);
		rowsRef.current = result.data;
		setRows(result.data);
		setTotalCount(result.totalCount);
		setIsGridLoading(false);
		onContentReadyRef.current?.();
		return result.data;
	}, [
		filterOperators,
		filters,
		options.manualLoad,
		options.paging,
		page,
		pageSize,
		sort,
		source
	]);
	(0, react.useEffect)(() => {
		loadRows();
	}, [loadRows]);
	(0, react.useEffect)(() => {
		if (options.events?.SUCCESS) on(options.events.SUCCESS, () => void loadRows());
	}, [
		loadRows,
		on,
		options.events
	]);
	(0, react.useEffect)(() => {
		visibleFields.filter((f) => f.filterable && f.type === "entity" && f.url && !loadedFilterOptionsRef.current.has(f.name)).forEach((f) => {
			loadedFilterOptionsRef.current.add(f.name);
			new ApiPlatformRemoteDataSource({
				url: f.url,
				idField: f.valueField,
				httpClient
			}).load({ take: 500 }).then((result) => setFilterRemoteOptions((prev) => ({
				...prev,
				[f.name]: result.data
			}))).catch(() => {});
		});
	}, [httpClient, visibleFields]);
	(0, react.useImperativeHandle)(ref, () => ({
		showLoading: (message) => {
			setLoadingMessage(message ?? t("grid.loading"));
			setIsGridLoading(true);
		},
		hideLoading: () => setIsGridLoading(false),
		refresh: () => void loadRows(),
		reset: () => {
			clearTimeout(filterDebounceRef.current);
			setSelectedKeys([]);
			setFilters({});
			setFilterInputs({});
			setFilterOperators(computeDefaultOperators(options.fields));
			setSort([]);
			setPage(0);
			setExpandedKeys(/* @__PURE__ */ new Set());
		},
		loadData: () => Promise.resolve(rowsRef.current),
		getSelectedRowKey: () => {
			const key = selectedKeys[0];
			return typeof key === "string" || typeof key === "number" ? key : void 0;
		},
		getSelectedRow: () => rowsRef.current.find((row) => row[idField] === selectedKeys[0]),
		getSelectedRowKeys: () => selectedKeys,
		getSelectedRows: () => rowsRef.current.filter((row) => selectedKeys.includes(row[idField])),
		getFilter: () => buildFilterExpression(filters, filterOperators),
		filter: (filterRule) => {
			const nextFilters = filterRule ? { [filterRule.field]: String(filterRule.value ?? "") } : {};
			const nextOperators = filterRule ? {
				...filterOperators,
				[filterRule.field]: filterRule.operator || getDefaultFilterOperator(options.fields.find((f) => f.name === filterRule.field) ?? options.fields[0])
			} : computeDefaultOperators(options.fields);
			clearTimeout(filterDebounceRef.current);
			setFilters(nextFilters);
			setFilterInputs(nextFilters);
			setFilterOperators(nextOperators);
			setPage(0);
		}
	}));
	const selectedRows = rows.filter((row) => selectedKeys.includes(row[idField]));
	const selectRow = (row) => {
		const key = row[idField];
		const nextKeys = options.selectionMode === "multiple" ? selectedKeys.includes(key) ? selectedKeys.filter((selectedKey) => selectedKey !== key) : [...selectedKeys, key] : [key];
		setSelectedKeys(nextKeys);
		const nextRows = rows.filter((item) => nextKeys.includes(item[idField]));
		options.onSelectionChanged?.({ selectedRowsData: nextRows });
		emit(DATA_GRID_EVENTS.SELECTION_CHANGED, nextRows);
	};
	const onAddClick = () => options.events?.ADD && emit(options.events.ADD);
	const onEditClick = () => {
		const row = selectedRows[0];
		if (row) {
			if (options.events?.EDIT) emit(options.events.EDIT, { row });
		} else notify(t("grid.selectRequired"), "warning", 2e3);
	};
	const onDeleteClick = () => {
		const row = selectedRows[0];
		if (row) setConfirmRow(row);
		else notify(t("grid.selectRequired"), "warning", 2e3);
	};
	const confirmDelete = () => {
		if (confirmRow && options.events?.DELETE) emit(options.events.DELETE, { row: confirmRow });
		setConfirmRow(null);
	};
	const toggleSort = (field) => {
		if (!field.sortable) return;
		setPage(0);
		setSort((current) => {
			const existing = current.find((rule) => rule.selector === field.name);
			if (!existing) return [{
				selector: field.name,
				desc: false
			}];
			if (!existing.desc) return [{
				selector: field.name,
				desc: true
			}];
			return [];
		});
	};
	const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
	const hasCheckbox = options.selectionMode === "multiple";
	const hasRowActions = Boolean(options.inlineActions && (options.allowEdit || options.allowDelete) && options.mode !== "minimal");
	const colSpan = visibleFields.length + (hasCheckbox ? 1 : 0) + (options.detailFields ? 1 : 0) + (hasRowActions ? 1 : 0);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "view datagrid-list",
		children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: `view-wrapper ${options.mode === "full" || !options.mode ? "view-wrapper-datagrid-list list-page" : ""}`,
			children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
				className: "grid theme-dependent native-datagrid",
				children: [
					(options.toolbarVisible ?? true) && options.mode !== "minimal" && /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
						className: "native-datagrid__toolbar",
						children: [
							options.hasBackButton && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
								type: "button",
								className: "app-icon-button",
								"aria-label": "Volver",
								onClick: () => options.onBack ? options.onBack() : navigate(-1),
								children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
									className: "ph ph-arrow-left",
									"aria-hidden": "true"
								})
							}),
							options.mode === "full" || !options.mode ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
								className: "grid-header",
								children: options.title
							}) : null,
							options.beforeToolbar?.(),
							/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", { className: "native-datagrid__toolbar-spacer" }),
							options.allowAdd && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
								type: "button",
								className: "app-button app-button--primary app-button--sm",
								onClick: onAddClick,
								disabled: options.addDisabled,
								children: t("grid.buttonNew")
							}),
							options.allowEdit && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
								type: "button",
								className: "app-button app-button--secondary app-button--sm",
								onClick: onEditClick,
								disabled: options.editDisabled,
								children: t("grid.buttonEdit")
							}),
							options.allowDelete && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
								type: "button",
								className: "app-button app-button--danger app-button--sm",
								onClick: onDeleteClick,
								disabled: options.deleteDisabled,
								children: t("grid.buttonDelete")
							}),
							buildToolbarButtons(options.toolbar),
							/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
								type: "button",
								className: "app-icon-button",
								"aria-label": t("grid.buttonRefresh"),
								onClick: () => void loadRows(),
								children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
									className: "ph ph-arrow-clockwise",
									"aria-hidden": "true"
								})
							})
						]
					}),
					/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
						className: "native-datagrid__table-wrap",
						children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("table", {
							className: "native-datagrid__table",
							children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("thead", { children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tr", { children: [
								options.detailFields && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", {
									className: "native-datagrid__detail-cell",
									style: { width: 36 }
								}),
								hasCheckbox && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", { className: "native-datagrid__select-cell" }),
								visibleFields.map((field) => {
									const sortRule = sort.find((rule) => rule.selector === field.name);
									return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", {
										title: field.label,
										style: {
											width: field.width,
											minWidth: field.minWidth,
											textAlign: field.align
										},
										children: field.sortable ? /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
											type: "button",
											className: "native-datagrid__sort-button",
											onClick: () => toggleSort(field),
											children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: field.label }), sortRule ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
												className: `ph ${sortRule.desc ? "ph-caret-down" : "ph-caret-up"} native-datagrid__sort-icon`,
												"aria-hidden": "true"
											}) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
												className: "ph ph-caret-up-down native-datagrid__sort-hint",
												"aria-hidden": "true"
											})]
										}) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
											className: "native-datagrid__column-label",
											children: field.label
										})
									}, field.name);
								}),
								hasRowActions && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", { className: "native-datagrid__actions-cell" })
							] }), (options.filterRow ?? true) && /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tr", {
								className: "native-datagrid__filter-row",
								children: [
									options.detailFields && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {}),
									hasCheckbox && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", { className: "native-datagrid__select-cell" }),
									visibleFields.map((field) => {
										const applyImmediate = (nextInputs) => {
											clearTimeout(filterDebounceRef.current);
											setFilterInputs(nextInputs);
											setFilters(nextInputs);
											setPage(0);
											options.onFilterChange?.(nextInputs);
										};
										return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
											style: { minWidth: field.minWidth },
											className: field.filterable === false ? "native-datagrid__filter-cell--noop" : void 0,
											children: field.filterable ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)(FilterCell, {
												field,
												operator: filterOperators[field.name] ?? getDefaultFilterOperator(field),
												remoteOptions: filterRemoteOptions[field.name] ?? [],
												value: filterInputs[field.name] ?? "",
												onInputChange: (nextValue) => {
													const nextInputs = {
														...filterInputs,
														[field.name]: nextValue
													};
													if (nextValue === "") delete nextInputs[field.name];
													setFilterInputs(nextInputs);
													clearTimeout(filterDebounceRef.current);
													filterDebounceRef.current = setTimeout(() => {
														setFilters(nextInputs);
														setPage(0);
														options.onFilterChange?.(nextInputs);
													}, 300);
												},
												onSelectChange: (nextValue) => {
													const nextInputs = {
														...filterInputs,
														[field.name]: nextValue
													};
													if (nextValue === "") delete nextInputs[field.name];
													applyImmediate(nextInputs);
												},
												onOperatorChange: (nextOperator) => {
													setFilterOperators((prev) => ({
														...prev,
														[field.name]: nextOperator
													}));
													if (filterInputs[field.name]) applyImmediate({ ...filterInputs });
												},
												onClear: () => {
													const nextInputs = { ...filterInputs };
													delete nextInputs[field.name];
													applyImmediate(nextInputs);
												}
											}) : null
										}, field.name);
									}),
									hasRowActions && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {})
								]
							})] }), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tbody", { children: rows.length === 0 && isGridLoading ? Array.from({ length: 7 }, (_, i) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tr", {
								className: "native-datagrid__skeleton-row",
								children: [
									options.detailFields && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {}),
									hasCheckbox && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
										className: "native-datagrid__select-cell",
										children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
											className: "native-datagrid__skeleton-cell",
											style: {
												width: 16,
												height: 16
											}
										})
									}),
									visibleFields.map((field) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", { className: "native-datagrid__skeleton-cell" }) }, field.name)),
									hasRowActions && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {})
								]
							}, `skeleton-${i}`)) : rows.length === 0 ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tr", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
								colSpan,
								children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
									className: "native-datagrid__empty",
									children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
										className: "ph ph-database",
										"aria-hidden": "true"
									}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: t("grid.noRecords") })]
								})
							}) }) : rows.map((row, rowIndex) => {
								const key = row[idField] ?? rowIndex;
								const detailFields = typeof options.detailFields === "function" ? options.detailFields(row) : options.detailFields;
								const detailUrl = options.detailUrl?.replace("{id}", String(key));
								const expanded = expandedKeys.has(key);
								options.onRowPrepared?.({
									data: row,
									rowIndex
								});
								return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)(react.default.Fragment, { children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tr", {
									className: `native-datagrid__row ${expanded ? "native-datagrid__row--expanded" : ""} ${selectedKeys.includes(key) ? "native-datagrid__row--selected" : ""}`,
									tabIndex: 0,
									onClick: () => selectRow(row),
									onKeyDown: (event) => {
										if (event.key === "Enter" && options.events?.EDIT) emit(options.events.EDIT, { row });
										else if (event.key === "Delete" && options.events?.DELETE) setConfirmRow(row);
										else if (event.key === "ArrowUp" || event.key === "ArrowDown") {
											event.preventDefault();
											const allRows = event.currentTarget.closest("tbody")?.querySelectorAll("tr.native-datagrid__row");
											if (!allRows) return;
											const idx = Array.from(allRows).indexOf(event.currentTarget);
											const next = allRows[event.key === "ArrowUp" ? idx - 1 : idx + 1];
											if (next) {
												next.focus();
												selectRow(rows[event.key === "ArrowUp" ? idx - 1 : idx + 1]);
											}
										}
									},
									children: [
										options.detailFields && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
											className: "native-datagrid__detail-cell",
											children: detailUrl && detailFields && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
												type: "button",
												className: `native-datagrid__expand-button${expanded ? " is-expanded" : ""}`,
												"aria-label": expanded ? "Contraer detalle" : "Expandir detalle",
												onClick: (event) => {
													event.stopPropagation();
													setExpandedKeys((current) => {
														const next = new Set(current);
														if (next.has(key)) next.delete(key);
														else next.add(key);
														return next;
													});
												},
												children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
													className: `ph ${expanded ? "ph-caret-down" : "ph-caret-right"}`,
													"aria-hidden": "true"
												})
											})
										}),
										hasCheckbox && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
											className: "native-datagrid__select-cell",
											children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
												type: "checkbox",
												checked: selectedKeys.includes(key),
												readOnly: true,
												"aria-label": "Seleccionar fila"
											})
										}),
										visibleFields.map((field, columnIndex) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
											style: { textAlign: field.align },
											title: getCellText(field, row),
											children: renderCell(field, row, rowIndex, columnIndex)
										}, field.name)),
										hasRowActions && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
											className: "native-datagrid__actions-cell",
											onClick: (e) => e.stopPropagation(),
											children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
												className: "native-datagrid__row-actions",
												children: [options.allowEdit && options.events?.EDIT && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
													type: "button",
													className: "app-icon-button",
													"aria-label": t("grid.buttonEdit"),
													title: t("grid.buttonEdit"),
													onClick: (e) => {
														e.stopPropagation();
														emit(options.events.EDIT, { row });
													},
													children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
														className: "ph ph-pencil-simple",
														"aria-hidden": "true"
													})
												}), options.allowDelete && options.events?.DELETE && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
													type: "button",
													className: "app-icon-button",
													"aria-label": t("grid.buttonDelete"),
													title: t("grid.buttonDelete"),
													onClick: (e) => {
														e.stopPropagation();
														setConfirmRow(row);
													},
													children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
														className: "ph ph-trash",
														"aria-hidden": "true"
													})
												})]
											})
										})
									]
								}), expanded && detailUrl && detailFields && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tr", {
									className: "native-datagrid__detail-row",
									children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
										colSpan,
										children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(DetailRows, {
											fields: detailFields,
											url: detailUrl
										})
									})
								})] }, String(key));
							}) })]
						})
					}),
					/* @__PURE__ */ (0, react_jsx_runtime.jsx)(SummaryView, {
						summaryFields: options.summaryFields,
						rows
					}),
					(options.paging ?? true) && /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
						className: "native-datagrid__pager",
						children: [
							/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
								className: "native-datagrid__pager-info",
								children: totalCount > 0 ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, totalCount)} de ${totalCount}` : "0 registros"
							}),
							/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
								className: "native-datagrid__pager-nav",
								children: [
									/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage(0),
										disabled: page === 0,
										"aria-label": "Primera página",
										children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
											className: "ph ph-caret-double-left",
											"aria-hidden": "true"
										})
									}),
									/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage((p) => Math.max(0, p - 1)),
										disabled: page === 0,
										"aria-label": "Página anterior",
										children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
											className: "ph ph-caret-left",
											"aria-hidden": "true"
										})
									}),
									getPageRange(page, totalPages).map((pageNum, i) => pageNum === null ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
										className: "native-datagrid__pager-ellipsis",
										children: "…"
									}, `ellipsis-${i}`) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: `native-datagrid__pager-btn${pageNum === page ? " is-current" : ""}`,
										onClick: () => setPage(pageNum),
										"aria-current": pageNum === page ? "page" : void 0,
										children: pageNum + 1
									}, pageNum)),
									/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage((p) => Math.min(totalPages - 1, p + 1)),
										disabled: page + 1 >= totalPages,
										"aria-label": "Página siguiente",
										children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
											className: "ph ph-caret-right",
											"aria-hidden": "true"
										})
									}),
									/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: "native-datagrid__pager-btn",
										onClick: () => setPage(totalPages - 1),
										disabled: page + 1 >= totalPages,
										"aria-label": "Última página",
										children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
											className: "ph ph-caret-double-right",
											"aria-hidden": "true"
										})
									})
								]
							}),
							/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
								className: "native-datagrid__pager-size",
								children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("label", {
									className: "native-datagrid__pager-size-label",
									htmlFor: "native-datagrid-page-size",
									children: t("grid.rowsPerPage")
								}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("select", {
									id: "native-datagrid-page-size",
									className: "native-datagrid__pager-size-select",
									value: pageSize,
									onChange: (e) => {
										setPageSize(Number(e.target.value));
										setPage(0);
									},
									children: PAGE_SIZE_OPTIONS.map((n) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
										value: n,
										children: n
									}, n))
								})]
							})
						]
					}),
					confirmRow && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
						className: "native-datagrid__confirm-overlay",
						role: "alertdialog",
						"aria-modal": "true",
						children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
							className: "native-datagrid__confirm-card",
							children: [
								/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
									className: "native-datagrid__confirm-title",
									children: t("dialog.confirmDeleteTitle")
								}),
								/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
									className: "native-datagrid__confirm-body",
									children: t("dialog.confirmDelete")
								}),
								/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
									className: "native-datagrid__confirm-actions",
									children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: "app-button app-button--secondary app-button--sm",
										onClick: () => setConfirmRow(null),
										children: t("dialog.buttonCancel")
									}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
										type: "button",
										className: "app-button app-button--danger app-button--sm",
										onClick: confirmDelete,
										children: t("grid.buttonDelete")
									})]
								})
							]
						})
					}),
					isGridLoading && rows.length > 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
						className: "native-datagrid__loading-overlay",
						"aria-live": "polite",
						children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
							className: "native-datagrid__loading-card",
							children: loadingMessage || t("grid.loading")
						})
					})
				]
			})
		})
	});
});
NativeDataGridView.displayName = "NativeDataGridView";
//#endregion
//#region assets/utils/media-query.tsx
const useScreenSize = () => {
	const [screenSize, setScreenSize] = (0, react.useState)(getScreenSize());
	const onSizeChanged = (0, react.useCallback)(() => {
		setScreenSize(getScreenSize());
	}, []);
	(0, react.useEffect)(() => {
		subscribe(onSizeChanged);
		return () => {
			unsubscribe(onSizeChanged);
		};
	}, [onSizeChanged]);
	return screenSize;
};
let handlers = [];
const xSmallMedia = window.matchMedia("(max-width: 575.98px)");
const smallMedia = window.matchMedia("(min-width: 576px) and (max-width: 991.98px)");
const mediumMedia = window.matchMedia("(min-width: 992px) and (max-width: 1199.98px)");
const largeMedia = window.matchMedia("(min-width: 1200px)");
let debounceTimer = null;
const notifyHandlers = () => {
	if (debounceTimer !== null) clearTimeout(debounceTimer);
	debounceTimer = setTimeout(() => {
		handlers.forEach((handler) => handler());
	}, 50);
};
[
	xSmallMedia,
	smallMedia,
	mediumMedia,
	largeMedia
].forEach((media) => {
	media.addEventListener("change", (e) => {
		if (e.matches) notifyHandlers();
	});
});
const subscribe = (handler) => handlers.push(handler);
const unsubscribe = (handler) => {
	handlers = handlers.filter((item) => item !== handler);
};
function getScreenSize() {
	return {
		isXSmall: xSmallMedia.matches,
		isSmall: smallMedia.matches,
		isMedium: mediumMedia.matches,
		isLarge: largeMedia.matches
	};
}
//#endregion
//#region assets/core/dialog/CrudDialogView.tsx
const CrudDialogView = (0, react.forwardRef)((options, ref) => {
	const { t } = useCoreTranslation();
	const [title, setTitle] = (0, react.useState)(options.title ?? t("crudPage.dialogTitleAdd"));
	const isControlled = options.isOpen !== void 0;
	const [internalVisible, setInternalVisible] = (0, react.useState)(options.visible ?? false);
	const visible = isControlled ? options.isOpen ?? false : internalVisible;
	const [isProcessing, setIsProcessing] = (0, react.useState)(false);
	const { isXSmall } = useScreenSize();
	const [on, emit] = useEvents();
	const width = options.width ?? 480;
	(0, react.useEffect)(() => {
		if (options.title) setTitle(options.title);
	}, [options.title]);
	const open = (nextTitle) => {
		if (!isControlled) setInternalVisible(true);
		if (nextTitle) setTitle(nextTitle);
	};
	const close = () => {
		if (isProcessing) return;
		if (!isControlled) setInternalVisible(false);
	};
	const handleClose = () => {
		if (isProcessing) return;
		close();
		options.onClose?.();
	};
	const onCancelClick = () => {
		handleClose();
		if (options.events?.CANCEL) emit(options.events.CANCEL);
	};
	const onSaveClick = () => {
		if (options.events?.SAVE) emit(options.events.SAVE);
	};
	const setupObservers = () => {
		if (options.events?.ADD) on(options.events.ADD, (payload) => open(payload?.title ?? t("crudPage.dialogTitleAdd")));
		if (options.events?.EDIT) on(options.events.EDIT, () => open(t("crudPage.dialogTitleEdit")));
		if (options.events?.LOADING) on(options.events.LOADING, (isLoading) => setIsProcessing(isLoading));
		if (options.events?.SUCCESS) on(options.events.SUCCESS, () => close());
	};
	const setupObserversRef = (0, react.useRef)(setupObservers);
	setupObserversRef.current = setupObservers;
	(0, react.useEffect)(() => {
		setupObserversRef.current();
	}, []);
	(0, react.useImperativeHandle)(ref, () => ({
		open,
		close
	}));
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)(_nubit_ui.AppDialog, {
		title,
		open: visible,
		fullScreen: isXSmall,
		width,
		height: options.height ?? "auto",
		maxWidth: options.width ?? void 0,
		onClose: handleClose,
		closeOnEscape: false,
		closeOnOutsideClick: false,
		keepMounted: true,
		rootClassName: "crud-dialog-root",
		className: ["crud-dialog", options.fitContent ? "fit-content" : "form-popup"].join(" "),
		headerClassName: "crud-dialog__header",
		titleClassName: "crud-dialog__title",
		bodyClassName: "crud-dialog__body",
		footerClassName: "crud-dialog__footer",
		footer: options.footerVisible ?? true ? /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: `form-popup-buttons-container ${width <= 360 ? "flex-buttons" : ""}`,
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
				type: "button",
				className: "app-button app-button--secondary",
				onClick: onCancelClick,
				disabled: isProcessing,
				children: options.negativeText ?? t("dialog.buttonCancel")
			}), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
				type: "button",
				className: "app-button app-button--primary dialog-save-button",
				onClick: onSaveClick,
				disabled: isProcessing,
				children: [isProcessing && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
					className: "button-indicator",
					"aria-hidden": "true"
				}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: options.positiveText ?? t("dialog.buttonSave") })]
			})]
		}) : void 0,
		children: [options.children, isProcessing && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: "crud-dialog__loading",
			"aria-hidden": "true",
			children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { className: "crud-dialog__loading-indicator" })
		})]
	});
});
CrudDialogView.displayName = "CrudDialogView";
//#endregion
//#region assets/core/form/FormEvents.ts
const FORM_EVENTS = {
	FIELD_CHANGED: "on:field:changed",
	ROW_ADDED: "on:row:added",
	ROW_REMOVED: "on:row:removed",
	ROW_UPDATED: "on:row:updated",
	LOADING: "on:loading",
	LOADING_DETAIL: "on:loading:detail"
};
const FORM_ERRORS_EVENT = "form-errors";
//#endregion
//#region assets/core/config/CoreConfig.tsx
const _coreConfig = {
	locale: "es",
	timezone: "UTC"
};
function configureCoreDate(config) {
	if (config.locale !== void 0) _coreConfig.locale = config.locale;
	if (config.timezone !== void 0) _coreConfig.timezone = config.timezone;
}
function getCoreLocale() {
	return _coreConfig.locale;
}
function getCoreTimezone() {
	return _coreConfig.timezone;
}
const CoreConfigContext = react.default.createContext(_coreConfig);
const CoreConfigProvider = ({ locale, timezone, children }) => {
	configureCoreDate({
		locale,
		timezone
	});
	const value = react.default.useMemo(() => ({
		locale,
		timezone
	}), [locale, timezone]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CoreConfigContext.Provider, {
		value,
		children
	});
};
function useCoreConfig() {
	return react.default.useContext(CoreConfigContext);
}
//#endregion
//#region assets/core/date/DateUtils.ts
const LIMA_TZ = "America/Lima";
function coreDateParts(date) {
	const parts = new Intl.DateTimeFormat("en-CA", {
		timeZone: getCoreTimezone(),
		year: "numeric",
		month: "2-digit",
		day: "2-digit"
	}).formatToParts(date);
	const get = (type) => parseInt(parts.find((part) => part.type === type).value, 10);
	return {
		year: get("year"),
		month: get("month"),
		day: get("day")
	};
}
var DateUtils = class {
	static dateFormatter(date) {
		const { year, month, day } = coreDateParts(date);
		return `${year}-${month < 10 ? `0${month}` : month}-${day < 10 ? `0${day}` : day}`;
	}
	static dateParser(dateString) {
		if (!dateString) return /* @__PURE__ */ new Date();
		const dateParts = dateString.split("-");
		const year = parseInt(dateParts[0], 10);
		const month = parseInt(dateParts[1], 10);
		const day = parseInt(dateParts[2], 10);
		if (!Number.isNaN(year) && !Number.isNaN(month) && !Number.isNaN(day)) return new Date(year, month - 1, day);
		return /* @__PURE__ */ new Date();
	}
	static format(dateString, formatOptions) {
		return new Date(dateString).toLocaleDateString(getCoreLocale(), {
			timeZone: getCoreTimezone(),
			...formatOptions ?? {
				day: "numeric",
				month: "short",
				year: "numeric"
			}
		});
	}
	static addDays(fieldValue, days) {
		const date = new Date(fieldValue);
		date.setDate(date.getDate() + days);
		return date;
	}
	static addMonths(fieldValue, months) {
		const date = new Date(fieldValue);
		date.setMonth(date.getMonth() + months);
		return date;
	}
	static addYears(fieldValue, years) {
		const date = new Date(fieldValue);
		date.setFullYear(date.getFullYear() + years);
		return date;
	}
	static dayDiff(startDate, endDate) {
		const dateStart = new Date(startDate);
		const dateEnd = new Date(endDate);
		const timeDiff = Math.abs(dateEnd.getTime() - dateStart.getTime());
		return Math.ceil(timeDiff / (1e3 * 3600 * 24));
	}
	static monthDiff(startDate, endDate) {
		const dateStart = new Date(startDate);
		const dateEnd = new Date(endDate);
		let months = (dateEnd.getFullYear() - dateStart.getFullYear()) * 12;
		months -= dateStart.getMonth();
		months += dateEnd.getMonth();
		return months <= 0 ? 0 : months;
	}
};
//#endregion
//#region assets/core/form/FormDataTransform.ts
function trimTrailingSlash(value) {
	return value.replace(/\/+$/, "");
}
function synthesizeEntityIri(field, entityValue) {
	if (!field.url) return void 0;
	const baseUrl = trimTrailingSlash(field.url);
	const directId = entityValue.id;
	if (typeof directId === "string" || typeof directId === "number") return `${baseUrl}/${directId}`;
	const directValue = entityValue[field.valueField];
	if (field.valueField !== "_iri" && (typeof directValue === "string" || typeof directValue === "number")) return `${baseUrl}/${directValue}`;
	for (const key of [
		"code",
		"uuid",
		"slug"
	]) {
		const candidate = entityValue[key];
		if (typeof candidate === "string" || typeof candidate === "number") return `${baseUrl}/${candidate}`;
	}
}
function upsertPrependData(field, item) {
	const existingLoadOption = field.loadOptions.find((option) => Array.isArray(option.prependData));
	if (existingLoadOption) {
		const prependData = existingLoadOption.prependData;
		const nextId = item[field.valueField];
		if (!prependData.some((candidate) => candidate[field.valueField] === nextId)) prependData.unshift(item);
		return;
	}
	field.loadOptions = [{ prependData: [item] }, ...field.loadOptions];
}
/**
* Builds an empty row from field definitions, applying default values.
*/
function buildEmptyRow(fields) {
	const row = {};
	fields.forEach((field) => {
		if (field.defaultValue !== void 0) row[field.name] = field.defaultValue instanceof Function ? field.defaultValue() : field.defaultValue;
		else row[field.name] = void 0;
	});
	return row;
}
/**
* Normalizes raw API data into the shape expected by the active form editors.
*
* - FILE fields are stripped (uploaded separately)
* - PASSWORD fields are blanked
* - DATE fields are parsed into JS Date objects
* - ENTITY fields are normalized to their scalar key for select-style editors
*/
function normalizeFormData(data, fields) {
	const row = { ...data };
	fields.forEach((field) => {
		if (field.type === "file") delete row[field.name];
		if (field.type === "password") row[field.name] = "";
		if (field.type === "date") {
			const rawValue = row[field.name];
			const type = typeof rawValue;
			if (type === "string") row[field.name] = DateUtils.dateParser(rawValue);
			else if (type === "object") row[field.name] = new Date(rawValue);
			else row[field.name] = null;
		}
		if (field.type === "entity") normalizeEntityField(row, field);
	});
	return row;
}
function normalizeEntityField(row, field) {
	const rawValue = row[field.name];
	const type = typeof rawValue;
	if (type === "string") {
		const value = field.valueField === "_iri" ? rawValue : rawValue.split("/").pop();
		row[field.name] = value;
	}
	if (type === "object" && row[field.name] !== null) {
		const entityValue = { ...row[field.name] };
		if (field.valueField === "_iri" && typeof entityValue["_iri"] !== "string" && typeof entityValue["@id"] !== "string") {
			const synthesizedIri = synthesizeEntityIri(field, entityValue);
			if (synthesizedIri) entityValue["_iri"] = synthesizedIri;
		}
		upsertPrependData(field, entityValue);
		const atId = entityValue["@id"];
		if (typeof atId === "string") {
			const value = field.valueField === "_iri" ? atId : atId.split("/").pop() ?? atId;
			row[field.name] = value;
		} else {
			const directValue = entityValue[field.valueField];
			if (directValue !== void 0 && directValue !== null) row[field.name] = directValue;
		}
	}
	if (row[field.name] === null || row[field.name] === void 0) row[field.name] = field.loadOptions[0]?.prependData[0] ?? null;
}
//#endregion
//#region assets/core/form/FormLayoutModel.ts
function buildFormLayoutModel(fields, formLayout, overflowLabel) {
	const pickFields = (names) => fields.filter((field) => names.includes(field.name));
	const remainingFields = (assignedNames) => fields.filter((field) => !assignedNames.includes(field.name));
	if (formLayout?.type === "tabs") {
		const overflow = remainingFields(formLayout.tabs.flatMap((tab) => tab.fields));
		return {
			type: "tabs",
			tabs: formLayout.tabs.map((tab) => ({
				label: tab.label,
				icon: tab.icon,
				fields: pickFields(tab.fields)
			})),
			overflow: overflow.length > 0 ? {
				label: overflowLabel,
				fields: overflow
			} : void 0
		};
	}
	if (formLayout?.type === "sections") {
		const overflow = remainingFields(formLayout.sections.flatMap((section) => section.fields));
		return {
			type: "sections",
			sections: formLayout.sections.map((section) => ({
				label: section.label,
				collapsible: section.collapsible,
				defaultCollapsed: section.defaultCollapsed,
				fields: pickFields(section.fields)
			})),
			overflow: overflow.length > 0 ? {
				label: overflowLabel,
				fields: overflow
			} : void 0
		};
	}
	return {
		type: "plain",
		fields
	};
}
//#endregion
//#region assets/core/form/serializeFormData.ts
function getIRI(url, value) {
	if (value.startsWith("/")) return value;
	return `${url}/${value}`;
}
/**
* Pure serialization of form data before HTTP submission.
*
* Transforms raw form field values into the shape expected by the API:
* - Applies uploaded file IRIs
* - Evaluates computed fields
* - Resolves ENTITY fields to Hydra IRIs
* - Formats DATE fields as UTC ISO-8601 (from Lima timezone)
* - Coerces numeric fields
* - Strips NONE/FILE fields as appropriate
*
* This function is extracted from useFormSubmit for testability.
*/
function serializeFormFields(rawData, fields, ctx) {
	const formData = { ...rawData };
	ctx.uploadedFiles.forEach((file) => {
		formData[file.name] = file.iri;
	});
	fields.forEach((field) => {
		if (!field.computed) return;
		const computedValue = field.computed(formData);
		if (computedValue !== void 0) formData[field.name] = computedValue;
	});
	fields.forEach((field) => {
		if (field.type === "entity" || field.type === "tags") {
			serializeEntityField(formData, field);
			return;
		}
		if (field.type === "file") {
			serializeFileField(formData, field, ctx);
			return;
		}
		if (field.type === "none") {
			if (!field.isIdentity) delete formData[field.name];
			return;
		}
		if (field.type === "date") {
			serializeDateField(formData, field);
			return;
		}
		if (field.type === "text" && field.valueType === "number") formData[field.name] = Number(formData[field.name]);
		if (field.type === "number" || field.type === "currency") serializeNumericField(formData, field);
	});
	return formData;
}
function serializeEntityField(formData, field) {
	const val = formData[field.name];
	const type = typeof val;
	if (val === null || val === void 0 || val === "" || val === -999) {
		delete formData[field.name];
		return;
	}
	if (type === "object" && val !== null && !field.multiple) {
		const entityValue = val;
		const atId = entityValue["@id"];
		if (typeof atId === "string") formData[field.name] = getIRI(field.url ?? "", atId);
		else {
			const idValue = entityValue["id"];
			const resolvedId = idValue !== void 0 && idValue !== null ? String(idValue) : String(entityValue[field.valueField]);
			formData[field.name] = getIRI(field.url ?? "", resolvedId);
		}
	}
	if (type === "string" || type === "number") formData[field.name] = getIRI(field.url ?? "", val.toString());
	if (field.multiple) if (Array.isArray(val)) formData[field.name] = val.map((item) => {
		if (typeof item === "object") {
			const record = item;
			const atId = record["@id"];
			if (typeof atId === "string") return getIRI(field.url ?? "", atId);
			const idValue = record["id"];
			const resolvedId = idValue !== void 0 && idValue !== null ? String(idValue) : String(record[field.valueField]);
			return getIRI(field.url ?? "", resolvedId);
		}
		return getIRI(field.url ?? "", String(item));
	});
	else formData[field.name] = [getIRI(field.url ?? "", val.toString())];
}
function serializeFileField(formData, field, ctx) {
	if (ctx.format === "multipart" && ctx.getFieldValue) {
		const files = ctx.getFieldValue(field.name);
		if (files && files.length > 0) formData[field.name] = files[0];
		else delete formData[field.name];
		return;
	}
	if (Array.isArray(formData[field.name])) delete formData[field.name];
}
function serializeDateField(formData, field) {
	const val = formData[field.name];
	if (val === null || val === void 0) {
		delete formData[field.name];
		return;
	}
	const date = new Date(val);
	formData[field.name] = luxon.DateTime.fromJSDate(date, { zone: getCoreTimezone() }).toUTC().toISO();
}
function serializeNumericField(formData, field) {
	if (field.isIdentity) return;
	if (field.sendAsString) {
		const val = formData[field.name];
		formData[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
	} else formData[field.name] = Number(formData[field.name]);
}
/**
* Serializes detail grid rows (entity IRIs, numeric coercion, identity cleanup).
* Pure function — operates on an array of row records.
*/
function serializeDetailRows(rows, detailFields, detailIdField, isEditMode) {
	const details = JSON.parse(JSON.stringify(rows));
	detailFields.forEach((field) => {
		if (field.type === "entity") details.forEach((detail) => {
			const currentValue = detail[field.name];
			if (currentValue === null || currentValue === void 0 || currentValue === "" || currentValue === -999) {
				delete detail[field.name];
				return;
			}
			if (typeof currentValue === "object") {
				const entityValue = currentValue;
				const atId = entityValue["@id"];
				if (typeof atId === "string") detail[field.name] = getIRI(field.url ?? "", atId);
				else {
					const idValue = entityValue["id"];
					const resolvedId = idValue !== void 0 && idValue !== null ? String(idValue) : String(entityValue[field.valueField]);
					detail[field.name] = getIRI(field.url ?? "", resolvedId);
				}
				return;
			}
			if (typeof currentValue === "string" || typeof currentValue === "number") detail[field.name] = getIRI(field.url ?? "", String(currentValue));
		});
		if (field.type === "number" || field.type === "currency") details.forEach((detail) => {
			if (field.sendAsString) {
				const val = detail[field.name];
				detail[field.name] = val != null ? Number(val).toFixed(field.precision || 2) : null;
			} else detail[field.name] = Number(detail[field.name]);
		});
	});
	details.forEach((detail) => {
		if (!isEditMode) delete detail[detailIdField];
		else if (typeof detail[detailIdField] === "string") delete detail[detailIdField];
	});
	return details;
}
//#endregion
//#region assets/core/form/useFormSubmit.ts
/**
* Returns helpers for form serialization and HTTP submit/delete operations.
* Delegates pure serialization to `serializeFormFields` / `serializeDetailRows`.
*/
const useFormSubmit = (options, accessors, emit, validateFn) => {
	const { t } = useCoreTranslation();
	const { confirm } = useCoreRuntime();
	const getIdField = () => options.fields.find((f) => f.isIdentity)?.name ?? "";
	const getDetailIdField = () => options.detailFields?.find((f) => f.isIdentity)?.name ?? "";
	/** Build multipart FormData from a plain object */
	const buildMultipartFormData = (data) => {
		const form = new FormData();
		for (const key in data) {
			const value = data[key];
			if (value != null) form.append(key, value instanceof Blob ? value : String(value));
		}
		return form;
	};
	/** Serialize main form fields for submission */
	const serializeFields = (rawData) => {
		return serializeFormFields(rawData, options.fields, {
			uploadedFiles: accessors.getUploadedFiles(),
			getFieldValue: accessors.getFieldValue,
			format: options.format
		});
	};
	/** Append serialized detail grid rows to formData */
	const appendDetailRows = (formData) => {
		if (!options.detailFields) return;
		formData[options.detailPropertyName] = serializeDetailRows(accessors.getDetailRows(), options.detailFields, getDetailIdField(), accessors.isEditMode());
	};
	/** Handle the SAVE event: validate → serialize → POST or PATCH */
	const handleSave = () => {
		if (!validateFn()) return;
		const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
		if (loadingEvent) emit(loadingEvent, true);
		options.onLoadingChange?.(true);
		const formData = serializeFields(accessors.getFormData());
		appendDetailRows(formData);
		const id = formData[getIdField()];
		let payload = formData;
		if (options.format === "multipart") payload = buildMultipartFormData(formData);
		const finalize = () => {
			if (loadingEvent) emit(loadingEvent, false);
			options.onLoadingChange?.(false);
		};
		(accessors.isEditMode() ? options.httpClient.patch(`${options.url}/${id}`, payload) : options.httpClient.post(options.url, payload)).then((response) => {
			if (successEvent) emit(successEvent, response);
			options.onSaveSuccess?.(response);
		}).catch((err) => {
			if (typeof err === "object" && err !== null && "status" in err && err.status === 422 && "data" in err && typeof err.data === "object" && err.data !== null && "violations" in err.data) emit(FORM_ERRORS_EVENT, err.data.violations);
			options.onSaveError?.(err);
		}).finally(finalize);
	};
	/** Handle the DELETE event: confirm dialog → DELETE HTTP */
	const handleDelete = (payload) => {
		const { LOADING: loadingEvent, SUCCESS: successEvent } = options.events ?? {};
		if (!confirm(`${t("dialog.confirmDeleteTitle")}\n\n${t("dialog.confirmDelete")}`)) return;
		if (loadingEvent) emit(loadingEvent, true);
		options.onLoadingChange?.(true);
		const finalize = () => {
			if (loadingEvent) emit(loadingEvent, false);
			options.onLoadingChange?.(false);
		};
		options.httpClient.delete(`${options.url}/${payload.row[getIdField()]}`).then((response) => {
			if (successEvent) emit(successEvent, response);
			options.onDeleteSuccess?.(response);
		}).catch((err) => {
			options.onDeleteError?.(err);
		}).finally(finalize);
	};
	return {
		handleSave,
		handleDelete,
		serializeFields,
		appendDetailRows
	};
};
//#endregion
//#region assets/core/form/FormDetailValidation.ts
function validateDetailRows(detailRows, detailFields) {
	return detailRows.every((row) => detailFields.every((field) => {
		if (field.isIdentity || field.hidden || !field.visible) return true;
		const value = row[field.name];
		if (field.required && (value === null || value === void 0 || value === "")) return false;
		if (field.type === "number" || field.type === "currency") {
			const numericValue = Number(value ?? 0);
			if (field.required && Number.isNaN(numericValue)) return false;
			const rangeValidators = field.validators.filter((validator) => validator.type === "range");
			for (const validator of rangeValidators) {
				const { min, max } = validator.options;
				if (min !== void 0 && numericValue < min) return false;
				if (max !== void 0 && numericValue > max) return false;
			}
		}
		return true;
	}));
}
//#endregion
//#region assets/core/form/useFormValidation.ts
function useFormValidation({ accessors, detailFields, detailNoDataText, requiredDetail, validationErrorText }) {
	const { notify } = useCoreRuntime();
	return () => {
		const formValid = accessors.validateForm();
		let gridValid = !accessors.hasPendingDetailEdits();
		let detailRequiredMissing = false;
		let detailRowsValid = true;
		if (requiredDetail) {
			gridValid = accessors.getDetailRowCount() > 0;
			if (!gridValid) detailRequiredMissing = true;
		}
		if (detailFields) detailRowsValid = validateDetailRows(accessors.getDetailRows(), detailFields);
		if (!formValid || !gridValid || !detailRowsValid) notify(detailRequiredMissing && formValid ? detailNoDataText : validationErrorText, detailRequiredMissing && formValid ? "warning" : "error");
		return formValid && gridValid && detailRowsValid;
	};
}
//#endregion
//#region assets/core/form/NativeFormView.tsx
function createRemoteSource(field, httpClient) {
	return new ApiPlatformRemoteDataSource({
		url: field.url ?? "",
		idField: field.valueField,
		byKeyUrl: field.byKeyUrl,
		defaultFilterRules: fieldFilters(field),
		options: field.loadOptions,
		iriMode: field.valueField === "_iri",
		httpClient
	});
}
function fieldKeyValue(field, item) {
	if (field.valueField === "_iri") return item["_iri"] ?? item["@id"] ?? item["id"];
	return item[field.valueField] ?? item["value"] ?? item["id"] ?? item["@id"];
}
function fieldTextValue(field, item) {
	const value = item[field.textField] ?? item["text"] ?? item["name"] ?? item["businessName"];
	return value == null ? "" : String(value);
}
function fieldFilters(field) {
	if (field.filters.length === 0) return void 0;
	return field.filters.map((filter) => [
		filter.field,
		filter.operator,
		filter.value
	]);
}
function inputValue(value) {
	if (value instanceof Date) return value.toISOString().slice(0, 10);
	if (typeof value === "string" || typeof value === "number") return value;
	return value == null ? "" : String(value);
}
function isEmptyValue(value) {
	return value === null || value === void 0 || value === "" || Array.isArray(value) && value.length === 0;
}
function fieldSearchExpr(field) {
	if (field.searchExpr && field.searchExpr.length > 0) return field.searchExpr;
	return [field.textField].filter(Boolean);
}
function colSpan(field) {
	return { "--native-form-col": String(field.col ?? 12) };
}
function NativeEntitySelect({ className, disabled, field, httpClient, id, onChange, options, readOnly, required, value }) {
	const [open, setOpen] = (0, react.useState)(false);
	const [draftQuery, setDraftQuery] = (0, react.useState)(null);
	const [searchedItems, setSearchedItems] = (0, react.useState)(null);
	const [loading, setLoading] = (0, react.useState)(false);
	const blurTimer = (0, react.useRef)(void 0);
	const normalizedValue = String(value && typeof value === "object" ? fieldKeyValue(field, value) : value ?? "");
	const items = searchedItems ?? options;
	const selectedItem = items.find((item) => String(fieldKeyValue(field, item) ?? "") === normalizedValue) ?? options.find((item) => String(fieldKeyValue(field, item) ?? "") === normalizedValue);
	const selectedText = selectedItem ? fieldTextValue(field, selectedItem) : "";
	const query = draftQuery ?? selectedText;
	(0, react.useEffect)(() => {
		if (!field.url || !field.searchEnabled || !open) return;
		const searchValue = query.trim();
		const timer = setTimeout(() => {
			setLoading(true);
			createRemoteSource(field, httpClient).load({
				take: 50,
				searchExpr: fieldSearchExpr(field),
				searchValue: searchValue === "" ? void 0 : searchValue
			}).then((result) => setSearchedItems(result.data)).catch(() => setSearchedItems(null)).finally(() => setLoading(false));
		}, 250);
		return () => clearTimeout(timer);
	}, [
		field,
		httpClient,
		open,
		options,
		query
	]);
	const selectItem = (item) => {
		onChange(fieldKeyValue(field, item) ?? null);
		setDraftQuery(fieldTextValue(field, item));
		setOpen(false);
	};
	const clearSelection = () => {
		onChange(null);
		setDraftQuery("");
		setOpen(true);
	};
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "native-form__lookup",
		children: [
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
				autoComplete: "off",
				className,
				disabled,
				id,
				readOnly,
				required,
				role: "combobox",
				"aria-autocomplete": "list",
				"aria-expanded": open,
				value: query,
				onBlur: () => {
					blurTimer.current = setTimeout(() => {
						setOpen(false);
						setDraftQuery(null);
						setSearchedItems(null);
					}, 120);
				},
				onChange: (event) => {
					setDraftQuery(event.currentTarget.value);
					setOpen(true);
					if (event.currentTarget.value === "") onChange(null);
				},
				onFocus: () => {
					if (blurTimer.current) clearTimeout(blurTimer.current);
					setOpen(true);
				}
			}),
			!disabled && !readOnly && normalizedValue !== "" && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
				type: "button",
				className: "native-form__lookup-clear",
				"aria-label": `Limpiar ${field.label}`,
				onMouseDown: (event) => event.preventDefault(),
				onClick: clearSelection,
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
					className: "ph ph-x",
					"aria-hidden": "true"
				})
			}),
			open && !disabled && !readOnly && /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
				className: "native-form__lookup-menu",
				role: "listbox",
				children: [
					loading && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
						className: "native-form__lookup-status",
						children: "Buscando..."
					}),
					!loading && items.length === 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
						className: "native-form__lookup-status",
						children: "Sin resultados"
					}),
					!loading && items.map((item) => {
						const itemValue = fieldKeyValue(field, item);
						const itemText = fieldTextValue(field, item);
						return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
							type: "button",
							role: "option",
							"aria-selected": String(itemValue ?? "") === normalizedValue,
							className: "native-form__lookup-option",
							onMouseDown: (event) => event.preventDefault(),
							onClick: () => selectItem(item),
							children: itemText
						}, String(itemValue));
					})
				]
			})
		]
	});
}
function validateField(field, value, formData) {
	if (field.required && isEmptyValue(value)) return `${field.label} es requerido`;
	for (const rule of field.validators ?? []) {
		if (rule.type === "required" && isEmptyValue(value)) return rule.options.message ?? `${field.label} es requerido`;
		if (rule.type === "email" && typeof value === "string" && value !== "" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return rule.options.message ?? "Correo inválido";
		if (rule.type === "numeric" && value !== "" && value != null && Number.isNaN(Number(value))) return rule.options.message ?? "Valor numérico inválido";
		if (rule.type === "pattern" && typeof value === "string" && !new RegExp(rule.options.pattern).test(value)) return rule.options.message ?? "Formato inválido";
		if (rule.type === "stringLength" && typeof value === "string") {
			if (rule.options.min !== void 0 && value.length < rule.options.min) return rule.options.message ?? "Texto demasiado corto";
			if (rule.options.max !== void 0 && value.length > rule.options.max) return rule.options.message ?? "Texto demasiado largo";
		}
		if (rule.type === "range" && value !== "" && value != null) {
			const numericValue = Number(value);
			if (rule.options.min !== void 0 && numericValue < rule.options.min) return rule.options.message ?? "Valor fuera de rango";
			if (rule.options.max !== void 0 && numericValue > rule.options.max) return rule.options.message ?? "Valor fuera de rango";
		}
		if (rule.type === "compare") {
			const target = rule.options.comparisonTarget();
			const comparison = rule.options.comparisonType ?? "==";
			if (!(comparison === "==" ? value === target : comparison === "!=" ? value !== target : comparison === ">" ? Number(value) > Number(target) : comparison === "<" ? Number(value) < Number(target) : comparison === ">=" ? Number(value) >= Number(target) : Number(value) <= Number(target))) return rule.options.message ?? "Valor inválido";
		}
		if (rule.type === "custom" && !rule.options.validationCallback({
			value,
			data: formData
		})) return rule.options.message ?? "Valor inválido";
	}
	return null;
}
function NativeDetailGrid({ allowAdding = true, allowDeleting = true, allowUpdating = true, detailFields, httpClient, onRowsChange, onRowsMutated, remoteOptions, rows }) {
	const visibleFields = detailFields.filter((field) => field.visible && !field.hidden);
	const updateCell = async (rowIndex, field, value) => {
		let nextRow = {
			...rows[rowIndex],
			[field.name]: value
		};
		if (field.onChange) {
			const draft = { ...rows[rowIndex] };
			await field.onChange.call({ defaultSetCellValue: (row, nextValue) => {
				row[field.name] = nextValue;
			} }, draft, value, rows[rowIndex]);
			nextRow = {
				...nextRow,
				...draft
			};
		}
		onRowsChange(rows.map((row, index) => index === rowIndex ? nextRow : row));
		onRowsMutated();
	};
	const addRow = () => {
		onRowsChange([...rows, buildEmptyRow(detailFields)]);
		onRowsMutated();
	};
	const removeRow = (rowIndex) => {
		onRowsChange(rows.filter((_, index) => index !== rowIndex));
		onRowsMutated();
	};
	const renderDetailControl = (row, rowIndex, field) => {
		const value = row[field.name];
		if (field.formatter && field.type === "none") return field.formatter({
			value,
			data: row,
			rowIndex,
			columnIndex: 0
		});
		if (field.type === "entity" || field.type === "select" || field.type === "enum") {
			const items = field.type === "entity" ? remoteOptions[field.name] ?? field.data ?? [] : field.data ?? [];
			if (field.type === "entity") return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(NativeEntitySelect, {
				className: "native-form__control",
				disabled: !allowUpdating || field.disabled,
				field,
				httpClient,
				options: items,
				value,
				onChange: (nextValue) => void updateCell(rowIndex, field, nextValue)
			});
			return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("select", {
				className: "native-form__control",
				value: String(value && typeof value === "object" ? fieldKeyValue(field, value) : value ?? ""),
				disabled: !allowUpdating || field.disabled,
				onChange: (event) => void updateCell(rowIndex, field, event.currentTarget.value === "" ? null : event.currentTarget.value),
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
					value: "",
					children: "Seleccione..."
				}), items.map((item) => {
					const itemValue = item["value"];
					const itemText = String(item["text"] ?? itemValue ?? "");
					return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
						value: String(itemValue ?? ""),
						children: itemText
					}, String(itemValue));
				})]
			});
		}
		return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
			className: "native-form__control",
			value: inputValue(value),
			readOnly: !allowUpdating || field.readonly,
			disabled: field.disabled,
			type: field.type === "number" || field.type === "currency" ? "number" : "text",
			onChange: (event) => void updateCell(rowIndex, field, event.target.value)
		});
	};
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "native-form__detail",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "native-form__detail-heading",
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("h3", {
				className: "native-form__detail-title",
				children: "Detalle"
			}), allowAdding && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
				type: "button",
				className: "app-icon-button",
				"aria-label": "Agregar detalle",
				onClick: addRow,
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
					className: "ph ph-plus",
					"aria-hidden": "true"
				})
			})]
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: "native-form__detail-table-wrap",
			children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("table", {
				className: "native-form__detail-table",
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("thead", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tr", { children: [visibleFields.map((field) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", { children: field.label }, field.name)), allowDeleting && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("th", {})] }) }), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tbody", { children: [rows.length === 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("tr", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", {
					colSpan: visibleFields.length + (allowDeleting ? 1 : 0),
					className: "native-form__detail-empty",
					children: "No items added"
				}) }), rows.map((row, rowIndex) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("tr", { children: [visibleFields.map((field) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", { children: renderDetailControl(row, rowIndex, field) }, field.name)), allowDeleting && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("td", { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
					type: "button",
					className: "app-icon-button",
					onClick: () => removeRow(rowIndex),
					children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: "ph ph-trash",
						"aria-hidden": "true"
					})
				}) })] }, rowIndex))] })]
			})
		})]
	});
}
const NativeFormView = (0, react.forwardRef)((options, ref) => {
	const { t } = useCoreTranslation();
	const { notify } = useCoreRuntime();
	const httpClient = useCoreHttpClient();
	const [on, emit] = useEvents();
	const isEdit = (0, react.useRef)(false);
	const uploadedFiles = (0, react.useRef)([]);
	const [formData, setFormData] = (0, react.useState)(() => buildEmptyRow(options.fields));
	const formDataRef = (0, react.useRef)(formData);
	const [detailRows, setDetailRows] = (0, react.useState)([]);
	const detailRowsRef = (0, react.useRef)(detailRows);
	const [fieldState, setFieldState] = (0, react.useState)({});
	const [errors, setErrors] = (0, react.useState)({});
	const [remoteOptions, setRemoteOptions] = (0, react.useState)({});
	const [activeTab, setActiveTab] = (0, react.useState)(0);
	const fields = options.fields;
	const onFieldDataChanged = options.onFieldDataChanged;
	const detailPropertyName = options.detailPropertyName ?? "items";
	const computedFieldNames = (0, react.useMemo)(() => fields.filter((field) => field.computed !== void 0).map((field) => field.name), [fields]);
	const formLayoutModel = (0, react.useMemo)(() => buildFormLayoutModel(fields, options.formLayout, t("form.groupOther")), [
		fields,
		options.formLayout,
		t
	]);
	const optionFields = (0, react.useMemo)(() => [...fields, ...options.detailFields ?? []], [fields, options.detailFields]);
	const setNextFormData = (0, react.useCallback)((nextData) => {
		formDataRef.current = nextData;
		setFormData(nextData);
		onFieldDataChanged?.(nextData);
	}, [onFieldDataChanged]);
	const setNextDetailRows = (0, react.useCallback)((nextRows) => {
		detailRowsRef.current = nextRows;
		setDetailRows(nextRows);
	}, []);
	const notifyDetailRowsMutated = (0, react.useCallback)(() => {
		emit(FORM_EVENTS.ROW_UPDATED, detailRowsRef.current);
	}, [emit]);
	const setFieldValue = (0, react.useCallback)((name, value) => {
		const field = fields.find((candidate) => candidate.name === name);
		const nextData = {
			...formDataRef.current,
			[name]: value
		};
		formDataRef.current = nextData;
		setFormData(nextData);
		setErrors((current) => ({
			...current,
			[name]: ""
		}));
		onFieldDataChanged?.(nextData);
		(field?.onChange)?.(value);
	}, [fields, onFieldDataChanged]);
	(0, react.useEffect)(() => {
		formDataRef.current = formData;
	}, [formData]);
	(0, react.useEffect)(() => {
		detailRowsRef.current = detailRows;
	}, [detailRows]);
	(0, react.useEffect)(() => {
		optionFields.forEach((field) => {
			if (field.type !== "entity" && field.type !== "tags") return;
			if (!field.url) {
				setRemoteOptions((current) => ({
					...current,
					[field.name]: field.data ?? []
				}));
				return;
			}
			createRemoteSource(field, httpClient).load({ take: 50 }).then((result) => {
				setRemoteOptions((current) => ({
					...current,
					[field.name]: result.data
				}));
				if (field.autoSelectIfSingle && result.data.length === 1 && isEmptyValue(formDataRef.current[field.name])) setFieldValue(field.name, fieldKeyValue(field, result.data[0]));
			}).catch(() => {
				setRemoteOptions((current) => ({
					...current,
					[field.name]: field.data ?? []
				}));
			});
		});
	}, [
		httpClient,
		optionFields,
		setFieldValue
	]);
	(0, react.useEffect)(() => {
		fields.forEach((field) => {
			const optionsForField = remoteOptions[field.name];
			if (!field.autoSelectIfSingle || optionsForField?.length !== 1 || !isEmptyValue(formDataRef.current[field.name])) return;
			setFieldValue(field.name, fieldKeyValue(field, optionsForField[0]));
		});
	}, [
		fields,
		formData,
		remoteOptions,
		setFieldValue
	]);
	const validate = useFormValidation({
		accessors: {
			getDetailRowCount: () => detailRowsRef.current.length,
			getDetailRows: () => detailRowsRef.current,
			hasPendingDetailEdits: () => false,
			validateForm: (0, react.useCallback)(() => {
				const nextErrors = {};
				options.fields.forEach((field) => {
					const state = fieldState[field.name];
					if (field.isIdentity || state?.validationEnabled === false || state?.hidden === true || field.hidden || field.visibleOnForm === false) return;
					const error = validateField(field, formDataRef.current[field.name], formDataRef.current);
					if (error) nextErrors[field.name] = error;
				});
				setErrors(nextErrors);
				return Object.keys(nextErrors).length === 0;
			}, [fieldState, options.fields])
		},
		detailFields: options.detailFields,
		detailNoDataText: options.requiredDetail ? t("form.detailRequired") : t("form.detailEmpty"),
		requiredDetail: options.requiredDetail,
		validationErrorText: t("form.validationError")
	});
	const submitAccessors = (0, react.useMemo)(() => ({
		getDetailRows: () => detailRowsRef.current,
		getFieldValue: (field) => formDataRef.current[field],
		getFormData: () => formDataRef.current,
		getUploadedFiles: () => uploadedFiles.current,
		isEditMode: () => isEdit.current
	}), []);
	const { handleSave, handleDelete } = useFormSubmit({
		url: options.url,
		detailUrl: options.detailUrl,
		fields: options.fields,
		detailFields: options.detailFields,
		detailPropertyName,
		format: options.format,
		events: options.events,
		httpClient,
		onSaveSuccess: options.onSaveSuccess,
		onSaveError: options.onSaveError,
		onDeleteSuccess: options.onDeleteSuccess,
		onDeleteError: options.onDeleteError,
		onLoadingChange: options.onLoadingChange
	}, submitAccessors, emit, validate);
	const applyAddPayload = (0, react.useCallback)((payload) => {
		uploadedFiles.current = [];
		setNextDetailRows([]);
		setNextFormData(payload ? normalizeFormData(payload, options.fields) : buildEmptyRow(options.fields));
		if (payload && Array.isArray(payload[detailPropertyName])) setNextDetailRows(payload[detailPropertyName]);
		isEdit.current = false;
	}, [
		detailPropertyName,
		options.fields,
		setNextDetailRows,
		setNextFormData
	]);
	(0, react.useEffect)(() => {
		on(FORM_ERRORS_EVENT, (formErrors) => {
			notify(t("form.validationError"), "error");
			setErrors((current) => {
				const next = { ...current };
				formErrors.forEach((error) => {
					next[error.propertyPath] = error.message;
				});
				return next;
			});
		});
		if (options.events?.ADD) on(options.events.ADD, (payload) => applyAddPayload(payload));
		if (options.events?.EDIT) on(options.events.EDIT, (payload) => {
			const row = normalizeFormData(payload.row, options.fields);
			uploadedFiles.current = [];
			setNextFormData(row);
			isEdit.current = true;
			const detailId = row[options.fields.find((field) => field.isIdentity)?.name ?? ""];
			const detailUrl = typeof detailId === "string" || typeof detailId === "number" ? options.detailUrl?.replace("{id}", String(detailId)) : void 0;
			if (!detailUrl) return;
			emit(FORM_EVENTS.LOADING, true);
			httpClient.get(detailUrl).then((response) => setNextDetailRows(response.data)).finally(() => emit(FORM_EVENTS.LOADING, false));
		});
		if (options.events?.DELETE) on(options.events.DELETE, (payload) => handleDelete(payload));
		if (options.events?.SAVE) on(options.events.SAVE, () => handleSave());
	}, []);
	(0, react.useEffect)(() => {
		if (computedFieldNames.length === 0 || !options.computedValues) return;
		let changed = false;
		const nextData = { ...formDataRef.current };
		computedFieldNames.forEach((name) => {
			if (Object.prototype.hasOwnProperty.call(options.computedValues, name) && nextData[name] !== options.computedValues?.[name]) {
				nextData[name] = options.computedValues?.[name];
				changed = true;
			}
		});
		if (changed) setNextFormData(nextData);
	}, [
		computedFieldNames,
		options.computedValues,
		setNextFormData
	]);
	(0, react.useImperativeHandle)(ref, () => ({
		getFormData: () => formDataRef.current,
		getFieldValue: (name) => formDataRef.current[name],
		setFieldValue,
		getDetailData: () => detailRowsRef.current,
		setDetailData: setNextDetailRows,
		saveDetailData: () => void 0,
		reloadDetailData: () => void 0,
		setValues: (data) => setNextFormData(normalizeFormData(data, options.fields)),
		setReadonly: (field, value) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				readonly: value
			}
		})),
		setDisabled: (field, value) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				disabled: value
			}
		})),
		enableValidation: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				validationEnabled: true
			}
		})),
		disableValidation: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				validationEnabled: false
			}
		})),
		setError: (field, message) => setErrors((current) => ({
			...current,
			[field]: message
		})),
		showField: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				hidden: false
			}
		})),
		hideField: (field) => setFieldState((current) => ({
			...current,
			[field]: {
				...current[field],
				hidden: true
			}
		})),
		validate,
		setIsEdit: (value) => {
			isEdit.current = value;
		}
	}), [
		options.fields,
		setFieldValue,
		setNextDetailRows,
		setNextFormData,
		validate
	]);
	const renderField = (field) => {
		const state = fieldState[field.name] ?? {};
		if (field.isIdentity || !field.visibleOnForm || field.hidden || state.hidden) return null;
		const value = formData[field.name];
		const readOnly = state.readonly ?? field.readonly ?? options.editable === false;
		const disabled = state.disabled ?? field.disabled;
		const error = errors[field.name];
		const errorClass = error ? " is-error" : "";
		const commonProps = {
			className: `native-form__control${field.onClick ? " native-form__control--action" : ""}${errorClass}`,
			disabled,
			id: `native-form-${field.name}`,
			name: field.name,
			onClick: field.onClick,
			readOnly,
			required: field.required
		};
		let control;
		if (field.contentRender && typeof field.contentRender === "function") control = field.contentRender({
			data: formData,
			value
		});
		else if (field.type === "textarea" || field.type === "html") control = /* @__PURE__ */ (0, react_jsx_runtime.jsx)("textarea", {
			...commonProps,
			className: `native-form__control native-form__textarea${errorClass}`,
			value: inputValue(value),
			onChange: (event) => setFieldValue(field.name, event.target.value)
		});
		else if (field.type === "checkbox" || field.type === "switch") control = /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("label", {
			className: "native-form__checkbox",
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
				type: "checkbox",
				checked: Boolean(value),
				disabled: disabled || readOnly,
				onChange: (event) => setFieldValue(field.name, event.target.checked)
			}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", { children: field.label })]
		});
		else if (field.type === "radio") control = /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
			className: "native-form__radio-list",
			children: (field.data ?? []).map((item) => {
				const itemValue = item["value"];
				return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("label", {
					className: "native-form__radio-item",
					children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
						type: "radio",
						checked: value === itemValue,
						disabled: disabled || readOnly,
						onChange: () => setFieldValue(field.name, itemValue)
					}), String(item["text"] ?? itemValue)]
				}, String(itemValue));
			})
		});
		else if (field.type === "entity" && !field.multiple) {
			const items = remoteOptions[field.name] ?? field.data ?? [];
			control = /* @__PURE__ */ (0, react_jsx_runtime.jsx)(NativeEntitySelect, {
				className: `native-form__control${errorClass}`,
				disabled,
				field,
				httpClient,
				id: `native-form-${field.name}`,
				options: items,
				readOnly,
				required: field.required,
				value,
				onChange: (nextValue) => setFieldValue(field.name, nextValue)
			});
		} else if (field.type === "enum" || field.type === "select" || field.type === "tags") {
			const items = field.type === "tags" ? remoteOptions[field.name] ?? field.data ?? [] : field.data ?? [];
			control = /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("select", {
				...commonProps,
				multiple: field.multiple || field.type === "tags",
				value: field.multiple && Array.isArray(value) ? value.map(String) : String(value ?? ""),
				onChange: (event) => {
					const nextValue = event.currentTarget.multiple ? Array.from(event.currentTarget.selectedOptions).map((option) => option.value) : event.currentTarget.value;
					setFieldValue(field.name, nextValue === "" ? null : nextValue);
				},
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
					value: "",
					children: "Seleccione..."
				}), items.map((item) => {
					const itemValue = field.type === "tags" ? fieldKeyValue(field, item) : item["value"];
					const itemText = field.type === "tags" ? fieldTextValue(field, item) : String(item["text"] ?? itemValue ?? "");
					return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
						value: String(itemValue ?? ""),
						children: itemText
					}, String(itemValue));
				})]
			});
		} else if (field.type === "file") control = /* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
			...commonProps,
			type: "file",
			accept: field.accept ?? void 0,
			readOnly: void 0,
			onChange: (event) => {
				setFieldValue(field.name, event.target.files ?? null);
			}
		});
		else {
			const type = field.type === "number" || field.type === "currency" ? "number" : field.type === "date" ? "date" : field.type === "datetime" ? "datetime-local" : field.type === "password" ? "password" : "text";
			control = /* @__PURE__ */ (0, react_jsx_runtime.jsx)("input", {
				...commonProps,
				type,
				value: inputValue(value),
				onChange: (event) => setFieldValue(field.name, event.target.value)
			});
		}
		return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "native-form__field",
			style: colSpan(field),
			children: [
				field.type !== "checkbox" && field.type !== "switch" && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("label", {
					className: `native-form__label${field.required ? " native-form__label--required" : ""}`,
					htmlFor: `native-form-${field.name}`,
					children: field.label
				}),
				control,
				field.helpText && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
					className: "native-form__label",
					children: field.helpText
				}),
				error && /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("span", {
					className: "native-form__error",
					children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: "ph ph-warning-circle",
						"aria-hidden": "true"
					}), error]
				})
			]
		}, field.name);
	};
	const renderFields = (fields) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: "native-form__grid",
		children: fields.map(renderField)
	});
	const renderGroup = (group) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("section", {
		className: "native-form__section",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("h3", {
			className: "native-form__section-title",
			children: group.label
		}), renderFields(group.fields)]
	}, group.label);
	const renderLayout = (layout) => {
		if (layout.type === "tabs") {
			const groups = [...layout.tabs, ...layout.overflow ? [layout.overflow] : []];
			const current = groups[activeTab] ?? groups[0];
			return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
				className: "native-form__tabs",
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
					className: "native-form__tabs-nav",
					children: groups.map((group, index) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
						type: "button",
						className: `native-form__tab-button${index === activeTab ? " native-form__tab-button--active" : ""}`,
						onClick: () => setActiveTab(index),
						children: group.label
					}, group.label))
				}), current && renderFields(current.fields)]
			});
		}
		if (layout.type === "sections") return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)(react_jsx_runtime.Fragment, { children: [layout.sections.map(renderGroup), layout.overflow && renderGroup(layout.overflow)] });
		return renderFields(layout.fields);
	};
	const detailGrid = options.detailFields ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)(NativeDetailGrid, {
		allowAdding: options.allowAdding,
		allowDeleting: options.allowDeleting,
		allowUpdating: options.allowUpdating,
		detailFields: options.detailFields,
		httpClient,
		onRowsMutated: notifyDetailRowsMutated,
		remoteOptions,
		rows: detailRows,
		onRowsChange: setNextDetailRows
	}) : null;
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
		className: `native-form${options.detailFields ? " native-form--with-detail" : ""} ${options.className ?? ""}`,
		children: options.detailFields ? /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			className: "native-form__sales-layout",
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "native-form__master-panel",
				children: renderLayout(formLayoutModel)
			}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "native-form__detail-panel",
				children: detailGrid
			})]
		}) : renderLayout(formLayoutModel)
	});
});
NativeFormView.displayName = "NativeFormView";
//#endregion
//#region assets/core/crud/AuditTrailPanel.tsx
const ACTION_COLORS = {
	create: "#2e7d32",
	update: "#1565c0",
	delete: "#c62828"
};
function DefaultEntryRenderer({ entry }) {
	const { t } = useCoreTranslation();
	const date = new Date(entry.timestamp);
	const formatted = Number.isNaN(date.getTime()) ? entry.timestamp : date.toLocaleString();
	const actionLabels = {
		create: t("auditTrail.action.create"),
		update: t("auditTrail.action.update"),
		delete: t("auditTrail.action.delete")
	};
	const changeKeys = Object.keys(entry.changes);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("li", {
		style: {
			borderBottom: "1px solid #e0e0e0",
			padding: "10px 0",
			listStyle: "none"
		},
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			style: {
				display: "flex",
				alignItems: "center",
				gap: 8,
				marginBottom: 4
			},
			children: [
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
					style: {
						fontSize: 12,
						color: "#757575"
					},
					children: formatted
				}),
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
					style: {
						fontSize: 12,
						color: "#424242"
					},
					children: entry.user
				}),
				/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
					style: {
						fontSize: 11,
						fontWeight: 600,
						padding: "1px 6px",
						borderRadius: 4,
						backgroundColor: ACTION_COLORS[entry.action],
						color: "#fff"
					},
					children: actionLabels[entry.action]
				})
			]
		}), changeKeys.length > 0 && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("ul", {
			style: {
				margin: "4px 0 0 0",
				padding: "0 0 0 16px"
			},
			children: changeKeys.map((field) => {
				const { before, after } = entry.changes[field];
				return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("li", {
					style: {
						fontSize: 12,
						color: "#616161"
					},
					children: [
						/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("strong", { children: [field, ":"] }),
						" ",
						/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
							style: { color: "#c62828" },
							children: String(before ?? "—")
						}),
						" → ",
						/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
							style: { color: "#2e7d32" },
							children: String(after ?? "—")
						})
					]
				}, field);
			})
		})]
	});
}
function AuditTrailPanel({ url, renderEntry, visible, onClose }) {
	const { t } = useCoreTranslation();
	const httpClient = useCoreHttpClient();
	const [fetchState, setFetchState] = (0, react.useState)({ status: "idle" });
	(0, react.useEffect)(() => {
		if (!visible || url === null) {
			setFetchState({ status: "idle" });
			return;
		}
		let cancelled = false;
		setFetchState({ status: "loading" });
		httpClient.get(url).then((response) => {
			if (!cancelled) setFetchState({
				status: "success",
				entries: response.data
			});
		}).catch(() => {
			if (!cancelled) setFetchState({ status: "error" });
		});
		return () => {
			cancelled = true;
		};
	}, [
		httpClient,
		url,
		visible
	]);
	if (!visible) return null;
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("aside", {
		className: "audit-trail-panel",
		style: {
			border: "1px solid #e0e0e0",
			borderRadius: 4,
			padding: "12px 16px",
			backgroundColor: "#fafafa",
			minWidth: 280
		},
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
			style: {
				display: "flex",
				alignItems: "center",
				justifyContent: "space-between",
				marginBottom: 12
			},
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("strong", {
				style: { fontSize: 14 },
				children: t("auditTrail.title")
			}), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
				type: "button",
				onClick: onClose,
				"aria-label": t("auditTrail.closeButton"),
				style: {
					background: "none",
					border: "none",
					cursor: "pointer",
					fontSize: 18,
					lineHeight: 1,
					padding: "0 4px",
					color: "#616161"
				},
				children: "×"
			})]
		}), url === null ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
			style: {
				fontSize: 13,
				color: "#757575",
				margin: 0
			},
			children: t("auditTrail.selectRecord")
		}) : fetchState.status === "loading" ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
			style: {
				fontSize: 13,
				color: "#757575",
				margin: 0
			},
			children: t("auditTrail.loading")
		}) : fetchState.status === "error" ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
			style: {
				fontSize: 13,
				color: "#c62828",
				margin: 0
			},
			children: t("auditTrail.error")
		}) : fetchState.status === "success" ? fetchState.entries.length === 0 ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
			style: {
				fontSize: 13,
				color: "#757575",
				margin: 0
			},
			children: t("auditTrail.empty")
		}) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)("ul", {
			style: {
				margin: 0,
				padding: 0
			},
			children: fetchState.entries.map((entry) => renderEntry ? /* @__PURE__ */ (0, react_jsx_runtime.jsx)(react.default.Fragment, { children: renderEntry(entry) }, String(entry.id)) : /* @__PURE__ */ (0, react_jsx_runtime.jsx)(DefaultEntryRenderer, { entry }, String(entry.id)))
		}) : null]
	});
}
//#endregion
//#region assets/core/crud/dialogStore.ts
function initialDialogState() {
	return {
		isOpen: false,
		mode: "add",
		rowData: null
	};
}
function dialogReducer(state, action) {
	switch (action.type) {
		case "OPEN": return {
			...state,
			[action.resourceId]: {
				isOpen: true,
				mode: action.mode,
				rowData: action.rowData
			}
		};
		case "CLOSE": {
			const existing = state[action.resourceId] ?? initialDialogState();
			return {
				...state,
				[action.resourceId]: {
					...existing,
					isOpen: false
				}
			};
		}
		default: return state;
	}
}
//#endregion
//#region assets/core/crud/DialogStoreProvider.tsx
const DialogStoreContext = (0, react.createContext)(null);
function DialogStoreProvider({ children }) {
	const [state, dispatch] = (0, react.useReducer)(dialogReducer, {});
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(DialogStoreContext.Provider, {
		value: {
			state,
			dispatch
		},
		children
	});
}
function useDialogStoreContext() {
	return (0, react.useContext)(DialogStoreContext);
}
//#endregion
//#region assets/core/crud/useCrudDialogStore.ts
function useCrudDialogStore(resourceId) {
	const { state, dispatch } = useDialogStoreContext();
	const dialogState = state[resourceId] ?? initialDialogState();
	const openDialog = (0, react.useCallback)((mode, rowData = null) => {
		dispatch({
			type: "OPEN",
			resourceId,
			mode,
			rowData
		});
	}, [dispatch, resourceId]);
	const closeDialog = (0, react.useCallback)(() => {
		dispatch({
			type: "CLOSE",
			resourceId
		});
	}, [dispatch, resourceId]);
	return {
		...dialogState,
		openDialog,
		closeDialog
	};
}
//#endregion
//#region assets/core/crud/CrudPage.tsx
function inferFilterOperator(field) {
	if (field.selectedFilterOperation) return field.selectedFilterOperation;
	return field.valueType === "string" ? "contains" : "=";
}
function buildRoutingFilterRules(fields, initialFilters) {
	return Object.entries(initialFilters).flatMap(([name, value]) => {
		const field = fields.find((candidate) => candidate.name === name && candidate.filterable !== false);
		if (!field || value === "") return [];
		return [{
			field: name,
			operator: inferFilterOperator(field),
			value
		}];
	});
}
/**
* Public export — wraps CrudPageInner in its own DialogStoreProvider so that
* existing pages do not need to add a provider themselves.
*/
const CrudPage = ({ resource, onFormDataChange, initialRecordId, initialIsNew, computedValues, initialFilters, onFiltersChange }) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)(DialogStoreProvider, { children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CrudPageInner, {
	resource,
	onFormDataChange,
	initialRecordId,
	initialIsNew,
	computedValues,
	initialFilters,
	onFiltersChange
}) });
const CrudPageInner = ({ resource, onFormDataChange, initialRecordId = null, initialIsNew = false, computedValues = {}, initialFilters = {}, onFiltersChange }) => {
	const { t } = useCoreTranslation();
	const { events, resource: resolvedResource, fields, formRef, permissions, selectionState, presetState } = useCrudPage((0, react.useMemo)(() => resolveCrudResource(resource), [resource]));
	const datagridFields = (0, react.useMemo)(() => fields.filter((field) => field.isIdentity || field.visible !== false), [fields]);
	const routeAwareGridFields = (0, react.useMemo)(() => datagridFields.map((field) => {
		const routeFilterValue = initialFilters[field.name];
		if (routeFilterValue === void 0) {
			if (field.filterValue === void 0) return field;
			return {
				...field,
				filterValue: void 0
			};
		}
		return {
			...field,
			filterValue: routeFilterValue,
			selectedFilterOperation: inferFilterOperator(field)
		};
	}), [datagridFields, initialFilters]);
	const routeAwareFilters = (0, react.useMemo)(() => [...resolvedResource.filter ?? [], ...buildRoutingFilterRules(routeAwareGridFields, initialFilters)], [
		initialFilters,
		resolvedResource.filter,
		routeAwareGridFields
	]);
	const isInlineEditMode = resolvedResource.editMode === "row" || resolvedResource.editMode === "cell" || resolvedResource.editMode === "batch";
	const hasMultipleSelection = (resolvedResource.bulkActions?.length ?? 0) > 0;
	const hasPresets = (resolvedResource.columnPresets?.length ?? 0) > 0;
	const hasAuditTrail = resolvedResource.auditTrail?.enabled === true;
	const [selectedRowId, setSelectedRowId] = (0, react.useState)(null);
	const [auditOpen, setAuditOpen] = (0, react.useState)(false);
	const [confirmState, setConfirmState] = (0, react.useState)({
		open: false,
		action: null
	});
	const { isOpen: dialogIsOpen, mode: dialogMode, openDialog, closeDialog } = useCrudDialogStore(resolvedResource.id);
	const [on] = useEvents();
	const wireEventsRef = react.default.useRef(() => {
		if (events.ADD) on(events.ADD, (payload) => {
			openDialog("add", payload?.rowData ?? null);
		});
		if (events.EDIT) on(events.EDIT, (rowData) => {
			openDialog("edit", rowData ?? null);
		});
		if (events.SUCCESS) on(events.SUCCESS, () => {
			closeDialog();
		});
	});
	wireEventsRef.current = () => {
		if (events.ADD) on(events.ADD, (payload) => {
			openDialog("add", payload?.rowData ?? null);
		});
		if (events.EDIT) on(events.EDIT, (rowData) => {
			openDialog("edit", rowData ?? null);
		});
		if (events.SUCCESS) on(events.SUCCESS, () => {
			closeDialog();
		});
	};
	react.default.useEffect(() => {
		wireEventsRef.current();
	}, []);
	react.default.useEffect(() => {
		if (initialRecordId != null) {
			openDialog("edit", { id: initialRecordId });
			return;
		}
		if (initialIsNew) {
			openDialog("add", null);
			return;
		}
		closeDialog();
	}, [
		closeDialog,
		initialIsNew,
		initialRecordId,
		openDialog
	]);
	const onDetailRowsChangedRef = react.default.useRef(resolvedResource.onDetailRowsChanged);
	onDetailRowsChangedRef.current = resolvedResource.onDetailRowsChanged;
	react.default.useEffect(() => {
		if (!onDetailRowsChangedRef.current) return;
		const handler = () => onDetailRowsChangedRef.current?.(formRef);
		on(FORM_EVENTS.ROW_ADDED, handler);
		on(FORM_EVENTS.ROW_UPDATED, handler);
		on(FORM_EVENTS.ROW_REMOVED, handler);
	}, []);
	const dialogTitle = (0, react.useMemo)(() => {
		return {
			add: t("crudPage.dialogTitleAdd"),
			edit: t("crudPage.dialogTitleEdit"),
			view: t("crudPage.dialogTitleView")
		}[dialogMode] ?? "Nuevo";
	}, [dialogMode, t]);
	/** Bridge between the grid renderer's selection event and our selection state.
	* Also captures the selected row ID for the audit trail. */
	const handleSelectionChanged = (e) => {
		selectionState.onSelectionChanged(e.selectedRowsData ?? []);
		if (hasAuditTrail) {
			const first = (e.selectedRowsData ?? [])[0];
			if (first != null) setSelectedRowId(first["id"] ?? first["ID"] ?? null);
		}
	};
	const auditUrl = (0, react.useMemo)(() => {
		if (!resolvedResource.auditTrail?.enabled || selectedRowId == null) return null;
		const { apiUrl } = resolvedResource.auditTrail;
		return typeof apiUrl === "function" ? apiUrl(selectedRowId) : `${apiUrl}${selectedRowId}`;
	}, [resolvedResource.auditTrail, selectedRowId]);
	const executeBulkAction = (0, react.useCallback)(async (action) => {
		await action.onAction(selectionState.selectedIds);
		selectionState.clearSelection();
		if (events.SUCCESS) dispatch(events.SUCCESS);
	}, [selectionState, events]);
	const handleBulkAction = (0, react.useCallback)((action) => {
		if (action.confirmMessage) setConfirmState({
			open: true,
			action
		});
		else executeBulkAction(action);
	}, [executeBulkAction]);
	/** Preset selector rendered as a toolbar slot via `beforeToolbar`. */
	const renderPresetSelector = hasPresets ? () => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "column-preset-selector",
		style: {
			display: "inline-flex",
			alignItems: "center",
			gap: 8
		},
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("label", {
			htmlFor: `preset-${resolvedResource.id}`,
			style: { fontSize: 13 },
			children: t("crudPage.columnsLabel")
		}), /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("select", {
			id: `preset-${resolvedResource.id}`,
			"aria-label": "Seleccionar preset de columnas",
			value: presetState.activePreset ?? "",
			onChange: (e) => presetState.setPreset(e.target.value || null),
			style: { fontSize: 13 },
			children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
				value: "",
				children: t("crudPage.allColumns")
			}), resolvedResource.columnPresets.map((p) => /* @__PURE__ */ (0, react_jsx_runtime.jsx)("option", {
				value: p.key,
				children: p.label
			}, p.key))]
		})]
	}) : void 0;
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		id: "wrapper",
		className: auditOpen ? "wrapper--with-audit" : void 0,
		children: [
			(resolvedResource.bulkActions?.length ?? 0) > 0 && selectionState.hasSelection && /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
				className: "bulk-toolbar",
				role: "toolbar",
				"aria-label": "Acciones en lote",
				children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("span", {
					className: "bulk-toolbar__count",
					children: t("crudPage.selectedCount", { count: selectionState.selectedCount })
				}), resolvedResource.bulkActions.map((action) => /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("button", {
					type: "button",
					className: "app-button app-button--secondary app-button--sm",
					onClick: () => handleBulkAction(action),
					children: [action.icon && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("i", {
						className: action.icon,
						"aria-hidden": "true"
					}), action.label]
				}, action.key))]
			}),
			hasAuditTrail && /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", {
				className: "audit-trail-toolbar",
				style: { marginBottom: 8 },
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
					type: "button",
					onClick: () => setAuditOpen((prev) => !prev),
					"aria-pressed": auditOpen,
					"aria-label": "Mostrar historial de cambios",
					children: t("crudPage.auditTrailButton")
				})
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)(NativeDataGridView, {
				id: resolvedResource.id,
				title: resolvedResource.title,
				url: resolvedResource.apiUrl,
				fields: routeAwareGridFields,
				events,
				allowAdd: permissions.canAdd,
				allowEdit: permissions.canEdit,
				allowDelete: permissions.canDelete,
				allowExport: permissions.canExport,
				sort: resolvedResource.sort,
				filter: routeAwareFilters,
				paging: resolvedResource.paging,
				headerFilter: resolvedResource.headerFilter,
				mode: resolvedResource.mode,
				stateStoringEnabled: resolvedResource.stateStoring,
				toolbar: resolvedResource.toolbar,
				selectionMode: hasMultipleSelection ? "multiple" : "single",
				onSelectionChanged: handleSelectionChanged,
				editMode: resolvedResource.editMode,
				visibleColumns: presetState.visibleColumns,
				beforeToolbar: renderPresetSelector,
				detailUrl: resolvedResource.detailUrl,
				detailFields: resolvedResource.detailFields,
				onFilterChange: onFiltersChange
			}),
			!isInlineEditMode && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CrudDialogView, {
				events,
				width: resolvedResource.dialogWidth,
				height: resolvedResource.dialogHeight,
				title: dialogTitle,
				isOpen: dialogIsOpen,
				onClose: closeDialog,
				children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(NativeFormView, {
					ref: formRef,
					url: resolvedResource.apiUrl,
					fields,
					events,
					detailFields: resolvedResource.detailFields,
					detailUrl: resolvedResource.detailUrl,
					detailPropertyName: resolvedResource.detailPropertyName,
					format: resolvedResource.format,
					onSaveSuccess: resolvedResource.onSaveSuccess ? (response) => resolvedResource.onSaveSuccess?.(response) : void 0,
					onSaveError: resolvedResource.onSaveError,
					onDeleteSuccess: resolvedResource.onDeleteSuccess ? (response) => resolvedResource.onDeleteSuccess?.(response) : void 0,
					onDeleteError: resolvedResource.onDeleteError,
					formLayout: resolvedResource.formLayout,
					computedValues,
					onFieldDataChanged: onFormDataChange
				})
			}),
			hasAuditTrail && /* @__PURE__ */ (0, react_jsx_runtime.jsx)(AuditTrailPanel, {
				url: auditUrl,
				renderEntry: resolvedResource.auditTrail.renderEntry,
				visible: auditOpen,
				onClose: () => setAuditOpen(false)
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)(_nubit_ui.ConfirmDialog, {
				open: confirmState.open,
				title: "Confirmar acción",
				message: confirmState.action?.confirmMessage ?? "",
				variant: "warning",
				onConfirm: () => {
					const action = confirmState.action;
					setConfirmState({
						open: false,
						action: null
					});
					if (action) executeBulkAction(action);
				},
				onCancel: () => setConfirmState({
					open: false,
					action: null
				})
			})
		]
	});
};
//#endregion
//#region assets/core/crud/routing/useRouting.ts
/**
* Reads URL params and exposes helpers for SmartCrudPage to:
* - Open the edit dialog for the record whose id is in the URL
* - Open the add dialog when the URL contains the sentinel value "new"
* - Sync grid filters to/from URL query params
*
* If `routing` is undefined, returns neutral defaults so the feature
* is completely opt-in (no side-effects when routing is not configured).
*/
function useRouting(routing) {
	const params = (0, react_router_dom.useParams)();
	const [searchParams, setSearchParams] = (0, react_router_dom.useSearchParams)();
	return {
		initialRecordId: (() => {
			if (!routing?.routeParam) return null;
			const value = params[routing.routeParam];
			if (!value || value === "new") return null;
			return value;
		})(),
		initialIsNew: (() => {
			if (!routing?.routeParam) return false;
			return params[routing.routeParam] === "new";
		})(),
		initialFilters: (() => {
			if (!routing?.syncFiltersToUrl) return {};
			const filters = {};
			searchParams.forEach((value, key) => {
				filters[key] = value;
			});
			return filters;
		})(),
		syncFilters: (0, react.useCallback)((filters) => {
			if (!routing?.syncFiltersToUrl) return;
			const next = new URLSearchParams();
			Object.entries(filters).forEach(([key, value]) => {
				if (value) next.set(key, value);
			});
			setSearchParams(next, { replace: true });
		}, [routing?.syncFiltersToUrl, setSearchParams])
	};
}
//#endregion
//#region assets/core/crud/devHint.ts
/**
* Returns true when running on a local development hostname.
* Works regardless of Vite build mode (`import.meta.env.DEV`).
*/
function isDevEnvironment() {
	if (typeof window === "undefined") return false;
	const host = window.location.hostname;
	return host === "localhost" || host === "127.0.0.1" || host.endsWith(".localhost") || host.startsWith("192.168.");
}
/**
* Derives a one-line field descriptor string for use in the hint snippet.
* Produces e.g. `{ key: 'name', label: 'Name', type: 'text' }`.
*/
function fieldToHintLine(field) {
	const readOnlyPart = field.readonly ? ", readOnly: true" : "";
	return `    { key: '${field.name}', label: '${field.label}', type: '${field.type}'${readOnlyPart} },`;
}
/**
* Log a dev hint for auto-inferred fields the first time a resource URL is
* mounted with auto-discovery active.
*
* @param resourceUrl - The `apiUrl` passed to SmartCrudPage (normalized form).
* @param fields      - The auto-inferred `Field[]` resolved by useResourceSchema.
* @param operations  - The inferred HTTP methods exposed by the resource.
*/
function logDevHint(resourceUrl, fields, operations = []) {
	if (!isDevEnvironment()) return;
	const fieldLines = fields.filter((f) => !f.isIdentity).map(fieldToHintLine).join("\n");
	const operationsLine = operations.length > 0 ? `  operations: [${operations.join(", ")}],
` : "";
	console.info(`[SmartCRUD] Auto-inferred fields for "${resourceUrl}". To customise, use:\n\ndefineResource('${resourceUrl}', {\n` + operationsLine + `  fields: [\n${fieldLines}\n  ]\n})`);
}
//#endregion
//#region assets/core/mercure/MercureManager.ts
var MercureManager = class {
	hubUrl = null;
	topics = /* @__PURE__ */ new Map();
	hubUrlListeners = /* @__PURE__ */ new Set();
	/**
	* Update the hub URL (called by the Axios interceptor when the `Link` header is discovered,
	* or by MercureProvider). Notifies all registered listeners.
	*
	* Idempotent: if the URL is already set to the same value, listeners are NOT re-notified.
	*/
	setHubUrl(url) {
		if (this.hubUrl === url) return;
		this.disconnectAll();
		this.hubUrl = url;
		this.hubUrlListeners.forEach((cb) => cb(url));
	}
	getHubUrl() {
		return this.hubUrl;
	}
	/**
	* Register a callback that fires whenever the hub URL changes.
	*
	* Returns an unsubscribe function — call it in a `useEffect` cleanup to avoid memory leaks.
	*
	* @example
	* ```tsx
	* useEffect(() => {
	*   return MercureManager.onHubUrlChange(setHubUrl);
	* }, []);
	* ```
	*/
	onHubUrlChange(callback) {
		this.hubUrlListeners.add(callback);
		return () => {
			this.hubUrlListeners.delete(callback);
		};
	}
	/**
	* Subscribe to a Mercure topic.
	*
	* @param topic    Full topic URI or URI Template, e.g. `https://host/api/products/{id}`.
	* @param callback Called with the parsed JSON payload on each SSE message.
	*
	* If `hubUrl` is null (hub not configured), this is a no-op (graceful degradation).
	* If an EventSource for this topic already exists, it is reused (ref-counting).
	*/
	subscribe(topic, callback) {
		if (this.hubUrl === null) return;
		const existing = this.topics.get(topic);
		if (existing) {
			const listener = (e) => {
				try {
					callback(JSON.parse(e.data));
				} catch {
					callback(e.data);
				}
			};
			existing.eventSource.addEventListener("message", listener);
			existing.listeners.set(callback, listener);
			existing.count += 1;
			return;
		}
		const url = new URL(this.hubUrl);
		url.searchParams.append("topic", topic);
		const eventSource = new EventSource(url.toString(), { withCredentials: true });
		const listener = (e) => {
			try {
				callback(JSON.parse(e.data));
			} catch {
				callback(e.data);
			}
		};
		eventSource.addEventListener("message", listener);
		const listeners = /* @__PURE__ */ new Map();
		listeners.set(callback, listener);
		this.topics.set(topic, {
			eventSource,
			count: 1,
			listeners
		});
	}
	/**
	* Unsubscribe a specific callback from a topic.
	*
	* Decrements the ref count. When count reaches 0, the EventSource is closed
	* and removed from the internal map.
	*/
	unsubscribe(topic, callback) {
		const entry = this.topics.get(topic);
		if (!entry) return;
		const listener = entry.listeners.get(callback);
		if (listener) {
			entry.eventSource.removeEventListener("message", listener);
			entry.listeners.delete(callback);
		}
		entry.count -= 1;
		if (entry.count <= 0) {
			entry.eventSource.close();
			this.topics.delete(topic);
		}
	}
	/**
	* Close all active EventSource connections and clear the topic map.
	*
	* Called internally when the hub URL changes so stale connections
	* bound to the previous hub are not leaked.
	*/
	disconnectAll() {
		this.topics.forEach((entry) => {
			entry.eventSource.close();
		});
		this.topics.clear();
	}
};
/** Singleton instance — shared across the entire application. */
const mercureManager = new MercureManager();
//#endregion
//#region assets/core/mercure/MercureProvider.tsx
/**
* MercureProvider — React context provider for the Mercure hub URL.
*
* Wraps the application (or a subtree) and makes the hub URL available to all
* descendant hooks via `useMercureHub()`.
*
* Also keeps `MercureManager` in sync with the hub URL whenever it changes.
*
* ## Usage
*
* ```tsx
* // In EfactApp.tsx (or your app root):
* <MercureProvider hubUrl={discoveredHubUrl}>
*   <App />
* </MercureProvider>
* ```
*
* The `hubUrl` prop is typically discovered from the `Link` header of API responses
* via an Axios response interceptor (see AC-4 in SLICE-014).
*/
/** The hub URL, or null if Mercure is not configured / not yet discovered. */
const MercureContext = (0, react.createContext)(null);
/**
* Provides the Mercure hub URL to the React tree and keeps `MercureManager`
* in sync whenever the URL changes.
*/
function MercureProvider({ hubUrl, children }) {
	(0, react.useEffect)(() => {
		mercureManager.setHubUrl(hubUrl);
	}, [hubUrl]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(MercureContext.Provider, {
		value: hubUrl,
		children
	});
}
//#endregion
//#region assets/core/mercure/useMercureHub.ts
/**
* useMercureHub — Hook that returns the Mercure hub URL from context.
*
* Returns `null` if:
* - The component is rendered outside a `<MercureProvider>` (default context value).
* - The hub URL has not been discovered yet (header `Link` not received).
* - Mercure is not configured in the backend.
*
* This hook does NOT perform any fetch or side effect — it only reads from context.
* Hub discovery is handled by the Axios response interceptor registered in EfactApp.tsx.
*
* ## Usage
*
* ```tsx
* const hubUrl = useMercureHub();
* // hubUrl is string | null
* ```
*/
/**
* Returns the Mercure hub URL from the nearest `<MercureProvider>`,
* or `null` if the hub is not available.
*/
function useMercureHub() {
	return (0, react.useContext)(MercureContext);
}
//#endregion
//#region assets/core/mercure/useMercureSubscription.ts
/**
* useMercureSubscription — Hook that subscribes to a Mercure topic and calls
* a callback whenever an SSE message is received for that topic.
*
* ## Behaviour
* - If `hubUrl` is null (Mercure not configured) or `enabled` is false → no-op.
* - Subscribes to the wildcard topic `<origin>/<apiUrl>/{id}` (URI Template RFC 6570),
*   which captures any item-level event (create / update / delete) for the collection.
* - Cleanup: unsubscribes on unmount or when dependencies change (no memory leaks).
*
* ## Usage
*
* ```tsx
* useMercureSubscription(
*   resource.apiUrl,
*   () => { gridRef.current?.instance().refresh(); },
*   resource.mercure !== false,
* );
* ```
*
* @param apiUrl   The resource API URL (e.g. `'api/products'` or `'/api/products'`).
*                 Used to build the wildcard topic URI.
* @param onUpdate Callback invoked on every SSE message for the topic.
* @param enabled  When false, the subscription is skipped entirely. Defaults to true.
*/
function useMercureSubscription(apiUrl, onUpdate, enabled = true) {
	const hubUrl = useMercureHub();
	(0, react.useEffect)(() => {
		if (!hubUrl || !enabled || !apiUrl) return;
		const normalizedPath = apiUrl.replace(/^\//, "");
		const topic = `${window.location.origin}/${normalizedPath}/{id}`;
		const handler = (_data) => {
			onUpdate();
		};
		mercureManager.subscribe(topic, handler);
		return () => {
			mercureManager.unsubscribe(topic, handler);
		};
	}, [
		hubUrl,
		enabled,
		apiUrl
	]);
}
//#endregion
//#region assets/core/crud/SmartCrudRolesContext.tsx
const SmartCrudRolesContext = (0, react.createContext)([]);
function SmartCrudRolesProvider({ children, roles }) {
	const value = (0, react.useMemo)(() => roles ?? [], [roles]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(SmartCrudRolesContext.Provider, {
		value,
		children
	});
}
function useSmartCrudRoles() {
	return (0, react.useContext)(SmartCrudRolesContext);
}
//#endregion
//#region assets/core/crud/useSmartCrudOperation.ts
function areEqualValues(left, right) {
	if (Object.is(left, right)) return true;
	if (left instanceof Date && right instanceof Date) return left.getTime() === right.getTime();
	if (Array.isArray(left) && Array.isArray(right)) {
		if (left.length !== right.length) return false;
		return left.every((item, index) => areEqualValues(item, right[index]));
	}
	return false;
}
function areEqualFormData(left, right) {
	if (left === right) return true;
	if (left == null || right == null) return false;
	const leftKeys = Object.keys(left);
	const rightKeys = Object.keys(right);
	if (leftKeys.length !== rightKeys.length) return false;
	return leftKeys.every((key) => Object.prototype.hasOwnProperty.call(right, key) && areEqualValues(left[key], right[key]));
}
function stateFromRouting(routingState) {
	if (routingState.initialRecordId != null) return {
		activeOperation: "edit",
		formData: null
	};
	if (routingState.initialIsNew) return {
		activeOperation: "create",
		formData: {}
	};
	return {
		activeOperation: null,
		formData: null
	};
}
function crudReducer(state, action) {
	switch (action.type) {
		case "sync-routing": {
			const nextState = stateFromRouting(action.routingState);
			return state.activeOperation === nextState.activeOperation && areEqualFormData(state.formData, nextState.formData) ? state : nextState;
		}
		case "set-form-data": return areEqualFormData(state.formData, action.data) ? state : {
			...state,
			formData: action.data
		};
		case "create": return state.activeOperation === "create" && areEqualFormData(state.formData, {}) ? state : {
			activeOperation: "create",
			formData: {}
		};
		case "edit": return state.activeOperation === "edit" && state.formData === null ? state : {
			activeOperation: "edit",
			formData: null
		};
		case "reset": return state.activeOperation === null && state.formData === null ? state : {
			activeOperation: null,
			formData: null
		};
		default: return state;
	}
}
/**
* Owns the `activeOperation` + `formData` state pair and keeps them in sync
* with routing changes and the resource event bus (ADD / EDIT / CANCEL / SUCCESS).
*
* Extracted from SmartCrudPage to consolidate three separate useEffects that
* previously managed these coupled states independently.
*/
function useSmartCrudOperation(events, routingState) {
	const [on] = useEvents();
	const [{ activeOperation, formData }, dispatchState] = (0, react.useReducer)(crudReducer, routingState, stateFromRouting);
	const handleFormDataChange = (0, react.useCallback)((data) => {
		dispatchState({
			type: "set-form-data",
			data
		});
	}, []);
	(0, react.useEffect)(() => {
		dispatchState({
			type: "sync-routing",
			routingState: {
				initialRecordId: routingState.initialRecordId,
				initialIsNew: routingState.initialIsNew
			}
		});
	}, [routingState.initialIsNew, routingState.initialRecordId]);
	(0, react.useEffect)(() => {
		const subscriptions = [];
		if (events.ADD) subscriptions.push(on(events.ADD, () => {
			dispatchState({ type: "create" });
		}));
		if (events.EDIT) subscriptions.push(on(events.EDIT, () => {
			dispatchState({ type: "edit" });
		}));
		if (events.CANCEL) subscriptions.push(on(events.CANCEL, () => {
			dispatchState({ type: "reset" });
		}));
		if (events.SUCCESS) subscriptions.push(on(events.SUCCESS, () => {
			dispatchState({ type: "reset" });
		}));
		return () => {
			subscriptions.forEach((sub) => sub.unsubscribe());
		};
	}, [
		events.ADD,
		events.CANCEL,
		events.EDIT,
		events.SUCCESS,
		on
	]);
	return {
		activeOperation,
		formData,
		handleFormDataChange
	};
}
//#endregion
//#region assets/core/crud/rules/useFieldPermissions.ts
/**
* Filters and marks fields based on user roles.
* IMPORTANT: This is UI-only. Backend is the real authorization source.
*
* - If field.permissions.visible is set and userRoles has no intersection → filter out the field
* - If field.permissions.editable is set and userRoles has no intersection → mark field as disabled
*
* Identity fields (isIdentity === true) are always kept regardless of permissions
* so grids and forms can resolve row keys consistently.
*/
function useFieldPermissions(fields, userRoles) {
	return (0, react.useMemo)(() => {
		return fields.reduce((acc, field) => {
			if (field.isIdentity) {
				acc.push(field);
				return acc;
			}
			const perms = field.permissions;
			if (!perms) {
				acc.push(field);
				return acc;
			}
			if (perms.visible && !perms.visible.some((r) => userRoles.includes(r))) return acc;
			if (perms.editable && !perms.editable.some((r) => userRoles.includes(r))) {
				acc.push({
					...field,
					disabledWhen: () => true
				});
				return acc;
			}
			acc.push(field);
			return acc;
		}, []);
	}, [fields, userRoles]);
}
//#endregion
//#region assets/core/crud/rules/useConditionalRules.ts
/**
* Given the current Field definitions and live form data,
* returns the reactive state (visible/disabled/computedValue) for each field.
*
* Pure computation — re-runs whenever formData changes.
*
* When `formData` is `null`, SmartCrud has not hydrated the form snapshot yet
* (typically while opening an edit dialog), so rules are skipped and defaults
* are returned. Empty objects `{}` are treated as valid create-form state.
*/
function useConditionalRules(fields, formData) {
	return (0, react.useMemo)(() => {
		return fields.map((field) => {
			const baseVisible = field.visibleOnForm;
			if (formData == null) return {
				name: field.name,
				visible: baseVisible,
				disabled: false,
				computedValue: void 0
			};
			const visible = field.visibleWhen ? baseVisible && field.visibleWhen(formData) : baseVisible;
			const disabled = field.disabledWhen ? field.disabledWhen(formData) : false;
			const computedValue = field.computed ? field.computed(formData) : void 0;
			return {
				name: field.name,
				visible,
				disabled,
				computedValue
			};
		});
	}, [fields, formData]);
}
//#endregion
//#region assets/core/crud/rules/useDependsOn.ts
/**
* Compares two primitive-or-reference values.
* Uses strict equality for primitives (covers the vast majority of form values).
* Falls back to JSON.stringify only for objects/arrays (rare: e.g. multi-select).
*/
function valuesEqual(a, b) {
	if (a === b) return true;
	if (a == null || b == null) return false;
	if (typeof a !== "object" || typeof b !== "object") return false;
	return JSON.stringify(a) === JSON.stringify(b);
}
/**
* For each field that declares `dependsOn`, invalidate its entity query
* (keyed by the field's `url`) whenever one of the watched form values changes.
*
* Only fires when a dependency value actually changes (compared per-value).
* Skips the initial mount run to prevent spurious refetches when a dialog opens
* with already-cached data (BUG-009).
*
* Also skips evaluation while `formData` is `null`, which SmartCrudPage uses
* for edit dialogs that have not hydrated their initial snapshot yet.
*/
function useDependsOn(fields, formData) {
	const queryClient = (0, _tanstack_react_query.useQueryClient)();
	const dependentFields = fields.filter((field) => field.dependsOn?.length);
	const currentEntries = formData == null ? null : dependentFields.map((field) => ({
		url: field.url ?? null,
		values: (field.dependsOn ?? []).map((dep) => formData[dep])
	}));
	const isMounted = (0, react.useRef)(false);
	const previousEntriesRef = (0, react.useRef)(null);
	(0, react.useEffect)(() => {
		if (!isMounted.current) {
			isMounted.current = true;
			previousEntriesRef.current = currentEntries;
			return;
		}
		if (currentEntries == null) {
			previousEntriesRef.current = null;
			return;
		}
		const previousEntries = previousEntriesRef.current ?? [];
		previousEntriesRef.current = currentEntries;
		currentEntries.forEach((entry, index) => {
			const previousValues = previousEntries[index]?.values ?? [];
			if (!(entry.values.length !== previousValues.length || entry.values.some((val, i) => !valuesEqual(val, previousValues[i]))) || !entry.url) return;
			queryClient.invalidateQueries({ queryKey: [entry.url] });
		});
	}, [JSON.stringify(currentEntries?.map((e) => e.values) ?? null), queryClient]);
}
//#endregion
//#region assets/core/crud/fieldOperationSemantics.ts
var SmartCrudFieldOperationSemanticsError = class extends Error {
	issues;
	constructor(message, issues = []) {
		super(message);
		this.name = "SmartCrudFieldOperationSemanticsError";
		this.issues = issues;
	}
};
const OPERATIONS = ["create", "edit"];
const PROPERTIES = [
	"visible",
	"required",
	"readonly",
	"disabled"
];
function normalizeOnly(only) {
	if (!only) return;
	return Array.isArray(only) ? only : [only];
}
function createEmptySemantics() {
	return {
		create: {},
		edit: {}
	};
}
function applyProperty(target, issues, owner, operation, property, value, source) {
	const current = target[operation][property];
	if (current !== void 0 && current !== value) {
		issues.push(`${owner} defines conflicting ${operation}.${property} semantics (${String(current)} vs ${String(value)} from ${source}).`);
		return;
	}
	target[operation][property] = value;
}
function applyFlagInput(target, issues, owner, property, input) {
	if (input === void 0) return;
	if (typeof input === "boolean") {
		OPERATIONS.forEach((operation) => {
			applyProperty(target, issues, owner, operation, property, input, property);
		});
		return;
	}
	OPERATIONS.forEach((operation) => {
		const value = input[operation];
		if (value === void 0) return;
		applyProperty(target, issues, owner, operation, property, value, `${property}.${operation}`);
	});
}
function applyStateInput(target, issues, owner, operation, state) {
	if (!state) return;
	PROPERTIES.forEach((property) => {
		const value = state[property];
		if (value === void 0) return;
		applyProperty(target, issues, owner, operation, property, value, `${operation}.${property}`);
	});
}
function validateResolvedSemantics(owner, semantics) {
	const issues = [];
	OPERATIONS.forEach((operation) => {
		const state = semantics[operation];
		if (state.visible === false && state.required === true) issues.push(`${owner} cannot be required when ${operation}.visible is false.`);
	});
	return issues;
}
function normalizeFieldOperationSemantics(behavior, owner = "Field operation semantics") {
	const semantics = createEmptySemantics();
	if (!behavior) return semantics;
	const issues = [];
	const allowedOperations = normalizeOnly(behavior.only);
	if (allowedOperations) {
		const allowedOperationSet = new Set(allowedOperations);
		OPERATIONS.forEach((operation) => {
			applyProperty(semantics, issues, owner, operation, "visible", allowedOperationSet.has(operation), "only");
		});
	}
	applyFlagInput(semantics, issues, owner, "visible", behavior.visible);
	applyFlagInput(semantics, issues, owner, "required", behavior.required);
	applyFlagInput(semantics, issues, owner, "readonly", behavior.readonly);
	applyFlagInput(semantics, issues, owner, "disabled", behavior.disabled);
	applyStateInput(semantics, issues, owner, "create", behavior.create);
	applyStateInput(semantics, issues, owner, "edit", behavior.edit);
	issues.push(...validateResolvedSemantics(owner, semantics));
	if (issues.length > 0) throw new SmartCrudFieldOperationSemanticsError("Invalid SmartCrud field operation semantics.", issues);
	return semantics;
}
/**
* Validates field operation semantics and returns any issues found,
* without throwing. Uses the same validation logic as
* `normalizeFieldOperationSemantics` but collects issues instead of
* throwing `SmartCrudFieldOperationSemanticsError`.
*/
function collectOperationSemanticsIssues(owner, behavior) {
	if (!behavior) return [];
	const semantics = createEmptySemantics();
	const issues = [];
	const allowedOperations = normalizeOnly(behavior.only);
	if (allowedOperations) {
		const allowedOperationSet = new Set(allowedOperations);
		OPERATIONS.forEach((operation) => {
			applyProperty(semantics, issues, owner, operation, "visible", allowedOperationSet.has(operation), "only");
		});
	}
	applyFlagInput(semantics, issues, owner, "visible", behavior.visible);
	applyFlagInput(semantics, issues, owner, "required", behavior.required);
	applyFlagInput(semantics, issues, owner, "readonly", behavior.readonly);
	applyFlagInput(semantics, issues, owner, "disabled", behavior.disabled);
	applyStateInput(semantics, issues, owner, "create", behavior.create);
	applyStateInput(semantics, issues, owner, "edit", behavior.edit);
	issues.push(...validateResolvedSemantics(owner, semantics));
	return issues;
}
function resolveBaseVisibility(field) {
	if (field.visible !== void 0) return field.visible;
	if (field.hidden !== void 0) return !field.hidden;
	return true;
}
function resolveBaseState(field) {
	return {
		visible: resolveBaseVisibility(field),
		required: field.required ?? false,
		readonly: field.readonly ?? false,
		disabled: field.disabled ?? false
	};
}
function resolveFieldOperationSemantics(field, behavior, owner = "Field operation semantics") {
	const normalized = normalizeFieldOperationSemantics(behavior, owner);
	return OPERATIONS.reduce((resolved, operation) => {
		const baseState = resolveBaseState(field);
		resolved[operation] = {
			visible: normalized[operation].visible ?? baseState.visible,
			required: normalized[operation].required ?? baseState.required,
			readonly: normalized[operation].readonly ?? baseState.readonly,
			disabled: normalized[operation].disabled ?? baseState.disabled
		};
		return resolved;
	}, {});
}
function applyFieldOperationSemantics(field, operation, behavior, owner = `Field '${field.name}'`, target = "grid") {
	const resolved = resolveFieldOperationSemantics(field, behavior, owner)[operation];
	return {
		...field,
		...target === "form" ? { visibleOnForm: resolved.visible } : { visible: resolved.visible },
		hidden: !resolved.visible,
		required: resolved.required,
		readonly: resolved.readonly,
		disabled: resolved.disabled
	};
}
//#endregion
//#region assets/core/crud/useSmartCrudFields.ts
/**
* Runs the full SmartCrud field processing pipeline:
*   1. Operation semantics — adjusts visibility/required/readonly per create/edit
*   2. RBAC — filters or disables fields based on user roles
*   3. Conditional rules — evaluates visibleWhen / disabledWhen / computed
*   4. Dependency invalidation — refetches dependent field options on change
*   5. Field state merge — applies conditional state back to field definitions
*
* Extracted from SmartCrudPage to isolate the declarative rules pipeline
* from schema loading and routing concerns.
*/
function useSmartCrudFields(fields, activeOperation, formData, roles) {
	const permissionFilteredFields = useFieldPermissions((0, react.useMemo)(() => {
		if (!activeOperation) return fields;
		return fields.map((field) => {
			const runtimeField = field;
			if (!runtimeField.operation) return field;
			return applyFieldOperationSemantics(runtimeField, activeOperation, runtimeField.operation, `Field '${field.name}'`, "form");
		});
	}, [activeOperation, fields]), roles);
	const fieldStates = useConditionalRules(permissionFilteredFields, formData);
	useDependsOn(permissionFilteredFields, formData);
	return {
		processedFields: (0, react.useMemo)(() => permissionFilteredFields.map((field) => {
			if (field.isIdentity) return field;
			const state = fieldStates.find((s) => s.name === field.name);
			if (!state) return field;
			const updated = { ...field };
			updated.visibleOnForm = state.visible;
			updated.hidden = !state.visible;
			if (state.disabled) updated.disabled = true;
			if (state.computedValue !== void 0) updated.readonly = true;
			return updated;
		}), [permissionFilteredFields, fieldStates]),
		computedValues: (0, react.useMemo)(() => fieldStates.reduce((acc, state) => {
			if (state.computedValue !== void 0) acc[state.name] = state.computedValue;
			return acc;
		}, {}), [fieldStates])
	};
}
//#endregion
//#region assets/core/crud/SmartCrudPage.tsx
function CrudSkeleton() {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "p-6 space-y-4 animate-pulse",
		children: [/* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", { className: "h-8 bg-gray-200 rounded w-1/3" }), /* @__PURE__ */ (0, react_jsx_runtime.jsx)("div", { className: "h-64 bg-gray-200 rounded" })]
	});
}
function CrudError({ message, onRetry }) {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsxs)("div", {
		className: "p-6 text-red-600 space-y-2",
		children: [
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
				className: "font-semibold",
				children: "Failed to load resource schema"
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("p", {
				className: "text-sm",
				children: message
			}),
			/* @__PURE__ */ (0, react_jsx_runtime.jsx)("button", {
				onClick: onRetry,
				className: "text-blue-600 underline text-sm",
				type: "button",
				children: "Retry"
			})
		]
	});
}
function formatRuntimeErrorMessage(error) {
	const issues = "issues" in error && Array.isArray(error.issues) ? error.issues : [];
	return issues.length > 0 ? `${error.message} ${issues.join(" ")}` : error.message;
}
/**
* SmartCrudPage — wraps CrudPage with Hydra auto-discovery and declarative rules.
*
* ## Field resolution
* - If `resource.fields` is a non-empty array, it is used as-is (pass-through).
* - If `resource.fields` is empty / absent, fields are inferred from the Hydra
*   API spec at `/api/docs.json` via `useResourceSchema`.
* - While the schema loads: renders a skeleton loader.
* - If the schema fetch errors: renders a user-visible error with a retry button.
*
* ## Declarative rules pipeline (via `useSmartCrudFields`)
*   1. `useFieldPermissions` — RBAC: hides or disables fields based on user roles
*   2. `useConditionalRules` — evaluates visibleWhen / disabledWhen / computed
*   3. `useDependsOn`        — invalidates entity queries when dependency fields change
*
* ## Operation & form state (via `useSmartCrudOperation`)
*   Manages `activeOperation` + `formData` as a unified state pair, synced
*   from routing changes and the resource event bus (ADD/EDIT/CANCEL/SUCCESS).
*
* URL deep-linking is wired via `initialRecordId` / `initialIsNew` props on CrudPage.
*/
function SmartCrudPage({ resource, fieldOverrides }) {
	const queryClient = (0, _tanstack_react_query.useQueryClient)();
	const resolvedBaseResource = (0, react.useMemo)(() => resolveCrudResource(resource), [resource]);
	const events = resolvedBaseResource.events;
	const hasManualFields = !resource.fieldContract && Array.isArray(resource.fields) && resource.fields.length > 0;
	const { fields, isLoading, error, supportedOperations } = (0, _nubit_api_platform.useResourceSchema)(resolvedBaseResource.apiUrl, hasManualFields ? resource.fields : void 0, hasManualFields ? void 0 : fieldOverrides, resource.fieldContract);
	const hintFiredRef = (0, react.useRef)(false);
	(0, react.useEffect)(() => {
		if (!resource.fieldContract && !hasManualFields && !isLoading && !error && fields.length > 0 && !hintFiredRef.current) {
			hintFiredRef.current = true;
			logDevHint(resource.apiUrl, fields, supportedOperations);
		}
	}, [
		resource.fieldContract,
		hasManualFields,
		isLoading,
		error,
		fields,
		resource.apiUrl,
		supportedOperations
	]);
	const routingState = useRouting(resource.routing);
	const { activeOperation, formData, handleFormDataChange } = useSmartCrudOperation(events, routingState);
	const roles = useSmartCrudRoles();
	const { processedFields, computedValues } = useSmartCrudFields(fields, activeOperation, formData, (0, react.useMemo)(() => roles ?? [], [roles]));
	useMercureSubscription(resource.apiUrl, () => {
		if (events.SUCCESS) dispatch(events.SUCCESS);
	}, resolvedBaseResource.mercure !== false);
	const normalizedApiUrl = resolvedBaseResource.apiUrl.startsWith("/") ? resolvedBaseResource.apiUrl : `/${resolvedBaseResource.apiUrl}`;
	const resolvedResource = (0, react.useMemo)(() => ({
		...resolvedBaseResource,
		...hasManualFields ? {} : { fields },
		apiUrl: normalizedApiUrl,
		fields: processedFields,
		_supportedOperations: supportedOperations
	}), [
		fields,
		hasManualFields,
		normalizedApiUrl,
		processedFields,
		resolvedBaseResource,
		supportedOperations
	]);
	if (!hasManualFields && isLoading) return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CrudSkeleton, {});
	if (!hasManualFields && error) return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CrudError, {
		message: formatRuntimeErrorMessage(error),
		onRetry: () => queryClient.invalidateQueries({ queryKey: _nubit_api_platform.API_DOC_QUERY_KEY })
	});
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CrudPage, {
		resource: resolvedResource,
		onFormDataChange: handleFormDataChange,
		computedValues,
		initialRecordId: routingState.initialRecordId,
		initialIsNew: routingState.initialIsNew,
		initialFilters: routingState.initialFilters,
		onFiltersChange: routingState.syncFilters
	});
}
//#endregion
//#region assets/core/crud/fieldValidation.ts
var SmartCrudFieldContractError = class extends Error {
	issues;
	constructor(message, issues = []) {
		super(message);
		this.name = "SmartCrudFieldContractError";
		this.issues = issues;
	}
};
const CONTRACT_KEYS = ["source", "strategy"];
const HYDRA_CONTRACT_KEYS = [...CONTRACT_KEYS, "directives"];
const MANUAL_CONTRACT_KEYS = [...CONTRACT_KEYS, "fields"];
const OVERRIDE_DIRECTIVE_KEYS = [
	"kind",
	"key",
	"patch"
];
const REMOVE_DIRECTIVE_KEYS = ["kind", "key"];
const SYNTHETIC_DIRECTIVE_KEYS = ["kind", "field"];
const OPERATION_BEHAVIOR_KEYS = [
	"only",
	"visible",
	"required",
	"readonly",
	"disabled",
	"create",
	"edit"
];
const OPERATION_STATE_KEYS = [
	"visible",
	"required",
	"readonly",
	"disabled"
];
const MANUAL_FIELD_KEYS = [...[
	"isIdentity",
	"type",
	"col",
	"name",
	"label",
	"width",
	"height",
	"minWidth",
	"minHeight",
	"align",
	"sortable",
	"filterable",
	"hideable",
	"validators",
	"mode",
	"url",
	"loadOptions",
	"filters",
	"byKeyUrl",
	"textField",
	"valueField",
	"valueType",
	"format",
	"selectedFilterOperation",
	"filterValue",
	"data",
	"formatter",
	"itemFormatter",
	"visible",
	"defaultValue",
	"autoSelectIfSingle",
	"onChange",
	"onSelect",
	"onClick",
	"onKeyPress",
	"readonly",
	"disabled",
	"hidden",
	"required",
	"precision",
	"accept",
	"buttons",
	"hidingPriority",
	"searchEnabled",
	"searchExpr",
	"fixed",
	"fixedPosition",
	"helpText",
	"contentRender",
	"visibleOnForm",
	"maxLength",
	"multiple",
	"editorOptions",
	"sendAsString",
	"order",
	"visibleWhen",
	"disabledWhen",
	"computed",
	"dependsOn",
	"permissions"
], "operation"];
const FIELD_PATCH_KEYS = MANUAL_FIELD_KEYS.filter((key) => key !== "name");
function normalizeKey(value) {
	return value.trim();
}
function isPlainObject(value) {
	return typeof value === "object" && value !== null && !Array.isArray(value);
}
function formatAllowedKeys(keys) {
	return keys.join(", ");
}
function collectUnknownKeyIssues(owner, value, allowedKeys) {
	if (!isPlainObject(value)) return [`${owner} must be an object.`];
	return Object.keys(value).filter((key) => !allowedKeys.includes(key)).sort((left, right) => left.localeCompare(right)).map((key) => `${owner} contains unknown key '${key}'. Allowed keys: ${formatAllowedKeys(allowedKeys)}.`);
}
function collectOperationBehaviorIssues(owner, behavior) {
	if (behavior === void 0) return [];
	const issues = collectUnknownKeyIssues(`${owner} operation`, behavior, OPERATION_BEHAVIOR_KEYS);
	if (isPlainObject(behavior)) {
		if (behavior.create !== void 0) issues.push(...collectUnknownKeyIssues(`${owner} operation.create`, behavior.create, OPERATION_STATE_KEYS));
		if (behavior.edit !== void 0) issues.push(...collectUnknownKeyIssues(`${owner} operation.edit`, behavior.edit, OPERATION_STATE_KEYS));
	}
	issues.push(...collectOperationSemanticsIssues(owner, behavior));
	return issues;
}
function collectHydraFieldDirectiveIssues(directive, index) {
	const owner = `Directive #${index + 1}`;
	if (directive.kind === "override") return [
		...collectUnknownKeyIssues(owner, directive, OVERRIDE_DIRECTIVE_KEYS),
		...collectUnknownKeyIssues(`Override '${normalizeKey(String(directive.key ?? ""))}' patch`, directive.patch, FIELD_PATCH_KEYS),
		...collectOperationBehaviorIssues(`Override '${normalizeKey(String(directive.key ?? ""))}'`, directive.patch.operation)
	];
	if (directive.kind === "remove") return collectUnknownKeyIssues(owner, directive, REMOVE_DIRECTIVE_KEYS);
	const fieldName = normalizeKey(directive.field?.name ?? "");
	return [
		...collectUnknownKeyIssues(owner, directive, SYNTHETIC_DIRECTIVE_KEYS),
		...collectUnknownKeyIssues(`Synthetic field '${fieldName || `#${index + 1}`}'`, directive.field, MANUAL_FIELD_KEYS),
		...collectOperationBehaviorIssues(`Synthetic field '${fieldName || `#${index + 1}`}'`, directive.field.operation)
	];
}
function collectManualFieldIssues(field, index) {
	const fieldName = normalizeKey(field.name ?? "");
	return [...collectUnknownKeyIssues(`Manual field #${index + 1}`, field, MANUAL_FIELD_KEYS), ...collectOperationBehaviorIssues(`Manual field '${fieldName || `#${index + 1}`}'`, field.operation)];
}
function validateFieldContract(contract) {
	const issues = [];
	if (contract.source === "hydra") {
		issues.push(...collectUnknownKeyIssues("Hydra contract", contract, HYDRA_CONTRACT_KEYS));
		if (contract.strategy !== "augment") issues.push("Hydra contracts must use strategy 'augment'.");
		const targetedKeys = /* @__PURE__ */ new Map();
		const syntheticNames = /* @__PURE__ */ new Set();
		contract.directives.forEach((directive, index) => {
			issues.push(...collectHydraFieldDirectiveIssues(directive, index));
			if (directive.kind === "override" || directive.kind === "remove") {
				const key = normalizeKey(directive.key);
				if (!key) {
					issues.push(`Directive #${index + 1} must reference a non-empty field key.`);
					return;
				}
				const previous = targetedKeys.get(key);
				if (previous) issues.push(`Field '${key}' is targeted more than once in hydra directives (${previous} + ${directive.kind}).`);
				else targetedKeys.set(key, directive.kind);
				return;
			}
			const fieldName = normalizeKey(directive.field.name);
			if (!fieldName) {
				issues.push(`Directive #${index + 1} must define a non-empty synthetic field name.`);
				return;
			}
			if (syntheticNames.has(fieldName)) issues.push(`Synthetic field '${fieldName}' is declared more than once.`);
			else syntheticNames.add(fieldName);
		});
	}
	if (contract.source === "manual") {
		issues.push(...collectUnknownKeyIssues("Manual contract", contract, MANUAL_CONTRACT_KEYS));
		if (contract.strategy !== "replace") issues.push("Manual contracts must use strategy 'replace'.");
		const names = /* @__PURE__ */ new Set();
		contract.fields.forEach((field, index) => {
			issues.push(...collectManualFieldIssues(field, index));
			const fieldName = normalizeKey(field.name);
			if (!fieldName) {
				issues.push(`Manual field #${index + 1} must define a non-empty name.`);
				return;
			}
			if (names.has(fieldName)) issues.push(`Manual field '${fieldName}' is declared more than once.`);
			else names.add(fieldName);
		});
	}
	if (issues.length > 0) throw new SmartCrudFieldContractError("Invalid SmartCrud field contract.", issues);
	return contract;
}
function validateUniqueFieldNames(fields, owner) {
	const seen = /* @__PURE__ */ new Set();
	const duplicates = [];
	fields.forEach((field, index) => {
		const name = field.name.trim();
		if (!name) {
			duplicates.push(`${owner} field #${index + 1} must define a non-empty name.`);
			return;
		}
		if (seen.has(name)) {
			duplicates.push(`${owner} field '${name}' is declared more than once.`);
			return;
		}
		seen.add(name);
	});
	if (duplicates.length > 0) throw new SmartCrudFieldContractError("Invalid SmartCrud field resolution input.", duplicates);
}
function validateHydraFieldResolutionInput(baselineFields, contract) {
	validateUniqueFieldNames(baselineFields, "Hydra baseline");
	const baselineByName = new Map(baselineFields.map((field) => [field.name, field]));
	const issues = [];
	contract.directives.forEach((directive) => {
		if (directive.kind === "override" || directive.kind === "remove") {
			if (!baselineByName.has(directive.key)) issues.push(`${directive.kind === "override" ? "Override" : "Remove"} targets unknown Hydra field '${directive.key}'.`);
			return;
		}
		const syntheticName = normalizeKey(directive.field.name);
		if (baselineByName.has(syntheticName)) issues.push(`Synthetic field '${syntheticName}' collides with an inferred Hydra field. Use 'override' instead.`);
	});
	if (issues.length > 0) throw new SmartCrudFieldContractError("Invalid SmartCrud hydra field resolution.", issues);
}
function validateResolvedFieldNames(fields, owner) {
	validateUniqueFieldNames(fields, owner);
}
//#endregion
//#region assets/core/crud/fieldContract.ts
function defineFields(contract) {
	return validateFieldContract(contract);
}
const defineFieldContract = defineFields;
//#endregion
//#region assets/core/crud/resolveSmartCrudFields.ts
function cloneField(field) {
	return { ...field };
}
function applyFieldPatch(field, patch) {
	return {
		...field,
		...patch
	};
}
function toResolvedManualField(field) {
	return cloneField(field);
}
function resolveHydraFields(baselineFields, contract) {
	validateHydraFieldResolutionInput(baselineFields, contract);
	const overrides = /* @__PURE__ */ new Map();
	const removals = /* @__PURE__ */ new Set();
	const prepend = [];
	const append = [];
	contract.directives.forEach((directive) => {
		if (directive.kind === "override") {
			overrides.set(directive.key, directive.patch);
			return;
		}
		if (directive.kind === "remove") {
			removals.add(directive.key);
			return;
		}
		const syntheticField = toResolvedManualField(directive.field);
		if (directive.kind === "prepend") {
			prepend.push(syntheticField);
			return;
		}
		append.push(syntheticField);
	});
	const resolvedBaseline = baselineFields.flatMap((field) => {
		if (removals.has(field.name)) return [];
		const patch = overrides.get(field.name);
		return [patch ? applyFieldPatch(field, patch) : cloneField(field)];
	});
	const resolved = [
		...prepend,
		...resolvedBaseline,
		...append
	];
	validateResolvedFieldNames(resolved, "Resolved SmartCrud");
	return resolved;
}
function mergeOverrides(inferred, overrides) {
	return inferred.map((field) => {
		const override = overrides.find((entry) => entry.key === field.name);
		if (!override) return cloneField(field);
		const { key, ...overrideProps } = override;
		return {
			...field,
			...overrideProps
		};
	});
}
function resolveSmartCrudFields(options) {
	const { baselineFields = [], contract, legacyOverrides = [] } = options;
	if (!contract) return legacyOverrides.length > 0 ? mergeOverrides([...baselineFields], legacyOverrides) : [...baselineFields].map(cloneField);
	validateFieldContract(contract);
	if (contract.source === "manual") return contract.fields.map((field) => toResolvedManualField(field));
	return resolveHydraFields(baselineFields, contract);
}
//#endregion
//#region assets/core/i18n/coreTranslations.ts
const coreTranslationsEs = {
	"crudPage.selectedCount": "{{count}} seleccionados",
	"crudPage.auditTrailButton": "Historial",
	"crudPage.allColumns": "Todas",
	"crudPage.columnsLabel": "Columnas:",
	"crudPage.dialogTitleAdd": "Nuevo",
	"crudPage.dialogTitleEdit": "Editar",
	"crudPage.dialogTitleView": "Ver",
	"auditTrail.title": "Historial de cambios",
	"auditTrail.closeButton": "Cerrar historial de cambios",
	"auditTrail.selectRecord": "Seleccione un registro...",
	"auditTrail.loading": "Cargando...",
	"auditTrail.error": "Error al cargar el historial.",
	"auditTrail.empty": "No hay entradas de historial.",
	"auditTrail.action.create": "Creado",
	"auditTrail.action.update": "Actualizado",
	"auditTrail.action.delete": "Eliminado",
	"form.detailRequired": "Agrega al menos un ítem para continuar",
	"form.detailEmpty": "No hay ítems agregados",
	"form.validationError": "Uno o más campos del formulario no son válidos",
	"form.loading": "Cargando...",
	"form.groupOther": "Otros",
	"grid.noRecords": "No hay registros para mostrar",
	"grid.selectRequired": "Debe seleccionar un registro",
	"grid.loading": "Cargando...",
	"grid.buttonNew": "Nuevo",
	"grid.buttonEdit": "Editar",
	"grid.buttonDelete": "Eliminar",
	"grid.buttonRefresh": "Actualizar",
	"grid.rowsPerPage": "Filas por página",
	"dialog.buttonCancel": "Cancelar",
	"dialog.buttonSave": "Guardar",
	"dialog.confirmDelete": "¿Está seguro de eliminar el registro?",
	"dialog.confirmDeleteTitle": "Confirmar eliminación"
};
const coreTranslationsEn = {
	"crudPage.selectedCount": "{{count}} selected",
	"crudPage.auditTrailButton": "History",
	"crudPage.allColumns": "All",
	"crudPage.columnsLabel": "Columns:",
	"crudPage.dialogTitleAdd": "New",
	"crudPage.dialogTitleEdit": "Edit",
	"crudPage.dialogTitleView": "View",
	"auditTrail.title": "Change history",
	"auditTrail.closeButton": "Close change history",
	"auditTrail.selectRecord": "Select a record...",
	"auditTrail.loading": "Loading...",
	"auditTrail.error": "Error loading history.",
	"auditTrail.empty": "No history entries.",
	"auditTrail.action.create": "Created",
	"auditTrail.action.update": "Updated",
	"auditTrail.action.delete": "Deleted",
	"form.detailRequired": "Add at least one item to continue",
	"form.detailEmpty": "No items added",
	"form.validationError": "One or more form fields are invalid",
	"form.loading": "Loading...",
	"form.groupOther": "Other",
	"grid.noRecords": "No records to display",
	"grid.selectRequired": "You must select a record",
	"grid.loading": "Loading...",
	"grid.buttonNew": "New",
	"grid.buttonEdit": "Edit",
	"grid.buttonDelete": "Delete",
	"grid.buttonRefresh": "Refresh",
	"grid.rowsPerPage": "Rows per page",
	"dialog.buttonCancel": "Cancel",
	"dialog.buttonSave": "Save",
	"dialog.confirmDelete": "Are you sure you want to delete this record?",
	"dialog.confirmDeleteTitle": "Confirm deletion"
};
//#endregion
//#region assets/core/i18n/initCoreI18n.ts
function initCoreI18n() {
	i18next.default.addResourceBundle("es", "core", coreTranslationsEs, true, false);
	i18next.default.addResourceBundle("en", "core", coreTranslationsEn, true, false);
}
//#endregion
//#region assets/core/crud/hydra/index.ts
var hydra_exports = /* @__PURE__ */ __exportAll({});
__reExport(hydra_exports, require("@nubit/api-platform"));
__reExport(/* @__PURE__ */ __exportAll({
	AuditTrailPanel: () => AuditTrailPanel,
	CrudPage: () => CrudPage,
	DialogStoreProvider: () => DialogStoreProvider,
	SmartCrudFieldContractError: () => SmartCrudFieldContractError,
	SmartCrudFieldOperationSemanticsError: () => SmartCrudFieldOperationSemanticsError,
	SmartCrudPage: () => SmartCrudPage,
	SmartCrudRolesProvider: () => SmartCrudRolesProvider,
	applyFieldOperationSemantics: () => applyFieldOperationSemantics,
	collectOperationSemanticsIssues: () => collectOperationSemanticsIssues,
	createScopedEventBus: () => createScopedEventBus,
	defineFieldContract: () => defineFieldContract,
	defineFields: () => defineFields,
	defineResource: () => defineResource,
	initCoreI18n: () => initCoreI18n,
	mergeOverrides: () => mergeOverrides,
	normalizeFieldOperationSemantics: () => normalizeFieldOperationSemantics,
	resolveFieldOperationSemantics: () => resolveFieldOperationSemantics,
	resolveSmartCrudFields: () => resolveSmartCrudFields,
	useColumnPreset: () => useColumnPreset,
	useConditionalRules: () => useConditionalRules,
	useCoreTranslation: () => useCoreTranslation,
	useCrudDialogStore: () => useCrudDialogStore,
	useCrudPage: () => useCrudPage,
	useDependsOn: () => useDependsOn,
	useFieldPermissions: () => useFieldPermissions,
	usePermissions: () => usePermissions,
	useRouting: () => useRouting,
	useSelectionState: () => useSelectionState,
	useSmartCrudRoles: () => useSmartCrudRoles,
	validateFieldContract: () => validateFieldContract,
	validateHydraFieldResolutionInput: () => validateHydraFieldResolutionInput,
	validateResolvedFieldNames: () => validateResolvedFieldNames
}), hydra_exports);
//#endregion
//#region assets/core/common/Constants.ts
const Constants = {
	BASE_URL: "/",
	API_BASE_URL: "/api/",
	TEXT_ADD: "Nuevo",
	TEXT_EDIT: "Editar",
	TEXT_DELETE: "Eliminar",
	TEXT_EXPORT: "Exportar",
	TEXT_SAVE: "Guardar",
	TEXT_CANCEL: "Cerrar",
	TEXT_CONFIRM: "¿Está seguro que desea eliminar este registro?",
	TEXT_LOADING: "Procesando, por favor espere...",
	ICON_ADD: "fas fa-plus fa-1x text-primary",
	ICON_EDIT: "fas fa-pen fa-1x text-warning",
	ICON_DELETE: "fas fa-trash fa-1x text-danger",
	ICON_XLS: "fas fa-file-excel fa-1x text-success",
	ICON_PDF: "fas fa-file-pdf fa-1x text-danger",
	ICON_SEARCH: "fas fa-search fa-1x text-info",
	ICON_INVOICE: "fas fa-file-invoice fa-1x text-info",
	ICON_CLEAR: "fas fa-times fa-1x text-danger",
	MAX_ROW_COUNT: 2147483647
};
//#endregion
//#region assets/core/common/UserInformation.ts
function getStoredItem(key) {
	if (typeof localStorage === "undefined") return null;
	const item = localStorage.getItem(key);
	if (!item) return null;
	try {
		return JSON.parse(item);
	} catch {
		return null;
	}
}
const userInformation = () => {
	const user = getStoredItem("user") ?? void 0;
	return {
		currentUserId: user?.userId,
		currentUserName: user?.username ?? "",
		currentUserRoleId: user?.role,
		currentBranchId: getStoredItem("selectedBranchId") ?? "",
		currentBranchName: getStoredItem("selectedBranchName") ?? ""
	};
};
//#endregion
//#region assets/core/field/BaseFieldBuilder.ts
var BaseFieldBuilder = class {
	_field;
	constructor(type) {
		this._field = {
			isIdentity: false,
			type,
			col: 12,
			name: "",
			label: "",
			width: void 0,
			height: null,
			minWidth: void 0,
			minHeight: void 0,
			align: "left",
			sortable: true,
			filterable: true,
			hideable: true,
			validators: [],
			mode: "local",
			url: void 0,
			loadOptions: [],
			filters: [],
			byKeyUrl: null,
			textField: "",
			valueField: "",
			valueType: "string",
			filterValue: void 0,
			selectedFilterOperation: void 0,
			data: [],
			format: "",
			formatter: void 0,
			itemFormatter: void 0,
			visible: true,
			defaultValue: void 0,
			onChange: void 0,
			onSelect: void 0,
			onClick: void 0,
			onKeyPress: void 0,
			readonly: false,
			disabled: false,
			hidden: false,
			required: false,
			precision: 0,
			accept: null,
			buttons: [],
			hidingPriority: void 0,
			searchEnabled: true,
			searchExpr: null,
			fixed: false,
			fixedPosition: void 0,
			helpText: void 0,
			contentRender: null,
			visibleOnForm: true,
			maxLength: void 0,
			multiple: false,
			autoSelectIfSingle: false,
			sendAsString: false,
			editorOptions: void 0
		};
	}
	isIdentity(isIdentity) {
		this._field.isIdentity = isIdentity;
		return this;
	}
	col(col) {
		this._field.col = col;
		return this;
	}
	name(name) {
		this._field.name = name;
		return this;
	}
	label(label) {
		this._field.label = label;
		return this;
	}
	width(width) {
		this._field.width = width;
		return this;
	}
	height(height) {
		this._field.height = height;
		return this;
	}
	minWidth(minWidth) {
		this._field.minWidth = minWidth;
		return this;
	}
	minHeight(minHeight) {
		this._field.minHeight = minHeight;
		return this;
	}
	align(align) {
		this._field.align = align;
		return this;
	}
	sortable(sortable) {
		this._field.sortable = sortable;
		return this;
	}
	filterable(filterable) {
		this._field.filterable = filterable;
		return this;
	}
	hideable(hideable) {
		this._field.hideable = hideable;
		return this;
	}
	validators(validators) {
		this._field.validators = validators;
		return this;
	}
	mode(mode) {
		this._field.mode = mode;
		return this;
	}
	valueType(valueType) {
		this._field.valueType = valueType;
		return this;
	}
	filterValue(filterValue) {
		this._field.filterValue = filterValue;
		return this;
	}
	data(data) {
		this._field.data = data;
		return this;
	}
	format(format) {
		this._field.format = format;
		return this;
	}
	formatter(formatter) {
		this._field.formatter = formatter;
		return this;
	}
	itemFormatter(itemFormatter) {
		this._field.itemFormatter = itemFormatter;
		return this;
	}
	visible(visible) {
		this._field.visible = visible;
		return this;
	}
	defaultValue(defaultValue) {
		this._field.defaultValue = defaultValue;
		return this;
	}
	onChange(onChange) {
		this._field.onChange = onChange;
		return this;
	}
	onSelect(onSelect) {
		this._field.onSelect = onSelect;
		return this;
	}
	onClick(onClick) {
		this._field.onClick = onClick;
		return this;
	}
	readonly(readonly) {
		this._field.readonly = readonly;
		return this;
	}
	disabled(disabled) {
		this._field.disabled = disabled;
		return this;
	}
	hidden(hidden) {
		this._field.hidden = hidden;
		return this;
	}
	required(required) {
		this._field.required = required;
		return this;
	}
	precision(value) {
		this._field.precision = value;
		return this;
	}
	accept(value) {
		this._field.accept = value;
		return this;
	}
	buttons(value) {
		this._field.buttons = value;
		return this;
	}
	hidingPriority(value) {
		this._field.hidingPriority = value;
		return this;
	}
	searchEnabled(value) {
		this._field.searchEnabled = value;
		return this;
	}
	searchExpr(value) {
		this._field.searchExpr = value;
		return this;
	}
	fixed(value) {
		this._field.fixed = value;
		return this;
	}
	fixedPosition(value) {
		this._field.fixedPosition = value;
		return this;
	}
	helpText(value) {
		this._field.helpText = value;
		return this;
	}
	contentRender(value) {
		this._field.contentRender = value;
		return this;
	}
	visibleOnForm(value) {
		this._field.visibleOnForm = value;
		return this;
	}
	onKeyPress(param) {
		this._field.onKeyPress = param;
		return this;
	}
	autoSelectIfSingle(value) {
		this._field.autoSelectIfSingle = value;
		return this;
	}
	url(url) {
		this._field.url = url;
		return this;
	}
	loadOptions(param) {
		this._field.loadOptions = param;
		return this;
	}
	filters(filters) {
		this._field.filters = filters;
		return this;
	}
	byKeyUrl(byKeyUrl) {
		this._field.byKeyUrl = byKeyUrl;
		return this;
	}
	textField(textField) {
		this._field.textField = textField;
		return this;
	}
	valueField(valueField) {
		this._field.valueField = valueField;
		return this;
	}
	selectedFilterOperation(operation) {
		this._field.selectedFilterOperation = operation;
		return this;
	}
	sendAsString(value) {
		this._field.sendAsString = value;
		return this;
	}
	multiple(value) {
		this._field.multiple = value;
		return this;
	}
	editorOptions(param) {
		this._field.editorOptions = param;
		return this;
	}
	maxLength(value) {
		this._field.maxLength = value;
		return this;
	}
	visibleWhen(fn) {
		this._field.visibleWhen = fn;
		return this;
	}
	disabledWhen(fn) {
		this._field.disabledWhen = fn;
		return this;
	}
	computed(fn) {
		this._field.computed = fn;
		return this;
	}
	dependsOn(fields) {
		this._field.dependsOn = fields;
		return this;
	}
	permissions(perms) {
		this._field.permissions = perms;
		return this;
	}
	build() {
		return { ...this._field };
	}
};
//#endregion
//#region assets/core/field/FieldBuilders.ts
/**
* Factory for the standard hidden identity field (type NONE, name 'id').
*
* Usage:
*   identityField().build()
*   identityField().required(true).build()
*   identityField().valueType('number').build()
*
* Defaults set automatically:
*   isIdentity(true), visible(false), required(true), name('id'), label('Id')
*/
var IdentityFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("none");
		this._field.isIdentity = true;
		this._field.visible = false;
		this._field.required = true;
		this._field.name = "id";
		this._field.label = "Id";
	}
};
function identityField() {
	return new IdentityFieldBuilder();
}
/**
* Builder for ENTITY (remote select) fields.
*
* Required constructor args: url, valueField, textField — the three properties
* that are ALWAYS present on every ENTITY field in the codebase.
*
* Usage:
*   new EntityFieldBuilder('customers', 'id', 'businessName')
*     .label('Cliente')
*     .name('customer')
*     .mode('remote')
*     .formatter(cell => cell.value?.businessName)
*     .build()
*/
var EntityFieldBuilder = class extends BaseFieldBuilder {
	constructor(url, valueField, textField) {
		super("entity");
		this._field.url = url;
		this._field.valueField = valueField;
		this._field.textField = textField;
	}
};
function entityField(url, valueField, textField) {
	return new EntityFieldBuilder(url, valueField, textField);
}
/**
* Builder for CURRENCY fields.
*
* Defaults applied automatically:
*   sendAsString(true), align('right')
*
* Usage:
*   new CurrencyFieldBuilder()
*     .name('grandTotal')
*     .label('Total')
*     .formatter(cell => NumberUtils.formatCurrency(cell.value, cell.data?.currency))
*     .build()
*/
var CurrencyFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("currency");
		this._field.sendAsString = true;
		this._field.align = "right";
	}
};
function currencyField() {
	return new CurrencyFieldBuilder();
}
/**
* Factory for image upload fields (FILE type).
*
* Defaults: url=Constants.API_BASE_URL+'media', accept='image/*', align='center'
* Formatter must be provided by the caller since it depends on the data shape.
*
* Usage:
*   imageField(Constants.API_BASE_URL)
*     .name('photo')
*     .label('Imagen')
*     .formatter(e => SharedUtils.imageFormatter(e.value?.path, e.data?.name))
*     .build()
*/
var ImageFieldBuilder = class extends BaseFieldBuilder {
	constructor(apiBaseUrl) {
		super("file");
		this._field.url = apiBaseUrl + "media";
		this._field.accept = "image/*";
		this._field.align = "center";
	}
};
function imageField(apiBaseUrl) {
	return new ImageFieldBuilder(apiBaseUrl);
}
/**
* Builder for DATE fields.
*
* Defaults applied automatically:
*   type = DATE, defaultValue = new Date()
*
* Usage:
*   dateField()
*     .name('issueDate')
*     .label('Fecha')
*     .formatter(cell => SharedUtils.dateFormatter(cell.value))
*     .build()
*/
var DateFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("date");
		this._field.defaultValue = /* @__PURE__ */ new Date();
	}
};
function dateField() {
	return new DateFieldBuilder();
}
/**
* Builder for DATETIME fields.
*
* Defaults applied automatically:
*   type = DATETIME, defaultValue = new Date()
*
* Usage:
*   datetimeField()
*     .name('openedAt')
*     .label('Apertura')
*     .formatter(cell => SharedUtils.dateTimeFormatter(cell.value))
*     .build()
*/
var DateTimeFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("datetime");
		this._field.defaultValue = /* @__PURE__ */ new Date();
	}
};
function datetimeField() {
	return new DateTimeFieldBuilder();
}
/**
* Builder for NUMBER fields.
*
* Usage:
*   numberField()
*     .name('quantity')
*     .label('Cantidad')
*     .precision(2)
*     .align('center')
*     .build()
*/
var NumberFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("number");
	}
};
function numberField() {
	return new NumberFieldBuilder();
}
/**
* Builder for ENUM (local select) fields.
*
* The options array is a required constructor arg — every ENUM field in the
* codebase always provides it.
*
* Usage:
*   enumField([
*     { value: 'income', text: 'Ingreso' },
*     { value: 'expense', text: 'Egreso' },
*   ])
*     .name('type')
*     .label('Tipo')
*     .formatter(cell => cell.value === 'income' ? 'Ingreso' : 'Egreso')
*     .build()
*/
var EnumFieldBuilder = class extends BaseFieldBuilder {
	constructor(options) {
		super("enum");
		this._field.data = options;
	}
};
function enumField(options) {
	return new EnumFieldBuilder(options);
}
/**
* Builder for TEXT (plain string input) fields.
*
* Usage:
*   textField()
*     .name('businessName')
*     .label('Razón social')
*     .required(true)
*     .build()
*/
var TextFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("text");
	}
};
function textField() {
	return new TextFieldBuilder();
}
/**
* Builder for TEXTAREA (multi-line text) fields.
*
* Usage:
*   textareaField()
*     .name('note')
*     .label('Nota')
*     .build()
*/
var TextareaFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("textarea");
	}
};
function textareaField() {
	return new TextareaFieldBuilder();
}
/**
* Builder for NONE (display-only, no editor) fields.
*
* Usage:
*   noneField()
*     .name('sunatStatus')
*     .label('Envío sunat')
*     .formatter(cell => SharedUtils.billStatusFormatter(cell.value))
*     .build()
*/
var NoneFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("none");
	}
};
function noneField() {
	return new NoneFieldBuilder();
}
/**
* Builder for SWITCH (boolean toggle) fields.
*
* Usage:
*   switchField()
*     .name('active')
*     .label('Activo')
*     .defaultValue(true)
*     .formatter(SharedUtils.booleanFormatter)
*     .build()
*/
var SwitchFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("switch");
	}
};
function switchField() {
	return new SwitchFieldBuilder();
}
/**
* Builder for SELECT (local dropdown, value+text pairs) fields.
*
* Usage:
*   selectField()
*     .name('currency')
*     .label('Moneda')
*     .data([{ value: 'PEN', text: 'Soles' }, { value: 'USD', text: 'Dólares' }])
*     .build()
*/
var SelectFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("select");
	}
};
function selectField() {
	return new SelectFieldBuilder();
}
/**
* Builder for CHECKBOX (boolean check) fields.
*
* Usage:
*   checkboxField()
*     .name('emitInvoice')
*     .label('Emitir comprobante')
*     .defaultValue(false)
*     .build()
*/
var CheckboxFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("checkbox");
	}
};
function checkboxField() {
	return new CheckboxFieldBuilder();
}
/**
* Builder for PASSWORD (masked text input) fields.
*
* Usage:
*   passwordField()
*     .name('password')
*     .label('Contraseña')
*     .required(true)
*     .build()
*/
var PasswordFieldBuilder = class extends BaseFieldBuilder {
	constructor() {
		super("password");
	}
};
function passwordField() {
	return new PasswordFieldBuilder();
}
//#endregion
//#region assets/core/field/FieldBuilder.ts
var FieldBuilder = class {
	_field;
	constructor() {
		this._field = {
			isIdentity: false,
			type: "text",
			col: 12,
			name: "",
			label: "",
			width: void 0,
			height: null,
			minWidth: void 0,
			minHeight: void 0,
			align: "left",
			sortable: true,
			filterable: true,
			hideable: true,
			validators: [],
			mode: "local",
			url: void 0,
			loadOptions: [],
			filters: [],
			byKeyUrl: null,
			textField: "",
			valueField: "",
			valueType: "string",
			selectedFilterOperation: void 0,
			filterValue: void 0,
			data: [],
			format: "",
			formatter: void 0,
			itemFormatter: void 0,
			visible: true,
			defaultValue: void 0,
			autoSelectIfSingle: false,
			onChange: void 0,
			onSelect: void 0,
			onClick: void 0,
			onKeyPress: void 0,
			readonly: false,
			disabled: false,
			hidden: false,
			required: false,
			precision: 0,
			accept: null,
			buttons: [],
			hidingPriority: void 0,
			searchEnabled: true,
			searchExpr: null,
			fixed: false,
			fixedPosition: void 0,
			helpText: void 0,
			contentRender: null,
			visibleOnForm: true,
			maxLength: void 0,
			multiple: false,
			editorOptions: void 0,
			sendAsString: false
		};
	}
	isIdentity(isIdentity) {
		this._field.isIdentity = isIdentity;
		return this;
	}
	type(type) {
		this._field.type = type;
		return this;
	}
	col(col) {
		this._field.col = col;
		return this;
	}
	name(name) {
		this._field.name = name;
		return this;
	}
	label(label) {
		this._field.label = label;
		return this;
	}
	width(width) {
		this._field.width = width;
		return this;
	}
	height(height) {
		this._field.height = height;
		return this;
	}
	minWidth(minWidth) {
		this._field.minWidth = minWidth;
		return this;
	}
	minHeight(minHeight) {
		this._field.minHeight = minHeight;
		return this;
	}
	align(align) {
		this._field.align = align;
		return this;
	}
	sortable(sortable) {
		this._field.sortable = sortable;
		return this;
	}
	filterable(filterable) {
		this._field.filterable = filterable;
		return this;
	}
	hideable(hideable) {
		this._field.hideable = hideable;
		return this;
	}
	validators(validators) {
		this._field.validators = validators;
		return this;
	}
	mode(mode) {
		this._field.mode = mode;
		return this;
	}
	url(url) {
		this._field.url = url;
		return this;
	}
	loadOptions(param) {
		this._field.loadOptions = param;
		return this;
	}
	filters(filters) {
		this._field.filters = filters;
		return this;
	}
	byKeyUrl(byKeyUrl) {
		this._field.byKeyUrl = byKeyUrl;
		return this;
	}
	textField(textField) {
		this._field.textField = textField;
		return this;
	}
	valueField(valueField) {
		this._field.valueField = valueField;
		return this;
	}
	valueType(valueType) {
		this._field.valueType = valueType;
		return this;
	}
	selectedFilterOperation(operation) {
		this._field.selectedFilterOperation = operation;
		return this;
	}
	filterValue(filterValue) {
		this._field.filterValue = filterValue;
		return this;
	}
	data(data) {
		this._field.data = data;
		return this;
	}
	format(format) {
		this._field.format = format;
		return this;
	}
	formatter(formatter) {
		this._field.formatter = formatter;
		return this;
	}
	itemFormatter(itemFormatter) {
		this._field.itemFormatter = itemFormatter;
		return this;
	}
	visible(visible) {
		this._field.visible = visible;
		return this;
	}
	defaultValue(defaultValue) {
		this._field.defaultValue = defaultValue;
		return this;
	}
	onChange(onChange) {
		this._field.onChange = onChange;
		return this;
	}
	onSelect(onSelect) {
		this._field.onSelect = onSelect;
		return this;
	}
	onClick(onClick) {
		this._field.onClick = onClick;
		return this;
	}
	autoSelectIfSingle(value) {
		this._field.autoSelectIfSingle = value;
		return this;
	}
	readonly(readonly) {
		this._field.readonly = readonly;
		return this;
	}
	disabled(disabled) {
		this._field.disabled = disabled;
		return this;
	}
	hidden(hidden) {
		this._field.hidden = hidden;
		return this;
	}
	required(required) {
		this._field.required = required;
		return this;
	}
	precision(value) {
		this._field.precision = value;
		return this;
	}
	accept(value) {
		this._field.accept = value;
		return this;
	}
	buttons(value) {
		this._field.buttons = value;
		return this;
	}
	hidingPriority(value) {
		this._field.hidingPriority = value;
		return this;
	}
	searchEnabled(value) {
		this._field.searchEnabled = value;
		return this;
	}
	searchExpr(value) {
		this._field.searchExpr = value;
		return this;
	}
	fixed(value) {
		this._field.fixed = value;
		return this;
	}
	fixedPosition(value) {
		this._field.fixedPosition = value;
		return this;
	}
	helpText(value) {
		this._field.helpText = value;
		return this;
	}
	contentRender(value) {
		this._field.contentRender = value;
		return this;
	}
	visibleOnForm(value) {
		this._field.visibleOnForm = value;
		return this;
	}
	onKeyPress(param) {
		this._field.onKeyPress = param;
		return this;
	}
	multiple(value) {
		this._field.multiple = value;
		return this;
	}
	editorOptions(param) {
		this._field.editorOptions = param;
		return this;
	}
	sendAsString(value) {
		this._field.sendAsString = value;
		return this;
	}
	maxLength(value) {
		this._field.maxLength = value;
		return this;
	}
	build() {
		return { ...this._field };
	}
};
//#endregion
//#region assets/core/event/domainEvents.ts
/**
* Central registry of domain CRUD events for simple features that have no
* other model logic.  Collocated here with createCrudEvents so there is a
* single place to add/remove feature event namespaces.
*/
const branchEvents = createCrudEvents("branch");
const categoryEvents = createCrudEvents("category");
const companyEvents = createCrudEvents("company");
const inboundEvents = createCrudEvents("inbound");
const operationEvents = createCrudEvents("operation");
const paymentMethodEvents = createCrudEvents("payment-methods");
const permissionEvents = createCrudEvents("permission");
const priceEvents = createCrudEvents("price");
const priceListEvents = createCrudEvents("price-list");
const roleEvents = createCrudEvents("role");
const sunatCatalogEvents = createCrudEvents("sunat-catalog");
const supplierEvents = createCrudEvents("supplier");
const unitOfMeasureEvents = createCrudEvents("unit-of-measure");
const userEvents = createCrudEvents("user");
const voucherSeriesEvents = createCrudEvents("voucher-series");
const voucherTypeEvents = createCrudEvents("voucherType");
const warehouseEvents = createCrudEvents("warehouse");
//#endregion
//#region assets/core/provider/CoreProvider.tsx
function CoreProvider({ children, http, httpClient, runtime }) {
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CoreHttpProvider, {
		client: httpClient,
		config: http,
		children: /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CoreRuntimeProvider, {
			runtime,
			children
		})
	});
}
//#endregion
exports.ApiPlatformRemoteDataSource = ApiPlatformRemoteDataSource;
Object.defineProperty(exports, "AppDialog", {
	enumerable: true,
	get: function() {
		return _nubit_ui.AppDialog;
	}
});
Object.defineProperty(exports, "ConfirmDialog", {
	enumerable: true,
	get: function() {
		return _nubit_ui.ConfirmDialog;
	}
});
exports.Constants = Constants;
exports.CoreConfigProvider = CoreConfigProvider;
exports.CoreHttpClient = CoreHttpClient;
exports.CoreHttpProvider = CoreHttpProvider;
exports.CoreProvider = CoreProvider;
exports.CoreRuntimeProvider = CoreRuntimeProvider;
exports.CrudPage = CrudPage;
exports.DATA_GRID_EVENTS = DATA_GRID_EVENTS;
exports.DataGridView = NativeDataGridView;
exports.DateUtils = DateUtils;
exports.DialogView = CrudDialogView;
exports.FORM_EVENTS = FORM_EVENTS;
exports.FieldBuilder = FieldBuilder;
exports.FieldType = FieldType;
exports.FormView = NativeFormView;
exports.LIMA_TZ = LIMA_TZ;
exports.MercureManager = mercureManager;
exports.MercureProvider = MercureProvider;
exports.SmartCrudPage = SmartCrudPage;
exports.SmartCrudRolesProvider = SmartCrudRolesProvider;
exports.branchEvents = branchEvents;
exports.categoryEvents = categoryEvents;
exports.checkboxField = checkboxField;
exports.companyEvents = companyEvents;
exports.configureCoreDate = configureCoreDate;
exports.createCoreHttpClient = createCoreHttpClient;
exports.createCrudEvents = createCrudEvents;
exports.createScopedEventBus = createScopedEventBus;
exports.currencyField = currencyField;
exports.dateField = dateField;
exports.datetimeField = datetimeField;
exports.defineFieldContract = defineFieldContract;
exports.defineFields = defineFields;
exports.defineResource = defineResource;
exports.dispatch = dispatch;
exports.entityField = entityField;
exports.enumField = enumField;
exports.getCoreLocale = getCoreLocale;
exports.getCoreTimezone = getCoreTimezone;
exports.identityField = identityField;
exports.imageField = imageField;
exports.inboundEvents = inboundEvents;
exports.initCoreI18n = initCoreI18n;
exports.mergeOverrides = mergeOverrides;
exports.noneField = noneField;
exports.numberField = numberField;
exports.operationEvents = operationEvents;
exports.passwordField = passwordField;
exports.paymentMethodEvents = paymentMethodEvents;
exports.permissionEvents = permissionEvents;
exports.priceEvents = priceEvents;
exports.priceListEvents = priceListEvents;
exports.resolveSmartCrudFields = resolveSmartCrudFields;
exports.roleEvents = roleEvents;
exports.selectField = selectField;
exports.sunatCatalogEvents = sunatCatalogEvents;
exports.supplierEvents = supplierEvents;
exports.switchField = switchField;
exports.textField = textField;
exports.textareaField = textareaField;
exports.unitOfMeasureEvents = unitOfMeasureEvents;
exports.useCoreConfig = useCoreConfig;
exports.useCoreHttpClient = useCoreHttpClient;
exports.useCoreRuntime = useCoreRuntime;
exports.useCoreTranslation = useCoreTranslation;
exports.useCrudPage = useCrudPage;
exports.useEvents = useEvents;
exports.useMercureHub = useMercureHub;
exports.useMercureSubscription = useMercureSubscription;
exports.usePermissions = usePermissions;
exports.useSelectionState = useSelectionState;
exports.useSmartCrudRoles = useSmartCrudRoles;
exports.userEvents = userEvents;
exports.userInformation = userInformation;
exports.voucherSeriesEvents = voucherSeriesEvents;
exports.voucherTypeEvents = voucherTypeEvents;
exports.warehouseEvents = warehouseEvents;
