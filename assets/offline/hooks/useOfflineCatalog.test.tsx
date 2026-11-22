import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { httpClient } from '@shared/api/HttpClient';
import { useOfflineCatalog } from './useOfflineCatalog';
import { FULL_REFRESH_AFTER_MS } from '@offline/catalogDelta';

vi.mock('@shared/api/HttpClient', () => ({ httpClient: { get: vi.fn() } }));
vi.mock('@nubitio/core', () => ({ getCoreApiBaseUrl: () => '/api/' }));
vi.mock('@offline/hooks/useConnectivity', () => ({
  useConnectivity: () => ({ isOnline: true }),
}));
vi.mock('@offline/catalog', () => ({
  getCachedProducts: vi.fn().mockResolvedValue([]),
  getLastProductsSyncAt: vi.fn().mockResolvedValue(null),
  persistProducts: vi.fn().mockResolvedValue(0),
  mergeCachedProducts: vi.fn().mockResolvedValue(0),
  updateCachedProductPrice: vi.fn(),
}));

const product = (id: string, salePrice: string) => ({
  id,
  name: `Producto ${id}`,
  salePrice,
  category: { id: 'c1', name: 'Cat' },
  unitOfMeasure: { id: 1, name: 'Unidad' },
});

const get = vi.mocked(httpClient.get);
const calls = () => get.mock.calls.map(([url, config]) => ({ url: String(url), config }));

/** Server double: a full catalogue, a cursor, and a configurable delta. */
const serve = (options: { delta?: () => unknown } = {}) => {
  get.mockImplementation(((url: string, config?: { params?: { since?: string } }) => {
    if (url.endsWith('products-changes')) {
      if (config?.params?.since) {
        const delta = options.delta?.();
        if (delta instanceof Error) return Promise.reject(delta);
        return Promise.resolve({ data: delta ?? { cursor: 'C2', products: [] } });
      }
      return Promise.resolve({ data: { cursor: 'C1', products: [] } });
    }
    return Promise.resolve({ data: [product('a', '10.10'), product('b', '5.00')] });
  }) as never);
};

beforeEach(() => {
  window.localStorage.clear();
  get.mockReset();
});
afterEach(() => vi.useRealTimers());

const loaded = async () => {
  const hook = renderHook(() => useOfflineCatalog());
  await waitFor(() => expect(hook.result.current.loading).toBe(false));
  return hook;
};

describe('useOfflineCatalog refresh', () => {
  it('asks for the cursor before it downloads the catalogue, and remembers it', async () => {
    serve();
    await loaded();

    const urls = calls().map((c) => c.url);
    expect(urls.indexOf('/api/products-changes')).toBeLessThan(
      urls.findIndex((u) => u.includes('products?itemsPerPage')),
    );
    expect(window.localStorage.getItem('pos_catalog_cursor')).toBe('C1');
  });

  it('then asks only for what changed, and merges it', async () => {
    serve({ delta: () => ({ cursor: 'C2', products: [product('a', '10.40')] }) });
    const hook = await loaded();
    get.mockClear();

    await act(async () => hook.result.current.refresh());

    expect(calls().some((c) => c.url.includes('products?itemsPerPage'))).toBe(false);
    expect(calls()[0].config).toMatchObject({ params: { since: 'C1' } });
    const prices = Object.fromEntries(hook.result.current.menuData.map((p) => [p.id, p.salePrice]));
    expect(prices).toEqual({ a: '10.40', b: '5.00' });
    expect(window.localStorage.getItem('pos_catalog_cursor')).toBe('C2');
  });

  it('falls back to a full download when the delta fails', async () => {
    serve({ delta: () => new Error('400 bad cursor') });
    const hook = await loaded();
    get.mockClear();

    await act(async () => hook.result.current.refresh());

    expect(calls().some((c) => c.url.includes('products?itemsPerPage'))).toBe(true);
  });

  it('downloads in full again once the last full download is old', async () => {
    serve();
    const hook = await loaded();
    window.localStorage.setItem(
      'pos_catalog_full_at',
      String(Date.now() - FULL_REFRESH_AFTER_MS - 1),
    );
    get.mockClear();

    await act(async () => hook.result.current.refresh());

    expect(calls().some((c) => c.url.includes('products?itemsPerPage'))).toBe(true);
  });

  it('keeps the catalogue on screen when every call fails', async () => {
    serve();
    const hook = await loaded();
    get.mockReset();
    get.mockRejectedValue(new Error('network down'));

    await act(async () => hook.result.current.refresh());

    expect(hook.result.current.menuData).toHaveLength(2);
    expect(hook.result.current.loadError).toBeNull();
  });
});
