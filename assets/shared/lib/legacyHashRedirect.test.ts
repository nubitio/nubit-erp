import { describe, expect, it, vi } from 'vitest';
import { legacyHashTarget, normalizeLegacyHashUrl } from './legacyHashRedirect';

const at = (hash: string, pathname = '/') => legacyHashTarget({ pathname, hash });

describe('legacyHashTarget', () => {
  it('maps a hash route to its real path and keeps the query', () => {
    expect(at('#/billing/sales?highlight=7')).toEqual({
      url: '/billing/sales?highlight=7',
      reload: false,
    });
    expect(at('#/pos')).toEqual({ url: '/pos', reload: false });
  });

  it('maps the bare hash root to /', () => {
    expect(at('#/')).toEqual({ url: '/', reload: false });
  });

  it('drops a trailing slash', () => {
    expect(at('#/catalog/products/')).toEqual({ url: '/catalog/products', reload: false });
  });

  it.each(['', '#', '#section', '#token=abc', '#?x=1'])(
    'ignores %j (not a legacy route)',
    (hash) => {
      expect(at(hash)).toBeNull();
    },
  );

  it('ignores real paths: only the site root carries legacy hash URLs', () => {
    expect(at('#/pos', '/billing/sales')).toBeNull();
  });

  it('refuses protocol-relative and backslash forms', () => {
    expect(at('#//evil.example/x')).toBeNull();
    expect(at('#/\\evil.example')).toBeNull();
  });

  describe('secrets move from the query to the fragment', () => {
    it.each(['/reset-password', '/accept-invitation', '/impersonate'])('%s', (route) => {
      expect(at(`#${route}?token=abc%2B1`)).toEqual({
        url: `${route}#token=abc%2B1`,
        reload: false,
      });
    });

    it('keeps other parameters in the query', () => {
      expect(at('#/reset-password?token=t&lang=es')).toEqual({
        url: '/reset-password?lang=es#token=t',
        reload: false,
      });
    });

    it('leaves a secret-route without a token alone', () => {
      expect(at('#/reset-password')).toEqual({ url: '/reset-password', reload: false });
    });

    it('does not move a "token" parameter on any other route', () => {
      expect(at('#/billing/sales?token=x')).toEqual({
        url: '/billing/sales?token=x',
        reload: false,
      });
    });
  });

  describe('renamed routes (ADR-020 D7)', () => {
    it('#/receipt becomes /stock-receipt, as an SPA route', () => {
      expect(at('#/receipt')).toEqual({ url: '/stock-receipt', reload: false });
      expect(at('#/receipt/new')).toEqual({ url: '/stock-receipt/new', reload: false });
      expect(at('#/receipt/12?x=1')).toEqual({ url: '/stock-receipt/12?x=1', reload: false });
    });

    it('does not rename lookalikes', () => {
      expect(at('#/receipts')).toEqual({ url: '/receipts', reload: false });
    });
  });

  describe('server-owned namespaces need a real page load', () => {
    it.each([
      ['#/register', '/register'],
      ['#/register/verify?x=1', '/register/verify?x=1'],
      ['#/account/dashboard', '/account/dashboard'],
      ['#/admin/tenants', '/admin/tenants'],
      ['#/buscar', '/buscar'],
    ])('%s', (hash, url) => {
      expect(at(hash)).toEqual({ url, reload: true });
    });

    it('does not treat SPA routes that merely start with a reserved word as server-owned', () => {
      expect(at('#/accounting')).toEqual({ url: '/accounting', reload: false });
      expect(at('#/accounts-payable')).toEqual({ url: '/accounts-payable', reload: false });
      expect(at('#/registers')).toEqual({ url: '/registers', reload: false });
    });
  });
});

describe('normalizeLegacyHashUrl', () => {
  const win = (hash: string, pathname = '/') => ({
    location: { pathname, hash, replace: vi.fn() },
    history: { state: { k: 1 }, replaceState: vi.fn() },
  });

  it('rewrites the address bar in place, keeping history state', () => {
    const w = win('#/billing/sales');
    expect(normalizeLegacyHashUrl(w as unknown as Window)).toBe(true);
    expect(w.history.replaceState).toHaveBeenCalledWith({ k: 1 }, '', '/billing/sales');
    expect(w.location.replace).not.toHaveBeenCalled();
  });

  it('does a real navigation for server-owned targets', () => {
    const w = win('#/register');
    expect(normalizeLegacyHashUrl(w as unknown as Window)).toBe(true);
    expect(w.location.replace).toHaveBeenCalledWith('/register');
    expect(w.history.replaceState).not.toHaveBeenCalled();
  });

  it('is a no-op for real-path URLs', () => {
    const w = win('', '/billing/sales');
    expect(normalizeLegacyHashUrl(w as unknown as Window)).toBe(false);
    expect(w.history.replaceState).not.toHaveBeenCalled();
    expect(w.location.replace).not.toHaveBeenCalled();
  });
});
