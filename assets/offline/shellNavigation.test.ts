import { describe, expect, it } from 'vitest';
import { isTenantShellNavigation, shellCacheKeyWillBeUsed } from './shellNavigation';

const nav = (path: string, init: { mode?: string; method?: string } = {}) =>
  isTenantShellNavigation({
    request: { mode: init.mode ?? 'navigate', method: init.method ?? 'GET' },
    url: new URL(path, 'https://demo.example.test'),
  });

describe('isTenantShellNavigation', () => {
  it.each(['/', '/pos', '/billing/sales', '/catalog/products', '/credit-note/12', '/login'])(
    'caches the shell for the SPA route %s',
    (path) => expect(nav(path)).toBe(true),
  );

  it.each([
    '/accounting',
    '/accounts-payable',
    '/accounts-receivable',
    '/registers',
    '/administration',
  ])('still treats %s as an SPA route (only looks like a reserved prefix)', (path) => {
    expect(nav(path)).toBe(true);
  });

  it.each([
    '/api/products',
    '/admin/tenants',
    '/account/dashboard',
    '/register/verify',
    '/buscar',
    '/receipt/abc',
    '/receipt/abc/ticket',
    '/health',
    '/webhook/paypal',
    '/build/chunk.js',
    '/sw.js',
    '/workbox-abc123.js',
    '/_profiler/x',
    '/.well-known/mercure',
  ])('never caches %s under the shell key', (path) => {
    expect(nav(path)).toBe(false);
  });

  it('ignores files and non-navigations', () => {
    expect(nav('/favicon.ico')).toBe(false);
    expect(nav('/a/b.css')).toBe(false);
    expect(nav('/pos', { mode: 'cors' })).toBe(false);
    expect(nav('/pos', { mode: 'no-cors' })).toBe(false);
    expect(nav('/pos', { method: 'POST' })).toBe(false);
  });
});

describe('shellCacheKeyWillBeUsed', () => {
  it('maps every shell navigation to the origin root, dropping path, query and hash', () => {
    for (const path of ['/', '/billing/sales?highlight=7', '/pos#x', '/catalog/products/12']) {
      const key = shellCacheKeyWillBeUsed({
        request: { url: new URL(path, 'https://a.test:8443').href },
      });
      expect(key).toBe('https://a.test:8443/');
    }
  });

  it('keeps tenants apart: the key carries the origin', () => {
    const a = shellCacheKeyWillBeUsed({ request: { url: 'https://acme.example.test/pos' } });
    const b = shellCacheKeyWillBeUsed({ request: { url: 'https://other.example.test/pos' } });
    expect(a).not.toBe(b);
  });
});
