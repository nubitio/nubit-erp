import { useCallback, useEffect, useRef } from 'react';
import { getCoreApiBaseUrl, useMercureSubscription } from '@nubitio/core';

const API = getCoreApiBaseUrl();

/** Several events in a row (a price import, a bulk tariff save) become one refresh. */
export const PRICE_SYNC_DEBOUNCE_MS = 1_500;
/**
 * Backup when Mercure is down or an event is lost. The catalogue is large, so this
 * is deliberately slow: Mercure delivers within a second or two when it works.
 */
export const PRICE_SYNC_POLL_MS = 120_000;

/**
 * Keeps the till's prices current while it stays open all shift.
 *
 *  - Mercure: a product, or a price list, changed → refresh shortly.
 *  - Polling: every PRICE_SYNC_POLL_MS while the tab is visible and online.
 *  - Coming back to the tab or reconnecting refreshes at once (the polling timer is
 *    throttled by browsers on hidden tabs, and a till may have been offline).
 */
export function usePosPriceSync(refresh: () => void, enabled = true): void {
  const refreshRef = useRef(refresh);
  useEffect(() => {
    refreshRef.current = refresh;
  }, [refresh]);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const schedule = useCallback(() => {
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => {
      timerRef.current = null;
      refreshRef.current();
    }, PRICE_SYNC_DEBOUNCE_MS);
  }, []);

  useMercureSubscription(`${API}products`, schedule, enabled);
  useMercureSubscription(`${API}price-lists`, schedule, enabled);

  useEffect(() => {
    if (!enabled) return;

    const refreshIfAvailable = () => {
      if (document.visibilityState === 'hidden') return;
      if (typeof navigator !== 'undefined' && navigator.onLine === false) return;
      refreshRef.current();
    };
    const onVisibility = () => {
      if (document.visibilityState === 'visible') refreshIfAvailable();
    };

    const interval = setInterval(refreshIfAvailable, PRICE_SYNC_POLL_MS);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('online', refreshIfAvailable);

    return () => {
      clearInterval(interval);
      document.removeEventListener('visibilitychange', onVisibility);
      window.removeEventListener('online', refreshIfAvailable);
      if (timerRef.current) clearTimeout(timerRef.current);
      timerRef.current = null;
    };
  }, [enabled]);
}
