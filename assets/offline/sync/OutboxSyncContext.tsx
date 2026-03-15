import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import { useConnectivity } from '@offline/connectivity/ConnectivityContext';
import {
  getPendingSalesCount,
  getRecentSales,
  processOutboxQueue,
  retrySaleSync,
} from '@offline/outbox';
import type { LocalSale } from '@offline/types';

/**
 * Polling intervals for the sync loop.
 *
 * When there are pending/error sales and the device is online we poll
 * aggressively so the queue drains quickly after reconnection.
 * When the queue is empty we slow down to conserve battery — especially
 * important on low-end Android devices used by vendors in Peru.
 */
const POLL_INTERVAL_ACTIVE_MS = 10_000; // 10s  — queue has items, drain quickly
const POLL_INTERVAL_IDLE_MS = 60_000; // 60s  — queue empty, just keep watch

interface OutboxSyncContextValue {
  pendingSalesCount: number;
  isSyncing: boolean;
  recentSales: LocalSale[];
  refresh: () => Promise<void>;
  processNow: () => Promise<void>;
  retrySale: (saleId: string) => Promise<void>;
}

const OutboxSyncContext = createContext<OutboxSyncContextValue | null>(null);

export const OutboxSyncProvider = ({ children }: { children: React.ReactNode }) => {
  const { isOnline } = useConnectivity();
  const [pendingSalesCount, setPendingSalesCount] = useState(0);
  const [recentSales, setRecentSales] = useState<LocalSale[]>([]);
  const [isSyncing, setIsSyncing] = useState(false);

  const refresh = useCallback(async () => {
    const [pending, sales] = await Promise.all([getPendingSalesCount(), getRecentSales(30)]);
    setPendingSalesCount(pending);
    setRecentSales(sales);
  }, []);

  const processNow = useCallback(async () => {
    if (!isOnline) {
      await refresh();
      return;
    }
    setIsSyncing(true);
    try {
      await processOutboxQueue();
    } finally {
      setIsSyncing(false);
      await refresh();
    }
  }, [isOnline, refresh]);

  const retrySale = useCallback(
    async (saleId: string) => {
      setIsSyncing(true);
      try {
        await retrySaleSync(saleId);
      } finally {
        setIsSyncing(false);
        await refresh();
      }
    },
    [refresh],
  );

  useEffect(() => {
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (isOnline) {
      void processNow();
    }
  }, [isOnline, processNow]);

  // Adaptive polling: drain aggressively when there are pending sales,
  // back off to conserve battery when the queue is empty.
  const intervalRef = useRef<number | null>(null);

  const scheduleNextPoll = useCallback(() => {
    if (intervalRef.current !== null) {
      window.clearInterval(intervalRef.current);
    }
    const delay = pendingSalesCount > 0 ? POLL_INTERVAL_ACTIVE_MS : POLL_INTERVAL_IDLE_MS;
    intervalRef.current = window.setInterval(() => {
      if (isOnline) {
        void processNow();
      } else {
        void refresh();
      }
    }, delay);
  }, [isOnline, pendingSalesCount, processNow, refresh]);

  useEffect(() => {
    scheduleNextPoll();
    return () => {
      if (intervalRef.current !== null) {
        window.clearInterval(intervalRef.current);
      }
    };
  }, [scheduleNextPoll]);

  const value = useMemo(
    () => ({
      pendingSalesCount,
      isSyncing,
      recentSales,
      refresh,
      processNow,
      retrySale,
    }),
    [isSyncing, pendingSalesCount, processNow, recentSales, refresh, retrySale],
  );

  return <OutboxSyncContext.Provider value={value}>{children}</OutboxSyncContext.Provider>;
};

export const useOutboxSync = () => {
  const context = useContext(OutboxSyncContext);
  if (!context) {
    throw new Error('useOutboxSync debe usarse dentro de OutboxSyncProvider');
  }
  return context;
};
