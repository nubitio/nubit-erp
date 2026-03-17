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

describe('logDevHint — DEV mode', () => {
  let consoleInfoSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    vi.stubEnv('DEV', true);
  });

  afterEach(() => {
    consoleInfoSpy.mockRestore();
    vi.unstubAllEnvs();
  });

  it('calls console.info when DEV=true', async () => {
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

  it('does NOT log a second time for the same resource URL (dedup)', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/deduplicated', stubFields);
    logDevHint('api/deduplicated', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(1);
  });

  it('DOES log for different resource URLs (separate entries)', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/resource-a', stubFields);
    logDevHint('api/resource-b', stubFields);
    expect(consoleInfoSpy).toHaveBeenCalledTimes(2);
  });
});

describe('logDevHint — PRODUCTION mode', () => {
  let consoleInfoSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    consoleInfoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined);
    vi.stubEnv('DEV', false);
  });

  afterEach(() => {
    consoleInfoSpy.mockRestore();
    vi.unstubAllEnvs();
  });

  it('does NOT call console.info when DEV=false', async () => {
    const logDevHint = await importFreshDevHint();
    logDevHint('api/production-resource', stubFields);
    expect(consoleInfoSpy).not.toHaveBeenCalled();
  });
});
