/**
 * useTenantIgvRate
 *
 * Fetches and caches the tenant-configured IGV tax rate from /api/tenant/settings.
 *
 * The rate is stored as a whole-number percentage on the backend (e.g. 18 or 10.5).
 * This hook converts it to the decimal fraction form required by calculation functions
 * (e.g. 0.18 or 0.105).
 *
 * OFFLINE BEHAVIOUR
 * ─────────────────
 * The last successfully fetched rate is persisted to localStorage under
 * `efact_tenant_igv_rate`. On a subsequent page load with no network the
 * persisted value is used immediately as `initialData` and React Query schedules
 * a background re-fetch once connectivity is restored.
 *
 * For retailers at the standard 18% rate this is a no-op (the localStorage value
 * equals the fallback constant). For restaurants at 10.5% the correct rate is
 * available from the very first render, even offline.
 *
 * FALLBACK ORDER
 * ──────────────
 * 1. localStorage persisted value (from previous session, available instantly)
 * 2. React Query in-memory cache (same session, survives re-renders)
 * 3. Fresh fetch from /api/tenant/settings
 * 4. IGV_RATE constant (0.18) — only when all of the above are unavailable
 */

import { useQuery } from '@tanstack/react-query';
import { httpClient } from '@shared/api/HttpClient';
import { IGV_RATE } from '@shared/lib/saleCalculations';

type TaxRegime = 'GENERAL' | 'NRUS';

type TenantSettingsResponse = {
  igv: number;
  taxRegime: TaxRegime;
};

type FetchedTenantRates = {
  igvRate: number;
  taxRegime: TaxRegime;
};

// ── localStorage persistence ──────────────────────────────────────────────────

const STORAGE_KEY = 'efact_tenant_igv_rate';
const TAX_REGIME_STORAGE_KEY = 'efact_tenant_tax_regime';

/** Maximum sensible tax rate: 50%. Guards against corrupted storage values. */
const MAX_RATE = 0.5;

function readPersistedRate(): number | undefined {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === null) return undefined;
    const parsed = parseFloat(raw);
    // Sanity-check: must be a positive decimal fraction ≤ MAX_RATE.
    return isFinite(parsed) && parsed > 0 && parsed <= MAX_RATE ? parsed : undefined;
  } catch {
    return undefined;
  }
}

function persistRate(rate: number): void {
  try {
    localStorage.setItem(STORAGE_KEY, String(rate));
  } catch {
    // Storage quota exceeded — silently ignore; the in-memory cache still works.
  }
}

function readPersistedTaxRegime(): TaxRegime | undefined {
  try {
    const raw = localStorage.getItem(TAX_REGIME_STORAGE_KEY);
    return raw === 'GENERAL' || raw === 'NRUS' ? raw : undefined;
  } catch {
    return undefined;
  }
}

function persistTaxRegime(taxRegime: TaxRegime): void {
  try {
    localStorage.setItem(TAX_REGIME_STORAGE_KEY, taxRegime);
  } catch {
    // Storage quota exceeded — silently ignore; the in-memory cache still works.
  }
}

// ── Query key ─────────────────────────────────────────────────────────────────

export const TENANT_IGV_RATE_QUERY_KEY = ['tenant', 'settings', 'igv-rate'] as const;

// ── Hook ──────────────────────────────────────────────────────────────────────

export interface UseTenantIgvRateResult {
  /**
   * The tenant's configured IGV rate as a decimal fraction (e.g. 0.18 or 0.105).
   * Resolved from localStorage → React Query cache → fresh fetch → IGV_RATE fallback.
   */
  igvRate: number;
  /**
   * The tenant's tax regime. NRUS taxpayers can only issue exonerated Boletas
   * with zero IGV. Defaults to 'GENERAL' until the first fetch resolves.
   */
  taxRegime: TaxRegime;
  /** True only during the very first network fetch (localStorage value not yet available). */
  isLoading: boolean;
  /** True after the rate has been confirmed from the server at least once. */
  isFetched: boolean;
}

export function useTenantIgvRate(): UseTenantIgvRateResult {
  const persistedRate = readPersistedRate();
  const persistedTaxRegime = readPersistedTaxRegime();
  const persistedData: FetchedTenantRates | undefined =
    persistedRate !== undefined && persistedTaxRegime !== undefined
      ? { igvRate: persistedRate, taxRegime: persistedTaxRegime }
      : undefined;

  const { data, isLoading, isFetched } = useQuery({
    queryKey: TENANT_IGV_RATE_QUERY_KEY,
    queryFn: async (): Promise<FetchedTenantRates> => {
      const response = await httpClient.get<TenantSettingsResponse>('/api/tenant/settings');
      // Backend stores as a whole-number percentage (e.g. 18 or 10.5).
      // Convert to decimal fraction for all calculation functions.
      const taxRegime = response.data.taxRegime;
      const igvRate = taxRegime === 'NRUS' ? 0 : response.data.igv / 100;
      persistRate(igvRate);
      persistTaxRegime(taxRegime);
      return { igvRate, taxRegime };
    },
    // Use the persisted value as immediately-available initial data.
    // Setting initialDataUpdatedAt to 0 (epoch) marks it as stale so React Query
    // schedules a background re-fetch on mount — ensuring the value stays fresh.
    initialData: persistedData,
    initialDataUpdatedAt: persistedData !== undefined ? 0 : undefined,
    staleTime: 10 * 60 * 1000, // 10 minutes — rate changes only via admin configuration
    gcTime: 30 * 60 * 1000, // 30 minutes — keep in memory for the full session
    retry: 2,
    retryDelay: 1000,
  });

  return {
    igvRate: data?.igvRate ?? IGV_RATE,
    taxRegime: data?.taxRegime ?? 'GENERAL',
    isLoading: isLoading && persistedData === undefined,
    isFetched,
  };
}
