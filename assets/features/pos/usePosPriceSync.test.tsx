import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { PRICE_SYNC_DEBOUNCE_MS, PRICE_SYNC_POLL_MS, usePosPriceSync } from './usePosPriceSync';

const subscriptions = new Map<string, () => void>();

vi.mock('@nubitio/core', () => ({
  getCoreApiBaseUrl: () => '/api/',
  useMercureSubscription: (topic: string, onUpdate: () => void) => {
    subscriptions.set(topic, onUpdate);
  },
}));

const setVisibility = (state: 'visible' | 'hidden') =>
  Object.defineProperty(document, 'visibilityState', { value: state, configurable: true });

beforeEach(() => {
  vi.useFakeTimers();
  subscriptions.clear();
  setVisibility('visible');
});

afterEach(() => {
  vi.useRealTimers();
});

describe('usePosPriceSync', () => {
  it('refreshes once after a burst of product events', () => {
    const refresh = vi.fn();
    renderHook(() => usePosPriceSync(refresh));

    const onProduct = subscriptions.get('/api/products')!;
    act(() => {
      onProduct();
      onProduct();
      onProduct();
    });
    expect(refresh).not.toHaveBeenCalled();

    act(() => {
      vi.advanceTimersByTime(PRICE_SYNC_DEBOUNCE_MS);
    });
    expect(refresh).toHaveBeenCalledTimes(1);
  });

  it('refreshes when a price list changes', () => {
    const refresh = vi.fn();
    renderHook(() => usePosPriceSync(refresh));

    act(() => {
      subscriptions.get('/api/price-lists')!();
      vi.advanceTimersByTime(PRICE_SYNC_DEBOUNCE_MS);
    });
    expect(refresh).toHaveBeenCalledTimes(1);
  });

  it('polls as a backup while the tab is visible', () => {
    const refresh = vi.fn();
    renderHook(() => usePosPriceSync(refresh));

    act(() => {
      vi.advanceTimersByTime(PRICE_SYNC_POLL_MS);
    });
    expect(refresh).toHaveBeenCalledTimes(1);
  });

  it('does not poll a hidden tab, and refreshes when it becomes visible again', () => {
    const refresh = vi.fn();
    renderHook(() => usePosPriceSync(refresh));

    setVisibility('hidden');
    act(() => {
      vi.advanceTimersByTime(PRICE_SYNC_POLL_MS);
    });
    expect(refresh).not.toHaveBeenCalled();

    setVisibility('visible');
    act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
    });
    expect(refresh).toHaveBeenCalledTimes(1);
  });

  it('refreshes when the connection comes back', () => {
    const refresh = vi.fn();
    renderHook(() => usePosPriceSync(refresh));

    act(() => {
      window.dispatchEvent(new Event('online'));
    });
    expect(refresh).toHaveBeenCalledTimes(1);
  });

  it('does nothing while disabled (offline) and cleans up on unmount', () => {
    const refresh = vi.fn();
    const { unmount } = renderHook(() => usePosPriceSync(refresh, false));
    act(() => {
      vi.advanceTimersByTime(PRICE_SYNC_POLL_MS * 2);
      window.dispatchEvent(new Event('online'));
    });
    expect(refresh).not.toHaveBeenCalled();

    const enabled = renderHook(() => usePosPriceSync(refresh, true));
    enabled.unmount();
    unmount();
    act(() => {
      vi.advanceTimersByTime(PRICE_SYNC_POLL_MS * 2);
    });
    expect(refresh).not.toHaveBeenCalled();
  });
});
