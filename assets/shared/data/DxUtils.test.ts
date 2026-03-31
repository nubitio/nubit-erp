/**
 * DxUtils — iriMode tests (BUG-010)
 *
 * Verifies that getDataStore(iriMode=true) injects a synthetic "_iri" field
 * (copied from "@id") on every item returned by load() and byKey(), allowing
 * DevExtreme lookups to match full IRI values without using "@id" as a
 * dot-notation JS property accessor.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';

// ---------------------------------------------------------------------------
// Mocks — must be declared before the module under test is imported
// ---------------------------------------------------------------------------

vi.mock('../api/HttpClient', () => ({
  httpClient: {
    get: vi.fn(),
  },
}));

vi.mock('rxjs', () => ({
  lastValueFrom: vi.fn((observable: Promise<unknown>) => observable),
}));

vi.mock('devextreme/data/custom_store', () => {
  // Return a simple class that stores its constructor config so tests can
  // inspect the load/byKey handlers directly.
  return {
    default: class CustomStore {
      public config: Record<string, unknown>;
      constructor(config: Record<string, unknown>) {
        this.config = config;
      }
    },
  };
});

import { httpClient } from '../api/HttpClient';
import { lastValueFrom } from 'rxjs';
import { dxUtils } from './DxUtils';
import type ApiResponse from '../api/ApiResponse';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

type StoreConfig = {
  key: string;
  load: (opts: Record<string, unknown>) => Promise<unknown>;
  byKey: (key: unknown) => Promise<unknown>;
};

const getStoreConfig = (store: unknown): StoreConfig => (store as { config: StoreConfig }).config;

const makeApiResponse = <T>(data: T): ApiResponse<T> =>
  ({
    data,
    headers: { 'x-total-count': '1' },
    status: 200,
    statusText: 'OK',
    config: {} as never,
  }) as unknown as ApiResponse<T>;

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DxUtils › getDataStore › iriMode=false (default)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('returns items without a synthetic _iri field when iriMode is false', async () => {
    const item = { '@id': '/api/warehouses/abc', id: 'abc', name: 'Main' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse([item]));
    vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/warehouses', 'id');
    const { load } = getStoreConfig(store);
    const result = (await load({ filter: [], sort: [] })) as { data: unknown[] };

    expect(result.data[0]).not.toHaveProperty('_iri');
  });
});

describe('DxUtils › getDataStore › iriMode=true (BUG-010)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('injects _iri = @id on every item returned by load()', async () => {
    const items = [
      { '@id': '/api/warehouses/uuid-1', id: 'uuid-1', name: 'Almacén Central' },
      { '@id': '/api/warehouses/uuid-2', id: 'uuid-2', name: 'Almacén Norte' },
    ];
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse(items));
    vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/warehouses', '_iri', null, [], [], [], true);
    const { load } = getStoreConfig(store);
    const result = (await load({ filter: [], sort: [] })) as { data: Record<string, unknown>[] };

    expect(result.data).toHaveLength(2);
    expect(result.data[0]['_iri']).toBe('/api/warehouses/uuid-1');
    expect(result.data[1]['_iri']).toBe('/api/warehouses/uuid-2');
  });

  it('preserves all original fields alongside _iri', async () => {
    const item = { '@id': '/api/warehouses/uuid-1', id: 'uuid-1', name: 'Almacén Central' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse([item]));
    vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/warehouses', '_iri', null, [], [], [], true);
    const { load } = getStoreConfig(store);
    const result = (await load({ filter: [], sort: [] })) as { data: Record<string, unknown>[] };

    const loaded = result.data[0];
    expect(loaded['@id']).toBe('/api/warehouses/uuid-1');
    expect(loaded['id']).toBe('uuid-1');
    expect(loaded['name']).toBe('Almacén Central');
    expect(loaded['_iri']).toBe('/api/warehouses/uuid-1');
  });

  it('sets the CustomStore key to "_iri"', () => {
    const store = dxUtils.getDataStore('/api/warehouses', '_iri', null, [], [], [], true);
    const { key } = getStoreConfig(store);
    expect(key).toBe('_iri');
  });

  it('injects _iri on item returned by byKey() given a full IRI', async () => {
    const item = { '@id': '/api/warehouses/uuid-1', id: 'uuid-1', name: 'Almacén Central' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse(item));
    vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/warehouses', '_iri', null, [], [], [], true);
    const { byKey } = getStoreConfig(store);
    const result = (await byKey('/api/warehouses/uuid-1')) as Record<string, unknown>;

    expect(result['_iri']).toBe('/api/warehouses/uuid-1');
    expect(result['name']).toBe('Almacén Central');
  });

  it('byKey() calls the IRI path directly (not url + "/" + iri)', async () => {
    const item = { '@id': '/api/warehouses/uuid-1', id: 'uuid-1', name: 'Almacén Central' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse(item));
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/warehouses', '_iri', null, [], [], [], true);
    const { byKey } = getStoreConfig(store);
    await byKey('/api/warehouses/uuid-1');

    // The resolved URL should be the IRI itself, not /api/warehouses//api/warehouses/uuid-1
    expect(getSpy).toHaveBeenCalledWith('/api/warehouses/uuid-1');
  });

  it('returns null without calling the API when byKey is called with undefined', async () => {
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/warehouses', '_iri', null, [], [], [], true);
    const { byKey } = getStoreConfig(store);
    const result = await byKey(undefined);

    expect(result).toBeNull();
    expect(getSpy).not.toHaveBeenCalled();
  });

  it('returns a matching prependData item without calling the API', async () => {
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore(
      '/api/categories',
      'id',
      null,
      [],
      [],
      [{ prependData: [{ id: 'uuid-1', name: 'Root' }] }],
    );
    const { byKey } = getStoreConfig(store);
    const result = (await byKey('uuid-1')) as Record<string, unknown>;

    expect(result).toEqual({ id: 'uuid-1', name: 'Root' });
    expect(getSpy).not.toHaveBeenCalled();
  });

  it('prefers a matching prependData item before attempting a byKey request', async () => {
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore(
      '/api/categories',
      'id',
      null,
      [],
      [],
      [{ prependData: [{ id: 'uuid-1', name: 'Root' }] }],
    );
    const { byKey } = getStoreConfig(store);
    const result = (await byKey('uuid-1')) as Record<string, unknown>;

    expect(result).toEqual({ id: 'uuid-1', name: 'Root' });
    expect(getSpy).not.toHaveBeenCalled();
  });
});

describe('DxUtils › getDataStore › searchOperation stripping (AC-9 / AC-14)', () => {
  beforeEach(() => vi.clearAllMocks());

  it('strips searchOperation before sending the HTTP request (AC-9)', async () => {
    const item = { id: 1, name: 'Laptop' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse([item]));
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/product-stocks', 'id');
    const { load } = getStoreConfig(store);
    await load({ filter: [], sort: [], searchOperation: 'contains' });

    const sentParams = getSpy.mock.calls[0][1]?.params as Record<string, unknown>;
    expect(sentParams).not.toHaveProperty('searchOperation');
  });

  it('strips searchValue when searchExpr is absent', async () => {
    const item = { id: 1, name: 'Laptop' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse([item]));
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/product-stocks', 'id');
    const { load } = getStoreConfig(store);
    await load({ filter: [], sort: [], searchOperation: 'contains', searchValue: 'lap' });

    const sentParams = getSpy.mock.calls[0][1]?.params as Record<string, unknown>;
    expect(sentParams).not.toHaveProperty('searchOperation');
    expect(sentParams).not.toHaveProperty('searchValue');
  });

  it('keeps searchValue when searchExpr is also present', async () => {
    const item = { id: 1, name: 'Laptop' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse([item]));
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/product-stocks', 'id');
    const { load } = getStoreConfig(store);
    await load({
      filter: [],
      sort: [],
      searchOperation: 'contains',
      searchExpr: 'name',
      searchValue: 'lap',
    });

    const sentParams = getSpy.mock.calls[0][1]?.params as Record<string, unknown>;
    expect(sentParams).not.toHaveProperty('searchOperation');
    expect(sentParams).toHaveProperty('searchExpr', 'name');
    expect(sentParams).toHaveProperty('searchValue', 'lap');
  });

  it('forwards filter, sort, skip, take, requireTotalCount unchanged (AC-14)', async () => {
    const item = { id: 1, name: 'Laptop' };
    vi.mocked(lastValueFrom).mockResolvedValueOnce(makeApiResponse([item]));
    const getSpy = vi.mocked(httpClient.get).mockReturnValue({} as never);

    const store = dxUtils.getDataStore('/api/product-stocks', 'id');
    const { load } = getStoreConfig(store);
    await load({
      filter: [['name', '=', 'Laptop']],
      sort: [{ selector: 'name', desc: false }],
      skip: 0,
      take: 20,
      requireTotalCount: true,
    });

    const sentParams = getSpy.mock.calls[0][1]?.params as Record<string, unknown>;
    expect(sentParams).toHaveProperty('skip', 0);
    expect(sentParams).toHaveProperty('take', 20);
    expect(sentParams).toHaveProperty('requireTotalCount', true);
    expect(sentParams).not.toHaveProperty('searchOperation');
  });
});
