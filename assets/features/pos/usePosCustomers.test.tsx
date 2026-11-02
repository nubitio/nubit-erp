import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { httpClient } from '@shared/api/HttpClient';
import { usePosCustomers } from './usePosCustomers';

vi.mock('@shared/api/HttpClient', () => ({ httpClient: { get: vi.fn() } }));

const walkIn = { id: 1, ruc: '00000000', businessName: 'Cliente General' };

/** A walk-in lookup whose response the test releases by hand. */
function deferredWalkIn() {
  let release!: () => void;
  vi.mocked(httpClient.get).mockImplementation(
    (url: string) =>
      new Promise((resolve) => {
        if (String(url).includes('ruc=00000000')) release = () => resolve({ data: [walkIn] });
        else resolve({ data: walkIn });
      }) as never,
  );
  return () => release();
}

afterEach(() => {
  vi.clearAllMocks();
  document.body.innerHTML = '';
});

describe('usePosCustomers walk-in default', () => {
  it('applies the walk-in customer when nothing else happened', async () => {
    const release = deferredWalkIn();
    const { result } = renderHook(() => usePosCustomers());
    await waitFor(() => expect(httpClient.get).toHaveBeenCalled());
    await act(async () => release());
    await waitFor(() => expect(result.current.selectedCustomerId).toBe(1));
  });

  it('does not apply it after the cashier cleared the field', async () => {
    const release = deferredWalkIn();
    const { result } = renderHook(() => usePosCustomers());
    await waitFor(() => expect(httpClient.get).toHaveBeenCalled());
    act(() => result.current.clearSelectedCustomer());
    await act(async () => release());
    expect(result.current.selectedCustomerId).toBeNull();
  });

  it('does not apply it while the cashier is typing in the customer field', async () => {
    document.body.innerHTML = '<div class="pos-customer-section"><input id="c" /></div>';
    const release = deferredWalkIn();
    const { result } = renderHook(() => usePosCustomers());
    await waitFor(() => expect(httpClient.get).toHaveBeenCalled());
    document.getElementById('c')!.focus();
    await act(async () => release());
    expect(result.current.selectedCustomerId).toBeNull();
  });
});
