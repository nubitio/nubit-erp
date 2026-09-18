import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The adapter's job is error UX on top of the library client, so the transport
// itself is mocked: these tests pin which failures notify the user and how.
const core = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
  dispatch: vi.fn(),
}));

vi.mock('@nubitio/core', () => ({
  createCoreHttpClient: () => ({
    get: core.get,
    post: core.post,
    put: core.put,
    patch: core.patch,
    delete: core.delete,
  }),
  getCoreApiBaseUrl: () => '/api/',
  dispatch: core.dispatch,
}));

import { HTTP_ERROR_EVENT } from './HttpClient';
import { SESSION_EXPIRED_EVENT } from '../lib/sessionExpiredEvent';

type Client = (typeof import('./HttpClient'))['httpClient'];

let httpClient: Client;

/**
 * A fresh client per test: the adapter remembers (per page load) whether the
 * session-expired notice was already shown, and that state must not leak
 * between tests.
 */
beforeEach(async () => {
  vi.resetAllMocks();
  vi.resetModules();
  document.body.innerHTML = '';
  localStorage.clear();
  ({ httpClient } = await import('./HttpClient'));
});

afterEach(() => {
  document.body.innerHTML = '';
});

function rejectWith(status: number, data: Record<string, unknown> = {}): Promise<never> {
  return Promise.reject(Object.assign(new Error('request failed'), { status, data }));
}

function toasts(): Element[] {
  return Array.from(document.querySelectorAll('.app-toast'));
}

function dispatchedEvents(): string[] {
  return core.dispatch.mock.calls.map((call) => String(call[0]));
}

function headerValue(headers: Record<string, string>, name: string): string | undefined {
  return Object.entries(headers).find(([key]) => key.toLowerCase() === name)?.[1];
}

describe('successful responses', () => {
  it('returns the ApiResponse contract (data plus a plain headers record)', async () => {
    const response = new Response(JSON.stringify({ id: 1 }), {
      status: 200,
      headers: { 'X-Total-Count': '5' },
    });
    core.get.mockResolvedValueOnce({
      response,
      data: { id: 1 },
      headers: response.headers,
      status: 200,
    });

    const result = await httpClient.get<{ id: number }>('/api/products');

    expect(result.data).toEqual({ id: 1 });
    // Call-sites index headers directly, so it must stay a plain object.
    expect(headerValue(result.headers, 'x-total-count')).toBe('5');
  });
});

describe('login failures', () => {
  it('does not show a toast or expire the session', async () => {
    core.post.mockReturnValueOnce(rejectWith(401, { detail: 'Credenciales inválidas' }));

    await expect(httpClient.post('/api/auth/login', {})).rejects.toMatchObject({ status: 401 });

    expect(toasts()).toHaveLength(0);
    expect(core.dispatch).not.toHaveBeenCalled();
  });

  it('leaves the error for the form to render', async () => {
    core.post.mockReturnValueOnce(rejectWith(401, { detail: 'Credenciales inválidas' }));

    await expect(httpClient.post('/api/auth/login', {})).rejects.toMatchObject({
      data: { detail: 'Credenciales inválidas' },
    });
  });
});

describe('expired sessions', () => {
  it('notifies session expiry and toasts on a non-login 401', async () => {
    const onExpired = vi.fn();
    window.addEventListener(SESSION_EXPIRED_EVENT, onExpired);
    core.get.mockReturnValueOnce(rejectWith(401));

    await expect(httpClient.get('/api/products')).rejects.toMatchObject({ status: 401 });

    expect(onExpired).toHaveBeenCalledTimes(1);
    expect(toasts()).toHaveLength(1);
    window.removeEventListener(SESSION_EXPIRED_EVENT, onExpired);
  });

  it('notifies expiry only once per page load', async () => {
    const onExpired = vi.fn();
    window.addEventListener(SESSION_EXPIRED_EVENT, onExpired);
    core.get.mockReturnValue(rejectWith(401));

    await expect(httpClient.get('/api/products')).rejects.toThrow();
    await expect(httpClient.get('/api/customers')).rejects.toThrow();

    expect(onExpired).toHaveBeenCalledTimes(1);
    window.removeEventListener(SESSION_EXPIRED_EVENT, onExpired);
  });
});

describe('validation failures', () => {
  it('does not toast when the response carries field violations', async () => {
    core.post.mockReturnValueOnce(
      rejectWith(422, { violations: [{ propertyPath: 'name', message: 'required' }] }),
    );

    await expect(httpClient.post('/api/products', {})).rejects.toMatchObject({ status: 422 });

    expect(toasts()).toHaveLength(0);
    expect(core.dispatch).not.toHaveBeenCalled();
  });

  it('toasts a 422 that carries no violations', async () => {
    core.post.mockReturnValueOnce(rejectWith(422, {}));

    await expect(httpClient.post('/api/products', {})).rejects.toMatchObject({ status: 422 });

    expect(toasts()).toHaveLength(1);
    expect(dispatchedEvents()).toContain(HTTP_ERROR_EVENT);
  });
});

describe('other failures', () => {
  it('toasts the server detail and publishes HTTP_ERROR_EVENT', async () => {
    core.get.mockReturnValueOnce(rejectWith(500, { detail: 'Fallo interno' }));

    await expect(httpClient.get('/api/products')).rejects.toMatchObject({ status: 500 });

    expect(toasts()[0]?.textContent).toBe('Fallo interno');
    expect(dispatchedEvents()).toContain(HTTP_ERROR_EVENT);
  });

  it('falls back to a status message when the body has no detail', async () => {
    core.get.mockReturnValueOnce(rejectWith(404, {}));

    await expect(httpClient.get('/api/products/1')).rejects.toMatchObject({ status: 404 });

    expect(toasts()[0]?.textContent).toBe('No se encontró el recurso');
  });

  it('never treats a server error as an expired session', async () => {
    core.get.mockReturnValueOnce(rejectWith(503, {}));

    await expect(httpClient.get('/api/products')).rejects.toThrow();

    expect(dispatchedEvents()).not.toContain(SESSION_EXPIRED_EVENT);
  });
});
