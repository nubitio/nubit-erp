import { useQuery } from '@tanstack/react-query';
import { businessHttpClient, httpRequestData } from '@shared/api/network';
import type { DataRecord } from '@core/data/DataRecord';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface FeatureEntitlement {
  enabled: boolean;
  /** 'plan' when the value comes from plan_features; 'override' for tenant overrides. */
  source: 'plan' | 'override';
  config: DataRecord;
}

export interface EntitlementsResponse {
  plan: string;
  limits: Record<string, { limit: number; current: number }>;
  features: Record<string, FeatureEntitlement>;
}

// ---------------------------------------------------------------------------
// Query key — export so callers can invalidate manually if needed
// ---------------------------------------------------------------------------

export const ENTITLEMENTS_QUERY_KEY = ['tenant-entitlements'] as const;

// ---------------------------------------------------------------------------
// Fetcher
// ---------------------------------------------------------------------------

async function fetchEntitlements(): Promise<EntitlementsResponse> {
  return httpRequestData(
    businessHttpClient.get<EntitlementsResponse>('/api/tenant/entitlements'),
  );
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

/**
 * Fetches the active tenant's plan, resource limits, and feature flags from
 * GET /api/tenant/entitlements.
 *
 * - staleTime: 5 minutes  (plan changes are rare; avoids unnecessary requests)
 * - refetchOnWindowFocus: true  (picks up changes made in the admin panel while
 *   the user had the app open in another tab)
 *
 * Use the derived helpers `useFeature()` and `useFeatureConfig()` instead of
 * consuming this hook directly in most components.
 */
export function useEntitlements() {
  return useQuery<EntitlementsResponse, Error>({
    queryKey: [...ENTITLEMENTS_QUERY_KEY],
    queryFn: fetchEntitlements,
    staleTime: 5 * 60_000,
    refetchOnWindowFocus: true,
  });
}
