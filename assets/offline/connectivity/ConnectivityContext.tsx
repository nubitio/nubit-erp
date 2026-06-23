import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { exceptionalFetch } from '@shared/api/network';

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
    const response = await exceptionalFetch(
      '/health/live',
      {
        method: 'GET',
        cache: 'no-store',
        credentials: 'include',
        signal: abortController.signal,
      },
      'browser-native-api',
    );
    return response.ok;
  } catch {
    return false;
  } finally {
    clearTimeout(timeout);
  }
};

export const ConnectivityProvider = ({ children }: { children: React.ReactNode }) => {
  // Start optimistically online and status 'checking' — navigator.onLine can be unreliable
  // in headless/Docker environments. The initial ping will correct the state within 4 seconds.
  const [browserOnline, setBrowserOnline] = useState<boolean>(true);
  const [apiReachable, setApiReachable] = useState<boolean>(true);
  const [status, setStatus] = useState<ConnectivityStatus>('checking');
  const [lastCheckedAt, setLastCheckedAt] = useState<string | null>(null);

  const pingNow = useCallback(async () => {
    setStatus('checking');
    const reachable = await pingBackend();
    if (reachable) {
      // A successful ping proves we have network — override any false navigator.onLine
      setBrowserOnline(true);
    }
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

    // Always ping on mount — navigator.onLine can be false in headless/Docker environments
    // even when the server is reachable. A successful ping will set browserOnline = true.
    // eslint-disable-next-line react-hooks/set-state-in-effect -- pingNow calls setState; initial connectivity check on mount is intentional
    void pingNow();

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
      if (document.visibilityState === 'visible') {
        void pingNow();
      }
    }, 60000);

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
