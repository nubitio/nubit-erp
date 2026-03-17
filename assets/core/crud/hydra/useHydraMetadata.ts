import { useQuery } from '@tanstack/react-query';
import { parseOpenApiSpec } from './openApiParser';
import type { HydraApiSpec } from './types';

const DOCS_URL = '/api/docs.json';
const isDev = import.meta.env.DEV;

async function fetchAndParse(url: string): Promise<HydraApiSpec> {
  const res = await fetch(url, isDev ? {} : { cache: 'force-cache' });
  if (!res.ok) throw new Error(`Failed to load API spec: ${res.status} ${res.statusText}`);
  const json = await res.json();
  return parseOpenApiSpec(json);
}

export interface UseHydraMetadataResult {
  spec: HydraApiSpec | undefined;
  isLoading: boolean;
  error: Error | undefined;
}

export function useHydraMetadata(): UseHydraMetadataResult {
  const { data, error, isPending } = useQuery<HydraApiSpec, Error>({
    queryKey: [DOCS_URL],
    queryFn: () => fetchAndParse(DOCS_URL),
    staleTime: isDev ? 30_000 : Infinity,
    refetchOnWindowFocus: isDev,
    refetchOnReconnect: isDev,
  });

  return { spec: data, isLoading: isPending, error: error ?? undefined };
}
