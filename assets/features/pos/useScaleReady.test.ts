import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { notifyHardwareConfigChanged } from './hardwareAgentEvents';
import { useScaleReady } from './useScaleReady';
import { fetchScaleStatus } from '@features/sales/lib/localAgentClient';

vi.mock('@features/sales/lib/localAgentClient', () => ({
  fetchScaleStatus: vi.fn(),
}));

afterEach(() => vi.clearAllMocks());

describe('useScaleReady', () => {
  it('refreshes readiness after hardware configuration changes', async () => {
    vi.mocked(fetchScaleStatus)
      .mockResolvedValueOnce({
        enabled: false,
        connected: false,
        protocol: 'generic',
        port: null,
      })
      .mockResolvedValueOnce({
        enabled: true,
        connected: true,
        protocol: 'generic',
        port: 'COM5',
      });

    const { result } = renderHook(() => useScaleReady());
    await waitFor(() => expect(fetchScaleStatus).toHaveBeenCalledTimes(1));
    expect(result.current).toBe(false);

    act(() => notifyHardwareConfigChanged());

    await waitFor(() => expect(result.current).toBe(true));
    expect(fetchScaleStatus).toHaveBeenCalledTimes(2);
  });
});
