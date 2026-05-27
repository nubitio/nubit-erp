import React, { ReactNode } from "react";

//#region assets/core/data/DataRecord.d.ts
type DataRecord = Record<string, unknown>;
//#endregion
//#region assets/core/data/GridData.d.ts
interface GridData<T> {
  data: T[];
  totalCount: number;
  summary: T[] | null;
}
//#endregion
//#region assets/core/date/DateUtils.d.ts
declare class DateUtils {
  static dateFormatter(date: Date): string;
  static dateParser(dateString: string): Date;
  static format(dateString: string, formatOptions?: Intl.DateTimeFormatOptions): string;
  static addDays(fieldValue: string | number | Date, days: number): Date;
  static addMonths(fieldValue: string | number | Date, months: number): Date;
  static addYears(fieldValue: string | number | Date, years: number): Date;
  static dayDiff(startDate: string | number | Date, endDate: string | number | Date): number;
  static monthDiff(startDate: string | number | Date, endDate: string | number | Date): number;
}
//#endregion
//#region assets/core/config/CoreConfig.d.ts
interface CoreConfig {
  locale: string;
  timezone: string;
}
declare function configureCoreDate(config: Partial<CoreConfig>): void;
declare function getCoreLocale(): string;
declare function getCoreTimezone(): string;
interface CoreConfigProviderProps {
  locale: string;
  timezone: string;
  children: React.ReactNode;
}
declare const CoreConfigProvider: ({
  locale,
  timezone,
  children
}: CoreConfigProviderProps) => import("react/jsx-runtime").JSX.Element;
declare function useCoreConfig(): CoreConfig;
//#endregion
//#region assets/core/event/EventHook.d.ts
type Unsubscribe = () => void;
/** Minimal subscription token — compatible with RxJS Subscription.unsubscribe() call-sites */
interface EventSubscription {
  unsubscribe: Unsubscribe;
  /** Convenience alias used by some call-sites via .add() — no-op, kept for compat */
  add: (teardown: Unsubscribe) => EventSubscription;
}
declare const useEvents: () => readonly [<T>(name: string, handler: (payload: T) => void) => EventSubscription, <T>(name: string, payload?: T) => void];
declare const dispatch: <T>(name: string, payload?: T) => void;
//#endregion
//#region assets/core/event/createCrudEvents.d.ts
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
declare const createCrudEvents: <TPrefix extends string>(prefix: TPrefix) => {
  readonly ADD: `${TPrefix}:add`;
  readonly EDIT: `${TPrefix}:edit`;
  readonly DELETE: `${TPrefix}:delete`;
  readonly SAVE: `${TPrefix}:save`;
  readonly CANCEL: `${TPrefix}:cancel`;
  readonly SUCCESS: `${TPrefix}:success`;
  readonly LOADING: `${TPrefix}:loading`;
};
//#endregion
//#region assets/core/event/EventTypes.d.ts
/**
 * Typed event name interfaces for core components.
 * Each field is optional — only declare the events your feature uses.
 */
interface DataGridEventNames {
  ADD?: string;
  EDIT?: string;
  DELETE?: string;
  SUCCESS?: string;
  LOADING?: string;
  CANCEL?: string;
  EXPORT?: string;
}
interface FormEventNames {
  ADD?: string;
  EDIT?: string;
  DELETE?: string;
  SAVE?: string;
  SUCCESS?: string;
  LOADING?: string;
  CANCEL?: string;
}
interface DialogEventNames {
  ADD?: string;
  EDIT?: string;
  SAVE?: string;
  SUCCESS?: string;
  LOADING?: string;
  CANCEL?: string;
}
interface ToolbarButtonItem {
  icon?: string;
  text: string;
  hint?: string;
  /** Semantic button type. Common values: 'default' | 'normal' | 'danger' | 'success' | 'back' */
  type?: string;
  onClick?: () => void;
  disabled?: boolean;
  visible?: boolean;
}
//#endregion
//#region assets/core/event/createScopedEventBus.d.ts
/**
 * A `FormEventNames` object whose event strings are namespaced with a
 * per-instance scope ID to prevent cross-component event leakage.
 * The extra `_scopeId` property carries the raw 8-char scope token for
 * diagnostics / testing.
 */
type ScopedFormEventNames = FormEventNames & {
  readonly _scopeId: string;
};
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
declare function createScopedEventBus(resourceId: string): ScopedFormEventNames;
//#endregion
//#region assets/core/i18n/CoreTranslationKeys.d.ts
interface CoreTranslationKeys {
  'crudPage.selectedCount': string;
  'crudPage.auditTrailButton': string;
  'crudPage.allColumns': string;
  'crudPage.columnsLabel': string;
  'crudPage.dialogTitleAdd': string;
  'crudPage.dialogTitleEdit': string;
  'crudPage.dialogTitleView': string;
  'crudPage.schemaErrorTitle': string;
  'crudPage.schemaErrorMessage': string;
  'crudPage.schemaErrorDetails': string;
  'crudPage.schemaErrorRetry': string;
  'auditTrail.title': string;
  'auditTrail.closeButton': string;
  'auditTrail.selectRecord': string;
  'auditTrail.loading': string;
  'auditTrail.error': string;
  'auditTrail.empty': string;
  'auditTrail.action.create': string;
  'auditTrail.action.update': string;
  'auditTrail.action.delete': string;
  'form.detailRequired': string;
  'form.detailEmpty': string;
  'form.validationError': string;
  'form.loading': string;
  'form.groupOther': string;
  'grid.noRecords': string;
  'grid.selectRequired': string;
  'grid.loading': string;
  'grid.buttonNew': string;
  'grid.buttonEdit': string;
  'grid.buttonDelete': string;
  'grid.buttonRefresh': string;
  'grid.rowsPerPage': string;
  'dialog.buttonCancel': string;
  'dialog.buttonSave': string;
  'dialog.confirmDelete': string;
  'dialog.confirmDeleteTitle': string;
  'common.yes': string;
  'common.no': string;
  'validation.defaultError': string;
  'form.searchButton': string;
  'form.actionButton': string;
}
//#endregion
//#region assets/core/i18n/coreTranslations.d.ts
declare const coreTranslationsEs: CoreTranslationKeys;
declare const coreTranslationsEn: CoreTranslationKeys;
//#endregion
//#region assets/core/i18n/useCoreTranslation.d.ts
declare function useCoreTranslation(): {
  t: (key: keyof CoreTranslationKeys, options?: DataRecord) => string;
};
//#endregion
//#region assets/core/i18n/initCoreI18n.d.ts
declare function initCoreI18n(): void;
//#endregion
//#region assets/core/http/CoreHttpClient.d.ts
type CoreResponseType = 'json' | 'arraybuffer' | 'blob' | 'text';
interface CoreRequestConfig {
  params?: DataRecord;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  responseType?: CoreResponseType;
}
interface CoreHttpResponse<T> {
  response: Response;
  data: T;
  headers: Headers;
  status: number;
}
interface CoreHttpErrorData {
  detail?: string;
  message?: string;
  violations?: unknown;
  [key: string]: unknown;
}
interface CoreHttpError extends Error {
  status?: number;
  data?: CoreHttpErrorData;
}
interface CoreHttpClientConfig {
  baseUrl?: string;
  credentials?: RequestCredentials;
  locale?: string;
  refreshPath?: string;
  loginPath?: string;
  onUnauthorized?: (error: CoreHttpError) => void;
  onError?: (error: CoreHttpError) => void;
}
declare class CoreHttpClient {
  private readonly config;
  private refreshPromise;
  constructor(config?: CoreHttpClientConfig);
  private headers;
  private refreshSession;
  private safeErrorData;
  private request;
  get<T>(url: string, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  post<T>(url: string, data?: unknown, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  put<T>(url: string, data?: unknown, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  patch<T>(url: string, data?: unknown, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
  delete<T>(url: string, config?: CoreRequestConfig): Promise<CoreHttpResponse<T>>;
}
declare function createCoreHttpClient(config?: CoreHttpClientConfig): CoreHttpClient;
//#endregion
//#region assets/core/http/CoreHttpContext.d.ts
interface CoreHttpProviderProps {
  children: React.ReactNode;
  client?: CoreHttpClient;
  config?: CoreHttpClientConfig;
}
declare function CoreHttpProvider({
  children,
  client,
  config
}: CoreHttpProviderProps): import("react/jsx-runtime").JSX.Element;
declare function useCoreHttpClient(): CoreHttpClient;
//#endregion
//#region assets/core/runtime/CoreRuntimeContext.d.ts
type CoreNotificationType = 'success' | 'error' | 'warning' | 'info';
interface CoreRuntime {
  notify: (message: string, type?: CoreNotificationType, durationMs?: number) => void;
  confirm: (message: string) => boolean;
}
interface CoreRuntimeProviderProps {
  children: React.ReactNode;
  runtime?: Partial<CoreRuntime>;
}
declare function CoreRuntimeProvider({
  children,
  runtime
}: CoreRuntimeProviderProps): import("react/jsx-runtime").JSX.Element;
declare function useCoreRuntime(): CoreRuntime;
//#endregion
//#region assets/core/provider/CoreProvider.d.ts
interface CoreProviderProps {
  children: React.ReactNode;
  http?: CoreHttpClientConfig;
  httpClient?: CoreHttpClient;
  runtime?: Partial<CoreRuntime>;
}
declare function CoreProvider({
  children,
  http,
  httpClient,
  runtime
}: CoreProviderProps): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/mercure/MercureProvider.d.ts
interface MercureProviderProps {
  /** Hub URL discovered from the `Link` header, or null if unavailable. */
  hubUrl: string | null;
  children: ReactNode;
}
/**
 * Provides the Mercure hub URL to the React tree and keeps `MercureManager`
 * in sync whenever the URL changes.
 */
declare function MercureProvider({
  hubUrl,
  children
}: MercureProviderProps): import("react/jsx-runtime").JSX.Element;
//#endregion
//#region assets/core/mercure/useMercureHub.d.ts
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
declare function useMercureHub(): string | null;
//#endregion
//#region assets/core/mercure/useMercureSubscription.d.ts
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
declare function useMercureSubscription(apiUrl: string | undefined, onUpdate: () => void, enabled?: boolean): void;
//#endregion
//#region assets/core/mercure/MercureManager.d.ts
/**
 * MercureManager — Singleton that manages the lifecycle of EventSource connections.
 *
 * Inspired by the `manager.ts` pattern from `api-platform/admin`, adapted for this project:
 * - Ref-counting per topic: multiple subscribers to the same topic share one EventSource.
 * - Graceful degradation: if hubUrl is null, subscribe() is a no-op.
 * - Topic format: full URI or URI Template (RFC 6570), e.g. `https://host/api/products/{id}`.
 *   The hub receives it as a query param: `?topic=<encoded-topic>`.
 */
declare class MercureManager {
  private hubUrl;
  private readonly topics;
  private readonly hubUrlListeners;
  /**
   * Update the hub URL (called by the Axios interceptor when the `Link` header is discovered,
   * or by MercureProvider). Notifies all registered listeners.
   *
   * Idempotent: if the URL is already set to the same value, listeners are NOT re-notified.
   */
  setHubUrl(url: string | null): void;
  getHubUrl(): string | null;
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
  onHubUrlChange(callback: (url: string | null) => void): () => void;
  /**
   * Subscribe to a Mercure topic.
   *
   * @param topic    Full topic URI or URI Template, e.g. `https://host/api/products/{id}`.
   * @param callback Called with the parsed JSON payload on each SSE message.
   *
   * If `hubUrl` is null (hub not configured), this is a no-op (graceful degradation).
   * If an EventSource for this topic already exists, it is reused (ref-counting).
   */
  subscribe(topic: string, callback: (data: unknown) => void): void;
  /**
   * Unsubscribe a specific callback from a topic.
   *
   * Decrements the ref count. When count reaches 0, the EventSource is closed
   * and removed from the internal map.
   */
  unsubscribe(topic: string, callback: (data: unknown) => void): void;
  /**
   * Close all active EventSource connections and clear the topic map.
   *
   * Called internally when the hub URL changes so stale connections
   * bound to the previous hub are not leaked.
   */
  disconnectAll(): void;
}
/** Singleton instance — shared across the entire application. */
declare const mercureManager: MercureManager;
//#endregion
export { type CoreConfig, CoreConfigProvider, type CoreConfigProviderProps, CoreHttpClient, type CoreHttpClientConfig, type CoreHttpError, type CoreHttpErrorData, CoreHttpProvider, type CoreHttpProviderProps, type CoreHttpResponse, type CoreNotificationType, CoreProvider, type CoreProviderProps, type CoreRequestConfig, type CoreResponseType, type CoreRuntime, CoreRuntimeProvider, type CoreRuntimeProviderProps, type CoreTranslationKeys, type DataGridEventNames, type DataRecord, DateUtils, type DialogEventNames, type EventSubscription, type FormEventNames, type GridData, mercureManager as MercureManager, type MercureManager as MercureManagerType, MercureProvider, type MercureProviderProps, type ScopedFormEventNames, type ToolbarButtonItem, configureCoreDate, coreTranslationsEn, coreTranslationsEs, createCoreHttpClient, createCrudEvents, createScopedEventBus, dispatch, getCoreLocale, getCoreTimezone, initCoreI18n, useCoreConfig, useCoreHttpClient, useCoreRuntime, useCoreTranslation, useEvents, useMercureHub, useMercureSubscription };