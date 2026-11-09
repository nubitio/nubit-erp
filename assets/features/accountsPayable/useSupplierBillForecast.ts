import { useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

export interface SupplierBillForecastData {
  asOf: string;
  horizonDays: number;
  currency: string;
  /** Open cash sessions of the branch. Bank balances are not available in the system. */
  cashBalance: string;
  expectedReceipts: string;
  payables: string;
  projectedBalance: string;
  overdueReceivables: string;
  overdueReceivablesCount: number;
}

export function useSupplierBillForecast() {
  return useQuery({
    queryKey: ['accounts-payable', 'forecast'],
    queryFn: async () =>
      (
        await httpClient.get<SupplierBillForecastData>(
          `${getCoreApiBaseUrl()}supplier-bills-forecast`,
          { errorPresentation: 'inline' },
        )
      ).data,
    retry: false,
  });
}
