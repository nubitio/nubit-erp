import type { ResourceConfig } from './ResourceConfig';

/**
 * Derives the resource `id` from a URL string.
 * '/api/products'        → 'products'
 * '/api/voucher-series'  → 'voucher-series'
 * 'http://host/a/b/items' → 'items'
 */
function deriveId(apiUrl: string): string {
  const stripped = apiUrl.replace(/\/+$/, '');
  const last = stripped.split('/').pop() ?? stripped;
  // Strip query params if any
  return last.split('?')[0];
}

/**
 * Overload 1 — Zero config: derive id from URL.
 * `mercure` defaults to `true` (opt-in) when not specified.
 */
export function defineResource(apiUrl: string): ResourceConfig;

/**
 * Overload 2 — URL + partial overrides.
 * Pass `mercure: false` to disable real-time Mercure subscription for this resource.
 * When omitted, `mercure` is effectively `true` (opt-in by default).
 */
export function defineResource<T extends Record<string, unknown>>(
  apiUrl: string,
  overrides: Omit<ResourceConfig<T>, 'apiUrl'> & { id?: string },
): ResourceConfig<T>;

/** Overload 3 — Full config object (legacy / full control — retrocompatible). */
export function defineResource<T extends Record<string, unknown> = Record<string, unknown>>(
  config: ResourceConfig<T>,
): ResourceConfig<T>;

export function defineResource<T extends Record<string, unknown> = Record<string, unknown>>(
  configOrUrl: ResourceConfig<T> | string,
  overrides?: Omit<ResourceConfig<T>, 'apiUrl'> & { id?: string },
): ResourceConfig<T> {
  if (typeof configOrUrl === 'string') {
    const apiUrl = configOrUrl;
    const id = overrides?.id ?? deriveId(apiUrl);
    return {
      id,
      title: '',
      fields: [],
      ...overrides,
      apiUrl,
    } as ResourceConfig<T>;
  }
  // Legacy: pass-through
  return configOrUrl;
}
