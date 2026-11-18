import { useQuery } from '@tanstack/react-query';
import { financialReportsApi } from './api/financialReportsEndpoints';

export const FINANCIAL_REPORTS_QUERY_KEYS = {
  profitAndLoss: (period?: string) => ['financial-reports', 'profit-and-loss', period] as const,
  cashFlow: (from?: string, to?: string, currency?: string) =>
    ['financial-reports', 'cash-flow', from, to, currency] as const,
};

export function useProfitAndLoss(period?: string) {
  return useQuery({
    queryKey: FINANCIAL_REPORTS_QUERY_KEYS.profitAndLoss(period),
    queryFn: () => financialReportsApi.profitAndLoss(period),
    staleTime: 30_000,
  });
}

export function useCashFlow(from?: string, to?: string, currency?: string) {
  return useQuery({
    queryKey: FINANCIAL_REPORTS_QUERY_KEYS.cashFlow(from, to, currency),
    queryFn: () => financialReportsApi.cashFlow(from, to, currency),
    staleTime: 30_000,
  });
}
