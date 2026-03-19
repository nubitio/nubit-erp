// @vitest-environment jsdom
/**
 * Unit tests for MercureManager — SLICE-014 T014-7
 *
 * Coverage:
 *   - subscribe with null hubUrl → no-op (AC-8 graceful degradation)
 *   - subscribe with valid hubUrl → creates EventSource with encoded topic
 *   - subscribe same topic twice → reuses EventSource (ref-counting)
 *   - unsubscribe when count > 1 → does NOT close EventSource
 *   - unsubscribe when count = 1 → closes EventSource and removes from map (AC-7)
 *   - setHubUrl idempotent → does NOT notify listeners if value unchanged
 *   - onHubUrlChange → callback fired when URL changes
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// ---------------------------------------------------------------------------
// MockEventSource — simulates browser EventSource
// ---------------------------------------------------------------------------

class MockEventSource {
  static instances: MockEventSource[] = [];

  url: string;
  readyState = 1; // OPEN
  private readonly listenerMap = new Map<string, ((e: MessageEvent) => void)[]>();

  constructor(url: string) {
    this.url = url;
    MockEventSource.instances.push(this);
  }

  addEventListener(type: string, listener: (e: MessageEvent) => void): void {
    if (!this.listenerMap.has(type)) this.listenerMap.set(type, []);
    this.listenerMap.get(type)!.push(listener);
  }

  removeEventListener(type: string, listener: (e: MessageEvent) => void): void {
    const arr = this.listenerMap.get(type) ?? [];
    this.listenerMap.set(
      type,
      arr.filter((l) => l !== listener),
    );
  }

  close(): void {
    this.readyState = 2; // CLOSED
  }

  /** Helper: simulate an incoming SSE message */
  emit(data: unknown): void {
    const event = new MessageEvent('message', { data: JSON.stringify(data) });
    this.listenerMap.get('message')?.forEach((l) => l(event));
  }
}

// ---------------------------------------------------------------------------
// Setup / teardown
// ---------------------------------------------------------------------------

// We need a fresh MercureManager instance for each test to avoid state leakage.
// Since MercureManager exports a singleton, we re-import the class via the
// named export `MercureManager` (the type) and instantiate it directly.
// The module also exports the class type — we use it to create isolated instances.

// Import the class type for direct instantiation
import type { MercureManager as MercureManagerType } from './MercureManager';

let manager: MercureManagerType;

beforeEach(async () => {
  // Reset MockEventSource instances list
  MockEventSource.instances = [];

  // Stub global EventSource with our mock
  vi.stubGlobal('EventSource', MockEventSource);

  // Dynamically import a fresh instance of the class.
  // We bypass the singleton by importing the class constructor directly.
  // Since the module only exports the singleton as default, we instantiate
  // the class via a workaround: re-import the module and use the class.
  const mod = await import('./MercureManager');
  // The module exports `MercureManager` as a type and `mercureManager` as default.
  // We need to create a fresh instance — we do this by accessing the class
  // through the module's internal structure.
  // Since the class is not exported as a value, we create a fresh manager
  // by resetting the singleton's internal state via its public API.
  manager = mod.default as unknown as MercureManagerType;

  // Reset internal state: set hubUrl to null (idempotent if already null)
  // We call setHubUrl with a dummy value first to force the change, then null.
  (manager as any).hubUrl = null;
  (manager as any).topics.clear();
  (manager as any).hubUrlListeners.clear();
});

afterEach(() => {
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('MercureManager › subscribe', () => {
  it('with hubUrl null → does NOT create an EventSource (AC-8 graceful degradation)', () => {
    // hubUrl is null by default after reset
    const callback = vi.fn();
    manager.subscribe('https://host/api/products/{id}', callback);

    expect(MockEventSource.instances).toHaveLength(0);
  });

  it('with valid hubUrl → creates EventSource with correctly encoded topic URL', () => {
    manager.setHubUrl('https://hub.example.com/.well-known/mercure');

    const topic = 'https://host/api/products/{id}';
    const callback = vi.fn();
    manager.subscribe(topic, callback);

    expect(MockEventSource.instances).toHaveLength(1);

    const createdUrl = MockEventSource.instances[0].url;
    const parsed = new URL(createdUrl);
    expect(parsed.origin + parsed.pathname).toBe('https://hub.example.com/.well-known/mercure');
    expect(parsed.searchParams.get('topic')).toBe(topic);
  });

  it('subscribing same topic twice → reuses the same EventSource (ref-counting)', () => {
    manager.setHubUrl('https://hub.example.com/.well-known/mercure');

    const topic = 'https://host/api/products/{id}';
    const cb1 = vi.fn();
    const cb2 = vi.fn();

    manager.subscribe(topic, cb1);
    manager.subscribe(topic, cb2);

    // Only one EventSource should have been created
    expect(MockEventSource.instances).toHaveLength(1);

    // Both callbacks should receive messages
    MockEventSource.instances[0].emit({ id: 1 });
    expect(cb1).toHaveBeenCalledTimes(1);
    expect(cb2).toHaveBeenCalledTimes(1);
  });

  it('callback receives parsed JSON payload from SSE message', () => {
    manager.setHubUrl('https://hub.example.com/.well-known/mercure');

    const topic = 'https://host/api/products/{id}';
    const callback = vi.fn();
    manager.subscribe(topic, callback);

    const payload = { '@id': '/api/products/1', name: 'Widget' };
    MockEventSource.instances[0].emit(payload);

    expect(callback).toHaveBeenCalledWith(payload);
  });
});

describe('MercureManager › unsubscribe', () => {
  it('when count > 1 → does NOT close the EventSource', () => {
    manager.setHubUrl('https://hub.example.com/.well-known/mercure');

    const topic = 'https://host/api/products/{id}';
    const cb1 = vi.fn();
    const cb2 = vi.fn();

    manager.subscribe(topic, cb1);
    manager.subscribe(topic, cb2);

    // Unsubscribe only cb1 — count goes from 2 to 1
    manager.unsubscribe(topic, cb1);

    // EventSource must still be open
    expect(MockEventSource.instances[0].readyState).toBe(1); // OPEN

    // cb2 should still receive messages
    MockEventSource.instances[0].emit({ id: 2 });
    expect(cb2).toHaveBeenCalledTimes(1);
    expect(cb1).not.toHaveBeenCalled(); // removed
  });

  it('when count = 1 → closes the EventSource and removes it from the map (AC-7)', () => {
    manager.setHubUrl('https://hub.example.com/.well-known/mercure');

    const topic = 'https://host/api/products/{id}';
    const callback = vi.fn();

    manager.subscribe(topic, callback);
    expect(MockEventSource.instances[0].readyState).toBe(1); // OPEN

    manager.unsubscribe(topic, callback);

    // EventSource must be closed
    expect(MockEventSource.instances[0].readyState).toBe(2); // CLOSED

    // Internal map must be empty — subscribing again creates a new EventSource
    const cb2 = vi.fn();
    manager.subscribe(topic, cb2);
    expect(MockEventSource.instances).toHaveLength(2);
  });

  it('unsubscribe on unknown topic → no-op (no error thrown)', () => {
    expect(() => {
      manager.unsubscribe('https://host/api/unknown/{id}', vi.fn());
    }).not.toThrow();
  });
});

describe('MercureManager › setHubUrl / onHubUrlChange', () => {
  it('setHubUrl is idempotent → does NOT notify listeners if value unchanged', () => {
    const listener = vi.fn();
    manager.onHubUrlChange(listener);

    manager.setHubUrl('https://hub.example.com/.well-known/mercure');
    expect(listener).toHaveBeenCalledTimes(1);

    // Set the same URL again — should NOT trigger listener
    manager.setHubUrl('https://hub.example.com/.well-known/mercure');
    expect(listener).toHaveBeenCalledTimes(1); // still 1
  });

  it('onHubUrlChange → callback is called when URL changes', () => {
    const listener = vi.fn();
    manager.onHubUrlChange(listener);

    manager.setHubUrl('https://hub.example.com/.well-known/mercure');
    expect(listener).toHaveBeenCalledWith('https://hub.example.com/.well-known/mercure');

    manager.setHubUrl(null);
    expect(listener).toHaveBeenCalledWith(null);

    expect(listener).toHaveBeenCalledTimes(2);
  });

  it('onHubUrlChange returns an unsubscribe function that stops notifications', () => {
    const listener = vi.fn();
    const unsubscribe = manager.onHubUrlChange(listener);

    manager.setHubUrl('https://hub.example.com/.well-known/mercure');
    expect(listener).toHaveBeenCalledTimes(1);

    unsubscribe();

    manager.setHubUrl(null);
    // Listener must NOT be called after unsubscribe
    expect(listener).toHaveBeenCalledTimes(1);
  });

  it('getHubUrl returns the current hub URL', () => {
    expect(manager.getHubUrl()).toBeNull();

    manager.setHubUrl('https://hub.example.com/.well-known/mercure');
    expect(manager.getHubUrl()).toBe('https://hub.example.com/.well-known/mercure');
  });
});
