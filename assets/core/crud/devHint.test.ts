// @vitest-environment jsdom

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import type { Field } from '../field/Field';

const stubFields: Field[] = [
  { name: 'name', label: 'Name', type: 'text', readonly: false } as unknown as Field,
  { name: 'price', label: 'Price', type: 'number', readonly: false } as unknown as Field,
];

async function importFreshDevHint() {
  // Force a fresh module import by using a cache-busting query string.
  // Vitest's vi.resetModules() + dynamic import gives us a fresh module state.
  vi.resetModules();
  const mod = await import('./devHint?t=' + Date.now());
  return mod.logDevHint;
}

/**
 * Helper to override window.location.hostname in jsdom.
 * jsdom does not allow direct assignment to location, so we redefine the
 * property on the window object.
 */
function stubHostname(hostname: string): () => void {
  const original = window.location;
  Object.defineProperty(window, 'location', {
    configurable: true,
    writable: true,
    value: { ...original, hostname },
  });
  return () => {
    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: original,
    });
  };
}

// ---------------------------------------------------------------------------
// DEV-environment suite
// jsdom defaults to hostname "localhost", so isDevEnvironment() returns true
// with no extra setup needed.
// ---------------------------------------------------------------------------
describe('logDevHint — local dev hostname (localhost)', () => {
  let consoleInfoSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
  });

  afterEach(() => {
    consoleInfoSpy.mockRestore();
  });

  it('calls console.info on localhost', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/test-resource', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(1);
  });

  it('prefixes the message with [SmartCRUD]', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/prefixed', stubFields);
    const [message] = consoleInfoSpy.mock.calls[0] as [string];
    expect(message).toMatch(/^\[SmartCRUD\]/);
  });

  it('includes the resource URL in the message', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/my-resource', stubFields);
    const [message] = consoleInfoSpy.mock.calls[0] as [string];
    expect(message).toContain('api/my-resource');
  });

  it('includes a defineResource call in the message', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/items', stubFields);
    const [message] = consoleInfoSpy.mock.calls[0] as [string];
    expect(message).toContain('defineResource');
  });

  it('logs each field name in the snippet', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/products', stubFields);
    const [message] = consoleInfoSpy.mock.calls[0] as [string];
    expect(message).toContain("key: 'name'");
    expect(message).toContain("key: 'price'");
  });

  it('logs every call regardless of URL (dedup delegated to SmartCrudPage hintFiredRef)', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/deduplicated', stubFields);
    logDevHint('api/deduplicated', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(2);
  });

  it('DOES log for different resource URLs (separate entries)', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/resource-a', stubFields);
    logDevHint('api/resource-b', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(2);
  });
});

// ---------------------------------------------------------------------------
// Additional local hostname variants
// ---------------------------------------------------------------------------
describe('logDevHint — other recognised local hostnames', () => {
  let consoleInfoSpy: ReturnType<typeof vi.spyOn>;
  let restoreHostname: () => void;

  afterEach(() => {
    consoleInfoSpy.mockRestore();
    restoreHostname?.();
  });

  it('fires on 127.0.0.1', async () => {
    restoreHostname = stubHostname('127.0.0.1');
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    const logDevHint = await importFreshDevHint();
    logDevHint('api/r', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(1);
  });

  it('fires on a *.localhost subdomain', async () => {
    restoreHostname = stubHostname('app.localhost');
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    const logDevHint = await importFreshDevHint();
    logDevHint('api/r', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(1);
  });

  it('fires on a 192.168.x.x LAN address', async () => {
    restoreHostname = stubHostname('192.168.1.100');
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    const logDevHint = await importFreshDevHint();
    logDevHint('api/r', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(1);
  });
});

// ---------------------------------------------------------------------------
// PRODUCTION / non-local hostname suite
// ---------------------------------------------------------------------------
describe('logDevHint — non-local hostname (production / staging)', () => {
  let consoleInfoSpy: ReturnType<typeof vi.spyOn>;
  let restoreHostname: () => void;

  beforeEach(() => {
    restoreHostname = stubHostname('app.example.com');
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
  });

  afterEach(() => {
    consoleInfoSpy.mockRestore();
    restoreHostname();
  });

  it('does NOT call console.info on a non-local hostname', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/production-resource', stubFields);
    expect(consoleInfoSpy).not.toHaveBeenCalled();
  });
});
