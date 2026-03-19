// @vitest-environment jsdom
/**
 * Unit tests for useMercureSubscription — SLICE-014 T014-7
 *
 * Coverage:
 *   - With hubUrl null → does NOT call MercureManager.subscribe (AC-8)
 *   - With enabled=false → does NOT call MercureManager.subscribe (AC-10)
 *   - With valid hubUrl and enabled → calls subscribe with correct wildcard topic (AC-5)
 *   - On unmount → calls unsubscribe (cleanup — AC-7)
 *   - When SSE event arrives → onUpdate callback is invoked (AC-6)
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Hoist mock factories so they are available before vi.mock() calls
// ---------------------------------------------------------------------------

const { mockSubscribe, mockUnsubscribe } = vi.hoisted(() => ({
  mockSubscribe: vi.fn(),
  mockUnsubscribe: vi.fn(),
}));

// ---------------------------------------------------------------------------
// Mock MercureManager before importing the hook
// ---------------------------------------------------------------------------

vi.mock('./MercureManager', () => ({
  default: {
    subscribe: mockSubscribe,
    unsubscribe: mockUnsubscribe,
    setHubUrl: vi.fn(),
    getHubUrl: vi.fn(),
    onHubUrlChange: vi.fn(() => () => {}),
  },
}));

// ---------------------------------------------------------------------------
// Mock useMercureHub so we can control the returned hubUrl
// ---------------------------------------------------------------------------

const { getMockHubUrl, setMockHubUrl } = vi.hoisted(() => {
  let _hubUrl: string | null = null;
  return {
    getMockHubUrl: () => _hubUrl,
    setMockHubUrl: (url: string | null) => {
      _hubUrl = url;
    },
  };
});

vi.mock('./useMercureHub', () => ({
  useMercureHub: () => getMockHubUrl(),
}));

// ---------------------------------------------------------------------------
// Import hook AFTER mocks are set up
// ---------------------------------------------------------------------------

import { useMercureSubscription } from './useMercureSubscription';

// ---------------------------------------------------------------------------
// Setup / teardown
// ---------------------------------------------------------------------------

beforeEach(() => {
  setMockHubUrl(null);
  mockSubscribe.mockReset();
  mockUnsubscribe.mockReset();

  // Provide a stable window.location.origin for topic building
  Object.defineProperty(window, 'location', {
    value: { origin: 'https://app.example.com' },
    writable: true,
  });
});

afterEach(() => {
  vi.clearAllMocks();
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useMercureSubscription', () => {
  it('with hubUrl null → does NOT call MercureManager.subscribe (AC-8 graceful degradation)', () => {
    setMockHubUrl(null);

    renderHook(() => useMercureSubscription('api/products', vi.fn()));

    expect(mockSubscribe).not.toHaveBeenCalled();
  });

  it('with enabled=false → does NOT call MercureManager.subscribe (AC-10 opt-out)', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    renderHook(() => useMercureSubscription('api/products', vi.fn(), false));

    expect(mockSubscribe).not.toHaveBeenCalled();
  });

  it('with valid hubUrl and enabled=true → calls subscribe with wildcard topic (AC-5)', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    renderHook(() => useMercureSubscription('api/products', vi.fn()));

    expect(mockSubscribe).toHaveBeenCalledTimes(1);

    const [calledTopic] = mockSubscribe.mock.calls[0] as [string, unknown];
    // Topic must be the wildcard URI Template for the collection
    expect(calledTopic).toBe('https://app.example.com/api/products/{id}');
  });

  it('strips leading slash from apiUrl when building topic', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    renderHook(() => useMercureSubscription('/api/products', vi.fn()));

    const [calledTopic] = mockSubscribe.mock.calls[0] as [string, unknown];
    // Must NOT produce double slash: https://app.example.com//api/products/{id}
    expect(calledTopic).toBe('https://app.example.com/api/products/{id}');
    expect(calledTopic).not.toContain('//api');
  });

  it('on unmount → calls unsubscribe with the same topic and handler (AC-7 cleanup)', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    const { unmount } = renderHook(() => useMercureSubscription('api/products', vi.fn()));

    expect(mockSubscribe).toHaveBeenCalledTimes(1);
    const [subscribedTopic, subscribedHandler] = mockSubscribe.mock.calls[0] as [
      string,
      (data: unknown) => void,
    ];

    unmount();

    expect(mockUnsubscribe).toHaveBeenCalledTimes(1);
    const [unsubscribedTopic, unsubscribedHandler] = mockUnsubscribe.mock.calls[0] as [
      string,
      (data: unknown) => void,
    ];

    // Must unsubscribe from the same topic with the same handler reference
    expect(unsubscribedTopic).toBe(subscribedTopic);
    expect(unsubscribedHandler).toBe(subscribedHandler);
  });

  it('when SSE event arrives → onUpdate callback is invoked (AC-6)', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    const onUpdate = vi.fn();
    renderHook(() => useMercureSubscription('api/products', onUpdate));

    // Simulate MercureManager calling the internal handler with SSE data
    const [, internalHandler] = mockSubscribe.mock.calls[0] as [string, (data: unknown) => void];

    act(() => {
      internalHandler({ '@id': '/api/products/1', name: 'Widget' });
    });

    expect(onUpdate).toHaveBeenCalledTimes(1);
  });

  it('re-subscribes when hubUrl changes from null to a valid URL', () => {
    setMockHubUrl(null);

    const onUpdate = vi.fn();
    const { rerender } = renderHook(() => useMercureSubscription('api/products', onUpdate));

    expect(mockSubscribe).not.toHaveBeenCalled();

    // Hub URL is discovered
    setMockHubUrl('https://hub.example.com/.well-known/mercure');
    rerender();

    expect(mockSubscribe).toHaveBeenCalledTimes(1);
  });

  it('does NOT subscribe when apiUrl is undefined', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    renderHook(() => useMercureSubscription(undefined, vi.fn()));

    expect(mockSubscribe).not.toHaveBeenCalled();
  });

  it('unsubscribes old topic and subscribes to new one when apiUrl changes', () => {
    setMockHubUrl('https://hub.example.com/.well-known/mercure');

    let apiUrl = 'api/products';
    const onUpdate = vi.fn();

    const { rerender } = renderHook(() => useMercureSubscription(apiUrl, onUpdate));

    expect(mockSubscribe).toHaveBeenCalledTimes(1);
    const firstTopic = (mockSubscribe.mock.calls[0] as [string, unknown])[0];
    expect(firstTopic).toBe('https://app.example.com/api/products/{id}');

    // Change apiUrl
    apiUrl = 'api/categories';
    rerender();

    // Old subscription cleaned up
    expect(mockUnsubscribe).toHaveBeenCalledTimes(1);
    const unsubscribedTopic = (mockUnsubscribe.mock.calls[0] as [string, unknown])[0];
    expect(unsubscribedTopic).toBe('https://app.example.com/api/products/{id}');

    // New subscription created
    expect(mockSubscribe).toHaveBeenCalledTimes(2);
    const secondTopic = (mockSubscribe.mock.calls[1] as [string, unknown])[0];
    expect(secondTopic).toBe('https://app.example.com/api/categories/{id}');
  });
});
