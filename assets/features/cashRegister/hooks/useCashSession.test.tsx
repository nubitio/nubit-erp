import { act, renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cashSessionService, type CashPosState } from '../services/cashSessionService';
import { useCashSession } from './useCashSession';

vi.mock('../services/cashSessionService', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../services/cashSessionService')>()),
  cashSessionService: { getPosState: vi.fn() },
}));

const getPosState = vi.mocked(cashSessionService.getPosState);

const open: CashPosState = {
  session: {
    id: 42,
    cashRegisterId: 3,
    cashRegisterName: 'Caja 3',
    openedAt: '2026-11-23T08:30:00+00:00',
    openingAmount: '150.00',
  },
  availableRegisters: [],
};
const closed: CashPosState = { session: null, availableRegisters: [{ id: 3, name: 'Caja 3' }] };

let client: QueryClient;
const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={client}>{children}</QueryClientProvider>
);

beforeEach(() => {
  localStorage.setItem('user', JSON.stringify({ userId: 7, username: 'cajero' }));
  localStorage.setItem('selectedBranchId', JSON.stringify('branch-1'));
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  getPosState.mockReset();
});
afterEach(() => {
  client.clear();
  localStorage.clear();
});

describe('useCashSession', () => {
  it('does not claim open or closed until the server answers', async () => {
    getPosState.mockResolvedValue(open);
    const { result } = renderHook(() => useCashSession(), { wrapper });

    expect(result.current.isLoading).toBe(true);
    expect(result.current.session).toBeNull();

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.session?.cashRegisterName).toBe('Caja 3');
  });

  it('is closed, with the registers it could open, when the server has no session for the user', async () => {
    getPosState.mockResolvedValue(closed);
    const { result } = renderHook(() => useCashSession(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.session).toBeNull();
    expect(result.current.availableRegisters).toEqual([{ id: 3, name: 'Caja 3' }]);
  });

  it('reads a state without the session key, as the API omits nulls', async () => {
    getPosState.mockResolvedValue({ availableRegisters: [] } as unknown as CashPosState);
    const { result } = renderHook(() => useCashSession(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.session).toBeNull();
  });

  it('picks up a session opened or closed elsewhere when refreshed', async () => {
    getPosState.mockResolvedValue(open);
    const { result } = renderHook(() => useCashSession(), { wrapper });
    await waitFor(() => expect(result.current.session).not.toBeNull());

    getPosState.mockResolvedValue(closed);
    await act(async () => {
      await result.current.refresh();
    });

    await waitFor(() => expect(result.current.session).toBeNull());
  });

  it('keeps the last answer and flags it stale when a refresh fails', async () => {
    getPosState.mockResolvedValue(open);
    const { result } = renderHook(() => useCashSession(), { wrapper });
    await waitFor(() => expect(result.current.session).not.toBeNull());

    getPosState.mockRejectedValue(new Error('Network Error'));
    await act(async () => {
      await result.current.refresh();
    });

    await waitFor(() => expect(result.current.isStale).toBe(true));
    expect(result.current.session?.id).toBe(42);
  });

  it('is not loading forever when the first answer fails', async () => {
    getPosState.mockRejectedValue(new Error('Network Error'));
    const { result } = renderHook(() => useCashSession(), { wrapper });

    await waitFor(() => expect(result.current.isStale).toBe(true));

    expect(result.current.isLoading).toBe(false);
    expect(result.current.session).toBeNull();
  });
});
