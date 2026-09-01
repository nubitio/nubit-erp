import { useMemo } from 'react';

import { useDashboardOverview } from '../hooks/useDashboardOverview';
import { mapEfactDashboardPayload, type EfactDeclarativeDashboardPayload } from './mapEfactDashboardPayload';

export type EfactDashboardResult = {
  /** The mapper always returns a fully-populated payload (zeros / empty arrays). */
  data: EfactDeclarativeDashboardPayload;
  loading: boolean;
  error: string | null;
  refetch: () => void;
};

export function useEfactDashboardData(): EfactDashboardResult {
  const { data, loading, error, refetch } = useDashboardOverview();

  const payload = useMemo(() => mapEfactDashboardPayload(data), [data]);

  return { data: payload, loading, error, refetch };
}
