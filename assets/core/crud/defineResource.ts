import type { ResourceConfig } from './ResourceConfig';
import type { DataRecord } from '../data/DataRecord';

const stringResourceCache = new Map<string, ResourceConfig>();

function cacheKey(apiUrl: string, overrides?: DataRecord): string | null {
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
export function defineResource<T extends DataRecord>(
  apiUrl: string,
  overrides: Omit<ResourceConfig<T>, 'apiUrl'> & { id?: string },
): ResourceConfig<T>;

/** Overload 3 — Full config object (legacy / full control — retrocompatible). */
export function defineResource<T extends DataRecord = DataRecord>(
  config: ResourceConfig<T>,
): ResourceConfig<T>;

export function defineResource<T extends DataRecord = DataRecord>(
  configOrUrl: ResourceConfig<T> | string,
  overrides?: Omit<ResourceConfig<T>, 'apiUrl'> & { id?: string },
): ResourceConfig<T> {
  if (typeof configOrUrl === 'string') {
    const apiUrl = configOrUrl;
    const key = cacheKey(apiUrl, overrides as DataRecord | undefined);
    if (key !== null && stringResourceCache.has(key)) {
      return stringResourceCache.get(key)! as ResourceConfig<T>;
    }

    const id = overrides?.id ?? deriveId(apiUrl);
    const resource = {
      id,
      title: '',
      fields: [],
      ...overrides,
      apiUrl,
    } as ResourceConfig<T>;

    if (key !== null) {
      stringResourceCache.set(key, resource as ResourceConfig);
    }

    return resource;
  }
  // Legacy: pass-through
  return configOrUrl;
}
