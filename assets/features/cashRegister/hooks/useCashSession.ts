import { useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useSessionInfo } from '@shared/sessionInfo';
import {
  cashSessionService,
  type CashPosState,
  type CashBusyRegister,
  type CashRegisterOption,
  type CashSessionInfo,
} from '../services/cashSessionService';

/** Persisted for offline start by `offline/posBootstrap`; keep the two names in step. */
export const CASH_POS_STATE_QUERY_KEY = 'cash-pos-state';

export interface UseCashSession {
  /** The session the signed-in user has open, or null. Only the server decides this. */
  session: CashSessionInfo | null;
  availableRegisters: CashRegisterOption[];
  /** Registers somebody else has open: whom the cashier has to wait for. */
  busyRegisters: CashBusyRegister[];
  /** First answer still pending and nothing known yet: do not claim open or closed. */
  isLoading: boolean;
  /** The last refresh failed; `session` is the last answer the server gave, if any. */
  isStale: boolean;
  refresh: () => Promise<void>;
}

/**
 * The user's cash session as the server sees it. It replaces a localStorage flag that defaulted to
 * "open" and let a cashier sell on a register someone else had opened. Revalidates on focus and on
 * reconnect, so closing or opening from another screen is picked up without a reload.
 */
export function useCashSession(): UseCashSession {
  const { currentUserId, currentBranchId } = useSessionInfo();
  const queryClient = useQueryClient();
  const queryKey = [CASH_POS_STATE_QUERY_KEY, currentUserId ?? null, currentBranchId];

  const query = useQuery<CashPosState, Error>({
    queryKey,
    queryFn: () => cashSessionService.getPosState(),
    staleTime: 0,
    refetchOnWindowFocus: true,
    refetchOnReconnect: true,
  });

  const refresh = useCallback(async () => {
    await queryClient.invalidateQueries({ queryKey: [CASH_POS_STATE_QUERY_KEY] });
  }, [queryClient]);

  return {
    session: query.data?.session ?? null,
    availableRegisters: query.data?.availableRegisters ?? [],
    busyRegisters: query.data?.busyRegisters ?? [],
    isLoading: query.data === undefined && !query.isError,
    isStale: query.isError,
    refresh,
  };
}
