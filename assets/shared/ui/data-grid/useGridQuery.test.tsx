import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, cleanup, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { FilterLeaf } from './gridProtocol';
import { useGridQuery } from './useGridQuery';

const get = vi.fn();
vi.mock('@shared/api/HttpClient', () => ({
  httpClient: { get: (...args: unknown[]) => get(...args) },
}));

type Row = { id: number; name: string };
const reply = (rows: Row[], total = rows.length) => ({
  data: { member: rows },
  headers: { 'x-total-count': String(total) },
});

const SEARCH = ['name'] as const;
const filterOf = (name: string, value: string): FilterLeaf | null =>
  name === 'status' && value ? ['isActive', '=', value === 'active'] : null;

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
  return renderHook(
    () =>
      useGridQuery<Row>({
        url: '/api/things',
        queryKey: ['things'],
        searchFields: SEARCH,
        filterOf,
      }),
    { wrapper },
  );
}

const paramsOfCall = (n: number) =>
  (get.mock.calls[n]?.[1] as { params: Record<string, unknown> }).params;

describe('useGridQuery', () => {
  beforeEach(() => {
    // mockClear + a default implementation rather than mockReset: with Vitest 4, resetting a mock
    // that a later test makes reject surfaces the rejection as a failure of that test.
    get.mockClear();
    get.mockImplementation(() => Promise.resolve(reply([])));
  });
  afterEach(cleanup);

  it('loads the first page and reads the total from the header', async () => {
    get.mockResolvedValue(reply([{ id: 1, name: 'a' }], 41));
    const { result } = setup();

    await waitFor(() => expect(result.current.isPending).toBe(false));

    expect(result.current.rows).toEqual([{ id: 1, name: 'a' }]);
    expect(result.current.totalCount).toBe(41);
    expect(paramsOfCall(0)).toMatchObject({ page: 1, itemsPerPage: 20 });
  });

  it('sends the AbortSignal and asks for inline errors (the grid shows its own)', async () => {
    get.mockResolvedValue(reply([]));
    const { result } = setup();
    await waitFor(() => expect(result.current.isPending).toBe(false));

    const config = get.mock.calls[0]?.[1] as { signal?: AbortSignal; errorPresentation?: string };
    expect(config.signal).toBeInstanceOf(AbortSignal);
    expect(config.errorPresentation).toBe('inline');
  });

  it('narrowing from page 3 issues ONE request, for page 1 (no request for the new filter on the old page)', async () => {
    get.mockResolvedValue(reply([{ id: 1, name: 'a' }], 100));
    const { result } = setup();
    await waitFor(() => expect(result.current.isPending).toBe(false));
    act(() => result.current.setPage(3));
    await waitFor(() => expect(get).toHaveBeenCalledTimes(2));
    expect(paramsOfCall(1)).toMatchObject({ page: 4 });

    get.mockClear();
    act(() => result.current.setFilter('status', 'active'));
    await waitFor(() => expect(get).toHaveBeenCalled());
    await waitFor(() => expect(result.current.isFetching).toBe(false));

    expect(get).toHaveBeenCalledTimes(1);
    expect(paramsOfCall(0)).toMatchObject({ page: 1 });
    expect(JSON.parse(paramsOfCall(0)['filter'] as string)).toEqual(['isActive', '=', true]);
  });

  it('keeps the previous rows, flagged stale, while the next page loads', async () => {
    get.mockResolvedValueOnce(reply([{ id: 1, name: 'first' }], 40));
    const { result } = setup();
    await waitFor(() => expect(result.current.rows).toHaveLength(1));

    let release!: () => void;
    get.mockImplementationOnce(
      () =>
        new Promise((resolve) => (release = () => resolve(reply([{ id: 2, name: 'second' }], 40)))),
    );
    act(() => result.current.setPage(1));

    await waitFor(() => expect(result.current.isStale).toBe(true));
    expect(result.current.rows).toEqual([{ id: 1, name: 'first' }]);

    act(() => release());
    await waitFor(() => expect(result.current.isStale).toBe(false));
    expect(result.current.rows).toEqual([{ id: 2, name: 'second' }]);
  });

  it('exposes the failure instead of throwing', async () => {
    get.mockImplementation(() => Promise.reject(Object.assign(new Error('boom'), { status: 500 })));
    const { result } = setup();

    await waitFor(() => expect(result.current.error).toBeTruthy());

    expect(result.current.rows).toEqual([]);
  });

  it('builds export parameters without pagination', async () => {
    get.mockResolvedValue(reply([]));
    const { result } = setup();
    await waitFor(() => expect(result.current.isPending).toBe(false));
    act(() => result.current.setSearch('acme'));

    await waitFor(() => expect(result.current.exportParams).toHaveProperty('searchValue', 'acme'));
    expect(result.current.exportParams).not.toHaveProperty('page');
    expect(result.current.exportParams).not.toHaveProperty('itemsPerPage');
  });
});
