import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { cashboxService } from '../services/cashboxService';
import { summarizeRegisters } from '../model/registersSummary';

export const CASHBOX_DASHBOARD_KEY = 'cashbox-dashboard';
export const CASHBOX_SUMMARY_KEY = 'cashbox-summary';
export const CASHBOX_RECENT_KEY = 'cashbox-recent-shifts';

const REFRESH_MS = 60_000;

/** The registers of the branch with their open shift, refreshed every minute. */
export function useCashboxRegisters() {
  return useQuery({
    queryKey: [CASHBOX_DASHBOARD_KEY],
    queryFn: () => cashboxService.getDashboard().then((response) => response.data),
    refetchInterval: REFRESH_MS,
  });
}

/** The figures of the header: sales today and how the closings of the week turned out. */
export function useCashboxSummary(cashRegisterId?: number) {
  return useQuery({
    queryKey: [CASHBOX_SUMMARY_KEY, cashRegisterId ?? null],
    queryFn: () => cashboxService.getSummary(cashRegisterId),
    refetchInterval: REFRESH_MS,
  });
}

export function useRecentShifts(limit: number, page = 1, cashRegisterId?: number) {
  return useQuery({
    queryKey: [CASHBOX_RECENT_KEY, limit, page, cashRegisterId ?? null],
    queryFn: () => cashboxService.getRecentSessions(limit, page, cashRegisterId),
    placeholderData: keepPreviousData,
    refetchInterval: REFRESH_MS,
  });
}

/** Registers and the derived header figures, for the pages that show both. */
export function useCashboxOverview() {
  const registers = useCashboxRegisters();
  const summary = useCashboxSummary();
  const totals = summarizeRegisters(registers.data ?? []);

  return { registers, summary, totals };
}
