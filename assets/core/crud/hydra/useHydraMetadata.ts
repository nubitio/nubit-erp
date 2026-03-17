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
  const jsonType = json['@type'] as string;
  if (
    (jsonType !== 'ApiDocumentation' && jsonType !== 'hydra:ApiDocumentation') ||
    !Array.isArray(json['supportedClass'])
  ) {
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
 * Stable query key used by useHydraMetadata.
 * Export this so that consumers (e.g. SmartCrudPage retry button) can
 * invalidate exactly the right query without coupling to an internal string.
 */
export const API_DOC_QUERY_KEY = ['api-doc-discovery'] as const;

/**
 * Waterfall discovery:
 *   1. Try /api/docs.jsonld (prefer Hydra — richer type info)
 *   2. Fall back to /api/docs.json (OpenAPI 3.1)
 *   3. Throw an Error if both fail so React Query sets isError = true
 */
async function fetchApiDoc(): Promise<ApiDoc> {
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
    // both failed — fall through to throw
  }

  // Both endpoints failed: throw so React Query sets isError = true
  throw new Error(
    `Failed to load API documentation from ${JSONLD_URL} and ${JSON_URL}. ` +
      'Make sure the API server is running.',
  );
}

export interface UseHydraMetadataResult {
  data: ApiDoc | undefined;
  isLoading: boolean;
  error: Error | undefined;
}

export function useHydraMetadata(): UseHydraMetadataResult {
  const { data, error, isPending } = useQuery<ApiDoc, Error>({
    queryKey: API_DOC_QUERY_KEY,
    queryFn: fetchApiDoc,
    staleTime: isDev ? 0 : 5 * 60_000, // 0 in dev, 5 min in prod
    refetchOnWindowFocus: isDev,
    refetchOnReconnect: isDev,
  });

  return { data, isLoading: isPending, error: error ?? undefined };
}

export { parseHydraDoc, parseOpenApiDoc };
