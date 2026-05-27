Object.defineProperty(exports, Symbol.toStringTag, { value: "Module" });
//#region \0rolldown/runtime.js
var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
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
var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", {
	value: mod,
	enumerable: true
}) : target, mod));
//#endregion
let react = require("react");
react = __toESM(react, 1);
let react_jsx_runtime = require("react/jsx-runtime");
let react_i18next = require("react-i18next");
let i18next = require("i18next");
i18next = __toESM(i18next, 1);
//#region assets/core/config/CoreConfig.tsx
const _coreConfig = {
	locale: "es",
	timezone: "UTC",
	apiBaseUrl: "/api/"
};
/**
* Configure core runtime values (locale, timezone, apiBaseUrl).
* This updates the module-level singleton so it can be read from non-React code
* (e.g. inside defineResource, entityField builders, DateUtils, etc.).
*/
function configureCore(config) {
	if (config.locale !== void 0) _coreConfig.locale = config.locale;
	if (config.timezone !== void 0) _coreConfig.timezone = config.timezone;
	if (config.apiBaseUrl !== void 0) _coreConfig.apiBaseUrl = config.apiBaseUrl;
}
/**
* @deprecated Use configureCore() instead.
*/
const configureCoreDate = configureCore;
function getCoreLocale() {
	return _coreConfig.locale;
}
function getCoreTimezone() {
	return _coreConfig.timezone;
}
function getCoreApiBaseUrl() {
	return _coreConfig.apiBaseUrl;
}
const CoreConfigContext = react.default.createContext(_coreConfig);
const CoreConfigProvider = ({ locale, timezone, apiBaseUrl, children }) => {
	configureCore({
		locale,
		timezone,
		apiBaseUrl
	});
	const value = react.default.useMemo(() => ({
		locale,
		timezone,
		apiBaseUrl
	}), [
		locale,
		timezone,
		apiBaseUrl
	]);
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
/**
* Recommended default for new projects using @nubit/core.
* You should almost always override this via CoreConfigProvider.
*/
const DEFAULT_TIMEZONE = "UTC";
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
//#region assets/core/event/EventHook.ts
const listeners = /* @__PURE__ */ new Map();
function subscribe(name, handler) {
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
		const sub = subscribe(name, handler);
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
//#region assets/core/i18n/coreTranslations.ts
const coreTranslationsEs = {
	"crudPage.selectedCount": "{{count}} seleccionados",
	"crudPage.auditTrailButton": "Historial",
	"crudPage.allColumns": "Todas",
	"crudPage.columnsLabel": "Columnas:",
	"crudPage.dialogTitleAdd": "Nuevo",
	"crudPage.dialogTitleEdit": "Editar",
	"crudPage.dialogTitleView": "Ver",
	"crudPage.schemaErrorTitle": "No se pudo cargar esta vista",
	"crudPage.schemaErrorMessage": "La configuración del recurso no está disponible. Intenta nuevamente o contacta a soporte si el problema continúa.",
	"crudPage.schemaErrorDetails": "Detalle técnico",
	"crudPage.schemaErrorRetry": "Reintentar",
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
	"dialog.confirmDeleteTitle": "Confirmar eliminación",
	"common.yes": "Sí",
	"common.no": "No",
	"validation.defaultError": "Valor inválido",
	"form.searchButton": "Buscar",
	"form.actionButton": "Acción"
};
const coreTranslationsEn = {
	"crudPage.selectedCount": "{{count}} selected",
	"crudPage.auditTrailButton": "History",
	"crudPage.allColumns": "All",
	"crudPage.columnsLabel": "Columns:",
	"crudPage.dialogTitleAdd": "New",
	"crudPage.dialogTitleEdit": "Edit",
	"crudPage.dialogTitleView": "View",
	"crudPage.schemaErrorTitle": "This view could not be loaded",
	"crudPage.schemaErrorMessage": "The resource configuration is not available. Try again or contact support if the problem continues.",
	"crudPage.schemaErrorDetails": "Technical details",
	"crudPage.schemaErrorRetry": "Retry",
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
	"dialog.confirmDeleteTitle": "Confirm deletion",
	"common.yes": "Yes",
	"common.no": "No",
	"validation.defaultError": "Invalid value",
	"form.searchButton": "Search",
	"form.actionButton": "Action"
};
//#endregion
//#region assets/core/i18n/useCoreTranslation.ts
function useCoreTranslation() {
	const { t } = (0, react_i18next.useTranslation)("core");
	return { t: (key, options) => t(key, options) };
}
//#endregion
//#region assets/core/i18n/initCoreI18n.ts
function initCoreI18n() {
	i18next.default.addResourceBundle("es", "core", coreTranslationsEs, true, false);
	i18next.default.addResourceBundle("en", "core", coreTranslationsEn, true, false);
}
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
	/**
	* Built-in cookie-based refresh (the original behavior).
	* Only used when `refreshFn` is not provided in config.
	*/
	async performBuiltInRefresh() {
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
	async performRefresh() {
		if (this.config.refreshFn) return this.config.refreshFn(this);
		return this.performBuiltInRefresh();
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
		const shouldAutoRefresh = this.config.autoRefresh !== false;
		if (response.status === 401 && retryOnUnauthorized && !isAuthEndpoint && shouldAutoRefresh) try {
			await this.performRefresh();
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
	const value = (0, react.useMemo)(() => {
		if (client) return client;
		return createCoreHttpClient({
			...config,
			baseUrl: config?.baseUrl ?? getCoreApiBaseUrl()
		});
	}, [client, config]);
	return /* @__PURE__ */ (0, react_jsx_runtime.jsx)(CoreHttpContext.Provider, {
		value,
		children
	});
}
function useCoreHttpClient() {
	return (0, react.useContext)(CoreHttpContext);
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
* // In your app root:
* <MercureProvider hubUrl={discoveredHubUrl}>
*   <App />
* </MercureProvider>
* ```
*
* The `hubUrl` is typically discovered from the `Link` header of API responses
* (common pattern with API Platform + Mercure).
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
* Hub discovery (usually from a `Link` header) must be done by the host application.
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
exports.CoreConfigProvider = CoreConfigProvider;
exports.CoreHttpClient = CoreHttpClient;
exports.CoreHttpProvider = CoreHttpProvider;
exports.CoreProvider = CoreProvider;
exports.CoreRuntimeProvider = CoreRuntimeProvider;
exports.DEFAULT_TIMEZONE = DEFAULT_TIMEZONE;
exports.DateUtils = DateUtils;
exports.MercureManager = mercureManager;
exports.MercureProvider = MercureProvider;
exports.configureCore = configureCore;
exports.configureCoreDate = configureCoreDate;
exports.coreTranslationsEn = coreTranslationsEn;
exports.coreTranslationsEs = coreTranslationsEs;
exports.createCoreHttpClient = createCoreHttpClient;
exports.createCrudEvents = createCrudEvents;
exports.createScopedEventBus = createScopedEventBus;
exports.dispatch = dispatch;
exports.getCoreApiBaseUrl = getCoreApiBaseUrl;
exports.getCoreLocale = getCoreLocale;
exports.getCoreTimezone = getCoreTimezone;
exports.initCoreI18n = initCoreI18n;
exports.useCoreConfig = useCoreConfig;
exports.useCoreHttpClient = useCoreHttpClient;
exports.useCoreRuntime = useCoreRuntime;
exports.useCoreTranslation = useCoreTranslation;
exports.useEvents = useEvents;
exports.useMercureHub = useMercureHub;
exports.useMercureSubscription = useMercureSubscription;
