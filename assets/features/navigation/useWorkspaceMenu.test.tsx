import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTenantRuntimeConfig } from '@shared/hooks';
import { menuService } from './services';
import { useWorkspaceMenu } from './useWorkspaceMenu';

vi.mock('@shared/hooks', () => ({ useTenantRuntimeConfig: vi.fn() }));
vi.mock('./services', () => ({
  menuService: { getMenuByRoleIdForRuntimeConfig: vi.fn(), getMenuByRoleId: vi.fn() },
}));

const config = vi.mocked(useTenantRuntimeConfig);
const forConfig = vi.mocked(menuService.getMenuByRoleIdForRuntimeConfig);
const plain = vi.mocked(menuService.getMenuByRoleId);

let client: QueryClient;
const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={client}>{children}</QueryClientProvider>
);
const reply = (data: unknown) => ({ data, headers: {} }) as never;

beforeEach(() => {
  localStorage.setItem('user', JSON.stringify({ userId: 7, username: 'cajero', role: 3 }));
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  forConfig.mockReset();
  plain.mockReset();
});
afterEach(() => {
  client.clear();
  localStorage.clear();
});

describe('useWorkspaceMenu', () => {
  it('is loading, and asks for nothing, until the runtime config is known', () => {
    config.mockReturnValue({ data: undefined, isError: false } as never);
    const { result } = renderHook(() => useWorkspaceMenu(), { wrapper });

    expect(result.current.isLoading).toBe(true);
    expect(forConfig).not.toHaveBeenCalled();
    expect(plain).not.toHaveBeenCalled();
  });

  it("returns the role's menu narrowed by the runtime config", async () => {
    config.mockReturnValue({ data: { profile: {} }, isError: false } as never);
    forConfig.mockResolvedValue(reply([{ text: 'Punto de venta', path: '/pos' }]));
    const { result } = renderHook(() => useWorkspaceMenu(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(forConfig).toHaveBeenCalledWith(3, { profile: {} });
    expect(result.current.items).toEqual([{ text: 'Punto de venta', path: '/pos' }]);
  });

  it('falls back to the role menu when the runtime config failed to load', async () => {
    config.mockReturnValue({ data: undefined, isError: true } as never);
    plain.mockResolvedValue(reply([{ text: 'Inicio', path: '/dashboard' }]));
    const { result } = renderHook(() => useWorkspaceMenu(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(plain).toHaveBeenCalledWith(3);
    expect(result.current.items).toHaveLength(1);
  });

  it('leaves an empty menu, not an eternal spinner, when the request fails', async () => {
    config.mockReturnValue({ data: { profile: {} }, isError: false } as never);
    forConfig.mockRejectedValue(new Error('Network Error'));
    const { result } = renderHook(() => useWorkspaceMenu(), { wrapper });

    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.items).toEqual([]);
  });
});
