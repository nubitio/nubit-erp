// @vitest-environment jsdom
/**
 * Tests for the enforceStockCheck behaviour in usePosCheckout.
 *
 * Focused on:
 *   - addToCart: blocks when enforceStockCheck=true and effectiveStock <= 0
 *   - addToCart: allows when enforceStockCheck=false, regardless of stock
 *   - addToCart: allows when trackInventory=false, regardless of stock
 *   - addToCart: blocks adding more than available stock when already in cart
 *   - updateQuantity: caps at effectiveStock when enforceStockCheck=true
 *   - updateQuantity: allows any qty when enforceStockCheck=false
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';

// ── hoist mutable references so they're available inside vi.mock factories ──

const { toastMock, fetchWarehouseStockMock } = vi.hoisted(() => ({
  toastMock: vi.fn(),
  fetchWarehouseStockMock: vi.fn(),
}));

// ── module mocks ─────────────────────────────────────────────────────────────

vi.mock('@shared/lib/SharedUtils', () => ({
  default: toastMock,
  getIRI: (base: string, id: string) => `${base}${id}`,
}));

vi.mock('@core/common/Constants', () => ({
  Constants: { API_BASE_URL: '/api/' },
}));

vi.mock('./useWarehouseStock', () => ({
  useWarehouseStock: () => ({
    fetchWarehouseStock: fetchWarehouseStockMock,
    getEffectiveStock: vi.fn(),
    warehouseStockByProductId: {},
  }),
}));

vi.mock('@offline/outbox', () => ({
  createLocalSaleAndEnqueue: vi.fn(),
}));

vi.mock('@offline/db', () => ({
  offlineDb: { sales: { get: vi.fn() } },
}));

vi.mock('@shared/hooks/useCurrencySettings', () => ({
  useCurrencySettings: () => ({
    currency: 'PEN',
    exchangeRate: '1.000000',
    setCurrency: vi.fn(),
    setExchangeRate: vi.fn(),
  }),
}));

import { usePosCheckout } from './usePosCheckout';
import type { PosItem } from './types';

// ── helpers ──────────────────────────────────────────────────────────────────

function makePosItem(overrides: Partial<PosItem> = {}): PosItem {
  return {
    id: 'prod-1',
    name: 'Widget',
    salePrice: 10,
    stock: 5,
    trackInventory: true,
    category: { id: 'cat-1', name: 'General' },
    ...overrides,
  };
}

const defaultParams = {
  tenantId: 'tenant-1',
  storeId: 'store-1',
  selectedWarehouseId: 'wh-1',
  selectedCustomerId: null,
  selectedPaymentMethodId: null,
  isCashPaymentSelected: false,
  visibleProducts: [],
  isOnline: true,
  onQueuedSaleCreated: vi.fn(),
  printSaleInvoice: vi.fn(),
};

// ── tests ─────────────────────────────────────────────────────────────────────

describe('usePosCheckout › enforceStockCheck', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    // Default: fetchWarehouseStock returns adequate stock
    fetchWarehouseStockMock.mockResolvedValue(5);
  });

  // ── addToCart — enforceStockCheck = true ────────────────────────────────

  it('blocks addToCart when effectiveStock is 0 and enforceStockCheck is true', async () => {
    fetchWarehouseStockMock.mockResolvedValue(0);

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: true }),
    );

    await act(async () => {
      await result.current.addToCart(makePosItem({ stock: 0 }));
    });

    expect(result.current.cart).toHaveLength(0);
    expect(toastMock).toHaveBeenCalledWith(expect.stringContaining('sin stock'), 'warning');
  });

  it('blocks addToCart when effectiveStock is negative and enforceStockCheck is true', async () => {
    fetchWarehouseStockMock.mockResolvedValue(-3);

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: true }),
    );

    await act(async () => {
      await result.current.addToCart(makePosItem({ stock: -3 }));
    });

    expect(result.current.cart).toHaveLength(0);
    expect(toastMock).toHaveBeenCalledWith(expect.stringContaining('sin stock'), 'warning');
  });

  it('allows addToCart when enforceStockCheck is false even with zero stock', async () => {
    fetchWarehouseStockMock.mockResolvedValue(0);

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: false }),
    );

    await act(async () => {
      await result.current.addToCart(makePosItem({ stock: 0 }));
    });

    expect(result.current.cart).toHaveLength(1);
    expect(toastMock).not.toHaveBeenCalledWith(expect.stringContaining('sin stock'), 'warning');
  });

  it('allows addToCart when trackInventory is false regardless of stock', async () => {
    fetchWarehouseStockMock.mockResolvedValue(0);

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: true }),
    );

    await act(async () => {
      await result.current.addToCart(makePosItem({ stock: 0, trackInventory: false }));
    });

    expect(result.current.cart).toHaveLength(1);
  });

  it('blocks adding a second unit when cart qty already equals effectiveStock', async () => {
    fetchWarehouseStockMock.mockResolvedValue(1);
    const item = makePosItem({ id: 'prod-cap', stock: 1 });

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: true }),
    );

    // First add succeeds (qty=1 == stock=1, allowed because 1 <= 1)
    await act(async () => {
      await result.current.addToCart(item);
    });
    expect(result.current.cart).toHaveLength(1);
    expect(result.current.cart[0].quantity).toBe(1);

    // Second add is rejected (would push qty to 2 > stock=1)
    await act(async () => {
      await result.current.addToCart(item);
    });
    expect(result.current.cart[0].quantity).toBe(1);
    expect(toastMock).toHaveBeenCalledWith(
      expect.stringContaining('Stock máximo alcanzado'),
      'warning',
    );
  });

  it('allows adding multiple units when stock is sufficient', async () => {
    fetchWarehouseStockMock.mockResolvedValue(10);
    const item = makePosItem({ id: 'prod-multi', stock: 10 });

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: true }),
    );

    await act(async () => {
      await result.current.addToCart(item);
      await result.current.addToCart(item);
      await result.current.addToCart(item);
    });

    expect(result.current.cart[0].quantity).toBe(3);
  });

  // ── updateQuantity — enforceStockCheck = true ───────────────────────────

  it('caps updateQuantity at effectiveStock when enforceStockCheck is true', async () => {
    fetchWarehouseStockMock.mockResolvedValue(3);
    const item = makePosItem({ id: 'prod-cap2', stock: 3 });

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: true }),
    );

    await act(async () => {
      await result.current.addToCart(item);
    });

    await act(async () => {
      await result.current.updateQuantity('prod-cap2', 10);
    });

    const cartItem = result.current.cart.find((c) => c.id === 'prod-cap2');
    expect(cartItem?.quantity).toBe(3);
    expect(toastMock).toHaveBeenCalledWith(
      expect.stringContaining('Stock máximo alcanzado'),
      'warning',
    );
  });

  it('allows any quantity with updateQuantity when enforceStockCheck is false', async () => {
    fetchWarehouseStockMock.mockResolvedValue(3);
    const item = makePosItem({ id: 'prod-free', stock: 3 });

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: false }),
    );

    await act(async () => {
      await result.current.addToCart(item);
    });

    await act(async () => {
      await result.current.updateQuantity('prod-free', 99);
    });

    const cartItem = result.current.cart.find((c) => c.id === 'prod-free');
    expect(cartItem?.quantity).toBe(99);
  });

  it('removes item from cart when updateQuantity is called with 0', async () => {
    fetchWarehouseStockMock.mockResolvedValue(5);
    const item = makePosItem({ id: 'prod-remove', stock: 5 });

    const { result } = renderHook(() =>
      usePosCheckout({ ...defaultParams, enforceStockCheck: false }),
    );

    await act(async () => {
      await result.current.addToCart(item);
    });
    expect(result.current.cart).toHaveLength(1);

    await act(async () => {
      await result.current.updateQuantity('prod-remove', 0);
    });
    expect(result.current.cart).toHaveLength(0);
  });
});
