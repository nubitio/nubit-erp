import { useQuery } from '@tanstack/react-query';
import { taxesApi } from './api/taxesEndpoints';

export const TAXES_QUERY_KEYS = {
  igvSummary: (period?: string) => ['taxes', 'igv-summary', period] as const,
  detracciones: (period?: string) => ['taxes', 'detracciones', period] as const,
  retenciones: (period?: string) => ['taxes', 'retenciones', period] as const,
};

export function useIgvSummary(period?: string) {
  return useQuery({
    queryKey: TAXES_QUERY_KEYS.igvSummary(period),
    queryFn: () => taxesApi.igvSummary(period),
    staleTime: 30_000,
  });
}

export function useDetracciones(period?: string) {
  return useQuery({
    queryKey: TAXES_QUERY_KEYS.detracciones(period),
    queryFn: () => taxesApi.detracciones(period),
    staleTime: 30_000,
  });
}

export function useRetencionesPercepciones(period?: string) {
  return useQuery({
    queryKey: TAXES_QUERY_KEYS.retenciones(period),
    queryFn: () => taxesApi.retencionesPercepciones(period),
    staleTime: 30_000,
  });
}
