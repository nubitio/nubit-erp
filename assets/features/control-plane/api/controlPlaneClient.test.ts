import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { controlPlaneClient } from './controlPlaneClient';

describe('controlPlaneClient CSRF protection', () => {
  beforeEach(() => {
    document.cookie = 'CSRF_TOKEN=; Max-Age=0; path=/';
    document.cookie = 'CSRF_TOKEN=control-plane-csrf; path=/';
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('sends the CSRF cookie as a header on control-plane mutations', async () => {
    const fetch = vi.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      expect(new Headers(init?.headers).get('X-CSRF-Token')).toBe('control-plane-csrf');
      return Response.json({ ok: true });
    });
    vi.stubGlobal('fetch', fetch);

    await controlPlaneClient.createPlan({
      name: 'starter',
      limits: {
        users: 1,
        customers: 1,
        products: 1,
        invoices: 1,
        branches: 1,
        storage_mb: 1,
      },
      isDefault: false,
    });

    expect(fetch).toHaveBeenCalledOnce();
  });
});
