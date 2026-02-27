import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useConnectivity } from '@offline/connectivity/ConnectivityContext';
import { getPendingSalesCount, getRecentSales, processOutboxQueue, retrySaleSync } from '@offline/outbox';
import type { LocalSale } from '@offline/types';

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

  useEffect(() => {
    const interval = window.setInterval(() => {
      if (isOnline) {
        void processNow();
      } else {
        void refresh();
      }
    }, 5000);

    return () => {
      window.clearInterval(interval);
    };
  }, [isOnline, processNow, refresh]);

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
