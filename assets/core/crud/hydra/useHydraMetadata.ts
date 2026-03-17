import { useQuery } from '@tanstack/react-query';
import { parseHydraDoc, parseOpenApiDoc } from './openApiParser';
import type { HydraApiDoc, OpenApiDoc, ApiDoc } from './types';

const JSONLD_URL = '/api/docs.jsonld';
const JSON_URL = '/api/docs.json';
const isDev = import.meta.env.DEV;

/**
 * Try to fetch and validate a Hydra JSON-LD doc.
 * Returns the typed HydraApiDoc on success, or throws on failure.
 */
async function fetchHydraDoc(url: string): Promise<HydraApiDoc> {
  const res = await fetch(url, isDev ? {} : { cache: 'force-cache' });
  if (!res.ok) throw new Error(`Failed to load Hydra API doc: ${res.status} ${res.statusText}`);
  const json = (await res.json()) as HydraApiDoc;
  // Validate that it looks like a Hydra ApiDocumentation
  if (json['@type'] !== 'ApiDocumentation' || !Array.isArray(json.supportedClass)) {
    throw new Error('useHydraMetadata: response is not a valid Hydra ApiDocumentation');
  }
  return json;
}

/**
 * Try to fetch and validate an OpenAPI 3.1 doc.
 * Returns the typed OpenApiDoc on success, or throws on failure.
 */
async function fetchOpenApiDoc(url: string): Promise<OpenApiDoc> {
  const res = await fetch(url, isDev ? {} : { cache: 'force-cache' });
  if (!res.ok) throw new Error(`Failed to load OpenAPI doc: ${res.status} ${res.statusText}`);
  const json = (await res.json()) as OpenApiDoc;
  // Validate that it looks like an OpenAPI 3.x doc
  if (typeof json.openapi !== 'string' || !json.openapi.startsWith('3')) {
    throw new Error('useHydraMetadata: response is not a valid OpenAPI 3.x doc');
  }
  return json;
}

/**
 * Waterfall discovery:
 *   1. Try /api/docs.jsonld (prefer Hydra — richer type info)
 *   2. Fall back to /api/docs.json (OpenAPI 3.1)
 *   3. Return null if both fail
 */
async function fetchApiDoc(): Promise<ApiDoc | null> {
  // 1. Try Hydra JSON-LD first
  try {
    const doc = await fetchHydraDoc(JSONLD_URL);
    return { format: 'hydra', doc };
  } catch {
    // fall through to OpenAPI
  }

  // 2. Fall back to OpenAPI JSON
  try {
    const doc = await fetchOpenApiDoc(JSON_URL);
    return { format: 'openapi', doc };
  } catch {
    // both failed
  }

  return null;
}

export interface UseHydraMetadataResult {
  data: ApiDoc | null | undefined;
  isLoading: boolean;
  error: Error | undefined;
}

export function useHydraMetadata(): UseHydraMetadataResult {
  const { data, error, isPending } = useQuery<ApiDoc | null, Error>({
    queryKey: ['api-doc-discovery'],
    queryFn: fetchApiDoc,
    staleTime: isDev ? 0 : 5 * 60_000, // 0 in dev, 5 min in prod
    refetchOnWindowFocus: isDev,
    refetchOnReconnect: isDev,
  });

  return { data, isLoading: isPending, error: error ?? undefined };
}

// Re-export parsers so consumers can use them with the raw docs
export { parseHydraDoc, parseOpenApiDoc };
