import { useQuery } from '@tanstack/react-query';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { httpClient } from '@shared/api/HttpClient';

export interface SupplierBillCurrencySummary {
  currency: string;
  balanceDue: string;
  overdueTotal: string;
  dueWithinSevenDays: string;
  dueWithinThirtyDays: string;
  billCount: number;
  supplierCount: number;
}
export interface SupplierBillSummaryData {
  asOf: string;
  currencies: SupplierBillCurrencySummary[];
}
export function useSupplierBillSummary() {
  return useQuery({
    queryKey: ['accounts-payable', 'summary'],
    queryFn: async () =>
      (
        await httpClient.get<SupplierBillSummaryData>(
          `${getCoreApiBaseUrl()}supplier-bills-summary`,
          { errorPresentation: 'inline' },
        )
      ).data,
    retry: false,
  });
}
