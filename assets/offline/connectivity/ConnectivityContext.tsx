import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

type ConnectivityStatus = 'online' | 'offline' | 'checking';

interface ConnectivityContextValue {
  browserOnline: boolean;
  apiReachable: boolean;
  isOnline: boolean;
  status: ConnectivityStatus;
  lastCheckedAt: string | null;
  pingNow: () => Promise<boolean>;
}

const ConnectivityContext = createContext<ConnectivityContextValue | null>(null);

const pingBackend = async () => {
  const abortController = new AbortController();
  const timeout = window.setTimeout(() => abortController.abort(), 4000);
  try {
    const response = await fetch('/health', {
      method: 'GET',
      cache: 'no-store',
      credentials: 'include',
      signal: abortController.signal,
    });
    return response.ok;
  } catch {
    return false;
  } finally {
    clearTimeout(timeout);
  }
};

export const ConnectivityProvider = ({ children }: { children: React.ReactNode }) => {
  const [browserOnline, setBrowserOnline] = useState<boolean>(navigator.onLine);
  const [apiReachable, setApiReachable] = useState<boolean>(navigator.onLine);
  const [status, setStatus] = useState<ConnectivityStatus>(navigator.onLine ? 'checking' : 'offline');
  const [lastCheckedAt, setLastCheckedAt] = useState<string | null>(null);

  const pingNow = useCallback(async () => {
    setStatus('checking');
    const reachable = await pingBackend();
    setApiReachable(reachable);
    setStatus(reachable ? 'online' : 'offline');
    setLastCheckedAt(new Date().toISOString());
    return reachable;
  }, []);

  useEffect(() => {
    const handleOnline = async () => {
      setBrowserOnline(true);
      await pingNow();
    };
    const handleOffline = () => {
      setBrowserOnline(false);
      setApiReachable(false);
      setStatus('offline');
      setLastCheckedAt(new Date().toISOString());
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (navigator.onLine) {
      void pingNow();
    } else {
      handleOffline();
    }

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [pingNow]);

  useEffect(() => {
    if (!browserOnline) {
      return;
    }

    const interval = window.setInterval(() => {
      void pingNow();
    }, 20000);

    return () => {
      window.clearInterval(interval);
    };
  }, [browserOnline, pingNow]);

  const value = useMemo<ConnectivityContextValue>(
    () => ({
      browserOnline,
      apiReachable,
      isOnline: browserOnline && apiReachable,
      status,
      lastCheckedAt,
      pingNow,
    }),
    [apiReachable, browserOnline, lastCheckedAt, pingNow, status],
  );

  return <ConnectivityContext.Provider value={value}>{children}</ConnectivityContext.Provider>;
};

export const useConnectivity = () => {
  const context = useContext(ConnectivityContext);
  if (!context) {
    throw new Error('useConnectivity debe usarse dentro de ConnectivityProvider');
  }
  return context;
};
