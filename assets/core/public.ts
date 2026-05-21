// ── Base data types ───────────────────────────────────────────────────────────
export type { DataRecord } from './data/DataRecord';
export type { GridData } from './data/GridData';

// ── Date utilities ────────────────────────────────────────────────────────────
export { DateUtils, LIMA_TZ } from './date';

// ── Core config ───────────────────────────────────────────────────────────────
export {
  CoreConfigProvider,
  configureCoreDate,
  getCoreLocale,
  getCoreTimezone,
  useCoreConfig,
} from './config/CoreConfig';
export type { CoreConfig, CoreConfigProviderProps } from './config/CoreConfig';

// ── Event primitives ──────────────────────────────────────────────────────────
export { dispatch, useEvents } from './event/EventHook';
export type { EventSubscription } from './event/EventHook';

// ── Provider ──────────────────────────────────────────────────────────────────
export { CoreProvider } from './provider';
export type { CoreProviderProps } from './provider';

// ── HTTP ──────────────────────────────────────────────────────────────────────
export {
  CoreHttpClient,
  CoreHttpProvider,
  createCoreHttpClient,
  useCoreHttpClient,
} from './http';
export type {
  CoreHttpClientConfig,
  CoreHttpError,
  CoreHttpErrorData,
  CoreHttpProviderProps,
  CoreHttpResponse,
  CoreRequestConfig,
  CoreResponseType,
} from './http';

// ── Runtime ───────────────────────────────────────────────────────────────────
export { CoreRuntimeProvider, useCoreRuntime } from './runtime';
export type { CoreNotificationType, CoreRuntime, CoreRuntimeProviderProps } from './runtime';

// ── Mercure (real-time) ───────────────────────────────────────────────────────
export {
  MercureProvider,
  MercureManager,
  useMercureHub,
  useMercureSubscription,
} from './mercure';
export type { MercureProviderProps, MercureManagerType } from './mercure';
