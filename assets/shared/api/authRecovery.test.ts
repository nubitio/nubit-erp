import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createCoreHttpClient } from '@nubitio/core';
import { sendApiRequest } from '@offline/api';
import { httpTransport } from './httpTransport';
import { SESSION_EXPIRED_EVENT } from '@shared/lib/sessionExpiredEvent';
import { beginAuthSession, getAuthSessionState } from '@shared/lib/authSessionState';

const deferred = () => {
  let resolve!: () => void;
  const promise = new Promise<void>((done) => {
    resolve = done;
  });
  return { promise, resolve };
};
const pathOf = (input: RequestInfo | URL) =>
  new URL(input instanceof Request ? input.url : String(input), window.location.origin).pathname;
const requestOf = (input: RequestInfo | URL, init?: RequestInit) =>
  input instanceof Request
    ? input
    : new Request(new URL(String(input), window.location.origin), init);

let install: (typeof import('./authRecovery'))['installAuthRecovery'];
beforeEach(async () => {
  vi.resetModules();
  delete (window as Window & { __nubitAuthRecoveryInstalled__?: boolean })
    .__nubitAuthRecoveryInstalled__;
  localStorage.clear();
  document.body.innerHTML = '';
  document.cookie = 'CSRF_TOKEN=initial-csrf; path=/';
  localStorage.setItem('user', JSON.stringify({ username: 'audit', userId: 1 }));
  localStorage.setItem('selectedBranchId', JSON.stringify('branch'));
  ({ installAuthRecovery: install } = await import('./authRecovery'));
});
afterEach(() => {
  vi.unstubAllGlobals();
});

describe('shared authentication recovery', () => {
  it('does not wrap fetch again when the installer runs more than once', () => {
    const original = vi.fn(async () => Response.json({}));
    vi.stubGlobal('fetch', original);

    install();
    const installedFetch = globalThis.fetch;
    install();

    expect(globalThis.fetch).toBe(installedFetch);
  });

  it('sends a file upload with a Content-Type whose boundary matches the body', async () => {
    const sent: { contentType: string | null; body: string }[] = [];
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const request = requestOf(input, init);
        sent.push({
          contentType: request.headers.get('Content-Type'),
          body: await request.clone().text(),
        });
        return Response.json({});
      }),
    );
    install();

    const body = new FormData();
    body.append('file', new File(['png-bytes'], 'foto.png', { type: 'image/png' }));
    await globalThis.fetch('/api/profile/avatar', { method: 'POST', body });

    const boundary = sent[0]?.contentType?.match(/^multipart\/form-data; boundary=(.+)$/)?.[1];
    expect(boundary, 'a multipart Content-Type with a boundary').toBeTruthy();
    // The body's delimiters must use the very boundary the header announces.
    expect(sent[0]?.body.startsWith(`--${boundary}`)).toBe(true);
    expect(sent[0]?.body).toContain('png-bytes');
  });

  it('shares one renewal across SDK clients and the outbox, preserving POST data and idempotency', async () => {
    const gate = deferred();
    let renewals = 0;
    let fresh = false;
    const posts: { key: string | null; body: string; csrf: string | null }[] = [];
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const path = pathOf(input);
        if (path === '/api/auth/refresh') {
          renewals += 1;
          expect(new Headers(init?.headers).get('Authorization')).toBeNull();
          expect(new Headers(init?.headers).get('X-CSRF-Token')).toBe('initial-csrf');
          await gate.promise;
          fresh = true;
          document.cookie = 'CSRF_TOKEN=renewed-csrf; path=/';
          return Response.json({ user: { username: 'audit', userId: 1 } });
        }
        const request = requestOf(input, init);
        expect(request.headers.has('X-Nubit-Auth-Epoch')).toBe(false);
        if (path === '/api/sales')
          posts.push({
            key: request.headers.get('Idempotency-Key'),
            body: await request.clone().text(),
            csrf: request.headers.get('X-CSRF-Token'),
          });
        return fresh ? Response.json({ id: path }) : Response.json({}, { status: 401 });
      }),
    );
    install();
    const first = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const second = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const all = Promise.all([
      first.get('products'),
      second.get('warehouses'),
      first.get('customers'),
      sendApiRequest({
        method: 'POST',
        url: '/api/sales',
        body: { total: 10 },
        headers: { 'Idempotency-Key': 'same-sale-attempt' },
      }),
    ]);
    await expect.poll(() => renewals).toBe(1);
    gate.resolve();
    const results = await all;
    expect(renewals).toBe(1);
    expect(results[3]).toMatchObject({ ok: true });
    expect(posts).toEqual([
      { key: 'same-sale-attempt', body: '{"total":10}', csrf: 'initial-csrf' },
      { key: 'same-sale-attempt', body: '{"total":10}', csrf: 'renewed-csrf' },
    ]);
  });

  it('reuses the new generation for an old 401 that arrives late', async () => {
    const late = deferred();
    let fresh = false;
    let renewals = 0;
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => {
        const path = pathOf(input);
        if (path === '/api/auth/refresh') {
          renewals += 1;
          fresh = true;
          document.cookie = 'CSRF_TOKEN=renewed-csrf; path=/';
          return Response.json({ user: {} });
        }
        const expired = !fresh;
        if (path === '/api/slow' && expired) await late.promise;
        return expired ? Response.json({}, { status: 401 }) : Response.json({ id: path });
      }),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const first = client.get('fast');
    const second = client.get('slow');
    await first;
    late.resolve();
    await second;
    expect(renewals).toBe(1);
  });

  it('keeps session and queued requests recoverable when renewal fails temporarily', async () => {
    const expired = vi.fn();
    window.addEventListener(SESSION_EXPIRED_EVENT, expired);
    let renewals = 0;
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => {
        if (pathOf(input) === '/api/auth/refresh') {
          renewals += 1;
          return Response.json({}, { status: 503 });
        }
        return Response.json({}, { status: 401 });
      }),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const results = await Promise.allSettled([client.get('products'), client.get('warehouses')]);
    expect(
      results.every((result) => result.status === 'rejected' && result.reason.status === 503),
    ).toBe(true);
    const offline = await sendApiRequest({ method: 'POST', url: '/api/sales', body: {} });
    expect(offline).toMatchObject({ recoverable: true, status: 503 });
    expect(offline.requiresLogin).toBeUndefined();
    expect(localStorage.getItem('user')).not.toBeNull();
    expect(expired).not.toHaveBeenCalled();
    expect(renewals).toBe(1);
    window.removeEventListener(SESSION_EXPIRED_EVENT, expired);
  });

  it('announces real expiry once and stops repeated renewal attempts', async () => {
    const expired = vi.fn();
    window.addEventListener(SESSION_EXPIRED_EVENT, expired);
    let renewals = 0;
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => {
        if (pathOf(input) === '/api/auth/refresh') renewals += 1;
        return Response.json({}, { status: 401 });
      }),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    await Promise.allSettled([
      client.get('products'),
      client.get('customers'),
      client.get('warehouses'),
    ]);
    await Promise.allSettled([client.get('products'), client.get('customers')]);
    expect(renewals).toBe(1);
    expect(getAuthSessionState().active).toBe(false);
    expect(expired).toHaveBeenCalledTimes(1);
    expect(document.querySelectorAll('.app-toast')).toHaveLength(1);
    window.removeEventListener(SESSION_EXPIRED_EVENT, expired);
  });

  it.each(['cookie', 'bearer'])(
    'normalizes renewed %s credentials instead of retrying an obsolete bearer',
    async (mode) => {
      localStorage.setItem('auth_token', JSON.stringify('old-access-marker'));
      const sent: (string | null)[] = [];
      let fresh = false;
      vi.stubGlobal(
        'fetch',
        vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
          if (pathOf(input) === '/api/auth/refresh') {
            fresh = true;
            document.cookie = 'CSRF_TOKEN=renewed-csrf; path=/';
            return Response.json(
              mode === 'bearer' ? { token: 'fresh-access-marker' } : { user: {} },
            );
          }
          sent.push(requestOf(input, init).headers.get('Authorization'));
          return fresh ? Response.json({ ok: true }) : Response.json({}, { status: 401 });
        }),
      );
      install();
      const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
      await client.get('products');
      expect(sent).toEqual([
        'Bearer old-access-marker',
        mode === 'bearer' ? 'Bearer fresh-access-marker' : null,
      ]);
    },
  );

  it('does not replay an old request after the authenticated session changes', async () => {
    const gate = deferred();
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => {
        await gate.promise;
        return Response.json({}, { status: 401 });
      }),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const request = client.get('products');
    await vi.waitFor(() => expect(globalThis.fetch).toBeDefined());
    // Let the request dispatch before replacing its identity.
    await new Promise((resolve) => setTimeout(resolve, 0));
    beginAuthSession();
    gate.resolve();
    await expect(request).rejects.toMatchObject({ name: 'AbortError' });
  });

  it('does not renew on an ordinary permission denial', async () => {
    const network = vi.fn(async () => Response.json({ detail: 'Forbidden' }, { status: 403 }));
    vi.stubGlobal('fetch', network);
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    await expect(client.get('products')).rejects.toMatchObject({ status: 403 });
    expect(network).toHaveBeenCalledTimes(1);
  });

  it('waits for credential publication when cookies arrive before the refresh body', async () => {
    const body = deferred();
    const late = deferred();
    let renewals = 0;
    let lateAttempts = 0;
    let fresh = false;
    localStorage.setItem('auth_token', JSON.stringify('old-access-marker'));
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const path = pathOf(input);
        if (path === '/api/auth/refresh') {
          renewals += 1;
          document.cookie = 'CSRF_TOKEN=renewed-csrf; path=/';
          const response = Response.json({ user: {} });
          vi.spyOn(response, 'json').mockImplementation(async () => {
            await body.promise;
            fresh = true;
            return { user: {} };
          });
          return response;
        }
        const oldRequest = !fresh;
        if (path === '/api/late') {
          lateAttempts += 1;
          if (oldRequest) await late.promise;
        }
        const bearer = requestOf(input, init).headers.get('Authorization');
        return oldRequest || bearer === 'Bearer old-access-marker'
          ? Response.json({}, { status: 401 })
          : Response.json({ ok: true });
      }),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const first = client.get('products');
    const second = client.get('late');
    await expect.poll(() => renewals).toBe(1);
    late.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(lateAttempts).toBe(1);
    body.resolve();
    await expect(Promise.all([first, second])).resolves.toHaveLength(2);
    expect(renewals).toBe(1);
    expect(getAuthSessionState().active).toBe(true);
  });

  it('does not cancel a shared renewal when one caller aborts', async () => {
    const gate = deferred();
    let fresh = false;
    let renewals = 0;
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) => {
        if (pathOf(input) === '/api/auth/refresh') {
          renewals += 1;
          await gate.promise;
          fresh = true;
          document.cookie = 'CSRF_TOKEN=renewed-csrf; path=/';
          return Response.json({ user: {} });
        }
        return fresh ? Response.json({ ok: true }) : Response.json({}, { status: 401 });
      }),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    const abort = new AbortController();
    const cancelled = client.get('products', { signal: abort.signal });
    const active = client.get('customers');
    await expect.poll(() => renewals).toBe(1);
    abort.abort();
    gate.resolve();
    await expect(cancelled).rejects.toMatchObject({ name: 'AbortError' });
    await expect(active).resolves.toMatchObject({ status: 200 });
    expect(renewals).toBe(1);
  });

  it('can report expiry again after successful login without reloading the page', async () => {
    const expired = vi.fn();
    window.addEventListener(SESSION_EXPIRED_EVENT, expired);
    vi.stubGlobal(
      'fetch',
      vi.fn(async (input: RequestInfo | URL) =>
        pathOf(input) === '/api/auth/login'
          ? Response.json({ user: { username: 'audit', userId: 1 } })
          : Response.json({}, { status: 401 }),
      ),
    );
    install();
    const client = createCoreHttpClient({ ...httpTransport, baseUrl: '/api/' });
    await expect(client.get('products')).rejects.toMatchObject({ status: 401 });
    expect(expired).toHaveBeenCalledTimes(1);
    await client.post('auth/login', { username: 'audit', password: 'fixture-only' });
    localStorage.setItem('user', JSON.stringify({ username: 'audit', userId: 1 }));
    await expect(client.get('products')).rejects.toMatchObject({ status: 401 });
    expect(expired).toHaveBeenCalledTimes(2);
    window.removeEventListener(SESSION_EXPIRED_EVENT, expired);
  });
});
