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
exports.CoreConfigProvider = CoreConfigProvider;
exports.CoreHttpClient = CoreHttpClient;
exports.CoreHttpProvider = CoreHttpProvider;
exports.CoreProvider = CoreProvider;
exports.CoreRuntimeProvider = CoreRuntimeProvider;
exports.DateUtils = DateUtils;
exports.LIMA_TZ = LIMA_TZ;
exports.MercureManager = mercureManager;
exports.MercureProvider = MercureProvider;
exports.configureCoreDate = configureCoreDate;
exports.createCoreHttpClient = createCoreHttpClient;
exports.dispatch = dispatch;
exports.getCoreLocale = getCoreLocale;
exports.getCoreTimezone = getCoreTimezone;
exports.useCoreConfig = useCoreConfig;
exports.useCoreHttpClient = useCoreHttpClient;
exports.useCoreRuntime = useCoreRuntime;
exports.useEvents = useEvents;
exports.useMercureHub = useMercureHub;
exports.useMercureSubscription = useMercureSubscription;
