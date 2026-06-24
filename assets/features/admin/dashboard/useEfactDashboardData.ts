import { useMemo } from 'react';
import type { DashboardDataResult } from '@nubitio/react-admin';

import { useDashboardOverview } from '../hooks/useDashboardOverview';
import { mapEfactDashboardPayload } from './mapEfactDashboardPayload';

export function useEfactDashboardData(): DashboardDataResult {
  const { data, loading, error, refetch } = useDashboardOverview();

  const payload = useMemo(() => mapEfactDashboardPayload(data), [data]);

  return {
    data: payload,
    loading,
    error,
    refetch,
  };
}