import { useQuery } from '@tanstack/react-query';
import { parseHydraDoc } from './openApiParser';
import type { HydraApiDoc } from './types';

const DOCS_URL = '/api/docs.jsonld';
const isDev = import.meta.env.DEV;

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

export interface UseHydraMetadataResult {
  doc: HydraApiDoc | undefined;
  isLoading: boolean;
  error: Error | undefined;
}

export function useHydraMetadata(): UseHydraMetadataResult {
  const { data, error, isPending } = useQuery<HydraApiDoc, Error>({
    queryKey: [DOCS_URL],
    queryFn: () => fetchHydraDoc(DOCS_URL),
    staleTime: isDev ? 0 : 5 * 60_000, // 0 in dev, 5 min in prod
    refetchOnWindowFocus: isDev,
    refetchOnReconnect: isDev,
  });

  return { doc: data, isLoading: isPending, error: error ?? undefined };
}

// Re-export parseHydraDoc so consumers can use it with the raw doc
export { parseHydraDoc };
