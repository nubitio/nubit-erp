import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { sendApiRequest } from './api';

const asResponse = (status: number, payload: unknown): Response =>
  ({
    status,
    ok: status >= 200 && status < 300,
    json: vi.fn().mockResolvedValue(payload),
  }) as unknown as Response;

describe('sendApiRequest', () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns data on successful request', async () => {
    fetchMock.mockResolvedValueOnce(asResponse(200, { ok: true }));

    const result = await sendApiRequest({
      method: 'GET',
      url: '/api/customers',
    });

    expect(result.ok).toBe(true);
    expect(result.status).toBe(200);
    expect(result.data).toEqual({ ok: true });
  });

  it('refreshes auth token once on 401 and retries original request', async () => {
    fetchMock
      .mockResolvedValueOnce(asResponse(401, { message: 'expired' }))
      .mockResolvedValueOnce(asResponse(200, { refreshed: true }))
      .mockResolvedValueOnce(asResponse(200, { id: 10 }));

    const result = await sendApiRequest({
      method: 'POST',
      url: '/api/orders',
      body: { total: 20 },
    });

    expect(result.ok).toBe(true);
    expect(result.data).toEqual({ id: 10 });
    expect(fetchMock).toHaveBeenCalledTimes(3);
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      '/api/auth/refresh',
      expect.objectContaining({ method: 'POST' })
    );
  });

  it('marks response as requiresLogin when refresh fails', async () => {
    fetchMock
      .mockResolvedValueOnce(asResponse(401, { message: 'expired' }))
      .mockResolvedValueOnce(asResponse(401, { message: 'refresh failed' }));

    const result = await sendApiRequest({
      method: 'GET',
      url: '/api/orders',
    });

    expect(result.ok).toBe(false);
    expect(result.status).toBe(401);
    expect(result.requiresLogin).toBe(true);
    expect(result.recoverable).toBe(false);
  });

  it('marks 5xx responses as recoverable', async () => {
    fetchMock.mockResolvedValueOnce(asResponse(503, { message: 'temporary' }));

    const result = await sendApiRequest({
      method: 'GET',
      url: '/api/orders',
    });

    expect(result.ok).toBe(false);
    expect(result.status).toBe(503);
    expect(result.recoverable).toBe(true);
    expect(result.errorMessage).toBe('temporary');
  });

  it('marks 429 responses as recoverable and prioritizes hydra error description', async () => {
    fetchMock.mockResolvedValueOnce(asResponse(429, { 'hydra:description': 'Slow down' }));

    const result = await sendApiRequest({
      method: 'GET',
      url: '/api/orders',
    });

    expect(result.ok).toBe(false);
    expect(result.status).toBe(429);
    expect(result.recoverable).toBe(true);
    expect(result.errorMessage).toBe('Slow down');
  });

  it('returns requiresLogin when request is still unauthorized after a successful refresh', async () => {
    fetchMock
      .mockResolvedValueOnce(asResponse(401, { message: 'expired' }))
      .mockResolvedValueOnce(asResponse(200, { refreshed: true }))
      .mockResolvedValueOnce(asResponse(401, { message: 'still unauthorized' }));

    const result = await sendApiRequest({
      method: 'GET',
      url: '/api/orders',
    });

    expect(result.ok).toBe(false);
    expect(result.status).toBe(401);
    expect(result.requiresLogin).toBe(true);
    expect(result.recoverable).toBe(false);
  });

  it('falls back to generic message when payload is not valid json', async () => {
    const response = {
      status: 400,
      ok: false,
      json: vi.fn().mockRejectedValue(new Error('invalid json')),
    } as unknown as Response;
    fetchMock.mockResolvedValueOnce(response);

    const result = await sendApiRequest({
      method: 'POST',
      url: '/api/orders',
      body: { total: 100 },
    });

    expect(result.ok).toBe(false);
    expect(result.status).toBe(400);
    expect(result.recoverable).toBe(false);
    expect(result.errorMessage).toBe('Error HTTP 400');
  });

  it('returns recoverable network error when fetch throws', async () => {
    fetchMock.mockRejectedValueOnce(new Error('Network down'));

    const result = await sendApiRequest({
      method: 'GET',
      url: '/api/orders',
    });

    expect(result.ok).toBe(false);
    expect(result.recoverable).toBe(true);
    expect(result.errorMessage).toBe('Network down');
  });
});
