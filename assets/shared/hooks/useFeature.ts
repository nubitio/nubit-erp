import { useEntitlements } from './useEntitlements';
import type { DataRecord } from '@nubitio/core';

/**
 * Returns true when the active tenant's plan (or an active override) has the
 * given feature enabled. Returns false while loading or on error (fail-closed).
 *
 * @example
 *   const canExport = useFeature('excel_export');
 *   if (!canExport) return <LockedButton />;
 */
export function useFeature(featureKey: string): boolean {
  const { data } = useEntitlements();
  return data?.features[featureKey]?.enabled ?? false;
}

/**
 * Returns the config object for a feature (e.g. `{ days: 365 }` for
 * `retention_days`), or an empty object when the feature is disabled or has
 * no config. Returns empty object while loading (fail-closed).
 *
 * @example
 *   const { max } = useFeatureConfig('concurrent_sessions') as { max: number };
 */
export function useFeatureConfig(featureKey: string): DataRecord {
  const { data } = useEntitlements();
  const entry = data?.features[featureKey];
  if (!entry?.enabled) return {};
  return entry.config ?? {};
}

/**
 * Returns the name of the active plan, or null while loading.
 *
 * @example
 *   const plan = usePlan(); // 'free' | 'basic' | 'pro' | null
 */
export function usePlan(): string | null {
  const { data } = useEntitlements();
  return data?.plan ?? null;
}
