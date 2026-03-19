/**
 * Smoke test for POSPage module.
 *
 * The vite test environment is "node" (no jsdom), so we cannot mount the full
 * component — its hooks depend on browser APIs (fetch, localStorage, IndexedDB).
 * Instead we assert that:
 *   1. The module exports a default component (function).
 *   2. The named re-export `POSPage` is the same reference as the default.
 *
 * This catches import/export mis-wiring (e.g. the named export being undefined)
 * without requiring a DOM environment.
 *
 * vi.mock calls are hoisted by Vitest to the top of the module, so they run
 * before any import side-effects (including Settings / localStorage access).
 */
import { describe, expect, it, vi } from 'vitest';

// Hoist mocks — these run BEFORE any module-level code in the imports below.
vi.mock('@assets/Settings', () => ({
  Setting: {
    AUTO_INVOICE: 'app_option_auto_invoice',
    CHECK_STOCK: 'app_option_check_stock',
    DEFAULT_CURRENCY: 'sales_default_currency',
    DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',
  },
  settings: {
    get: vi.fn().mockResolvedValue(undefined),
    getCached: vi.fn().mockReturnValue(undefined),
    init: vi.fn().mockResolvedValue(undefined),
  },
}));

vi.mock('@offline/hooks/useConnectivity', () => ({
  useConnectivity: () => ({ isOnline: true }),
}));

vi.mock('@offline/hooks/useOutboxSync', () => ({
  useOutboxSync: () => ({
    pendingSalesCount: 0,
    isSyncing: false,
    recentSales: [],
    retrySale: vi.fn(),
    refresh: vi.fn(),
    processNow: vi.fn(),
  }),
}));

vi.mock('@features/sale', () => ({
  createQzInvoicePrinter: () => ({
    initialize: vi.fn().mockResolvedValue(undefined),
    printSaleInvoice: vi.fn().mockResolvedValue(undefined),
  }),
}));

vi.mock('@core/common/UserInformation', () => ({
  userInformation: () => ({
    currentBranchId: '1',
    currentBranchName: 'Test Branch',
    currentUserName: 'Test User',
  }),
}));

vi.mock('./usePosCustomers', () => ({
  usePosCustomers: () => ({
    customerSearch: '',
    setCustomerSearch: vi.fn(),
    selectedCustomerId: null,
    setSelectedCustomerId: vi.fn(),
    clearSelectedCustomer: vi.fn(),
    customerOptions: [],
    customersLoading: false,
    customersHasMore: false,
    customersPage: 1,
    deferredCustomerSearch: '',
    loadCustomers: vi.fn(),
  }),
}));

vi.mock('./usePosCheckout', () => ({
  usePosCheckout: () => ({
    cart: [],
    amountReceived: '',
    setAmountReceived: vi.fn(),
    discountPercent: '',
    setDiscountPercent: vi.fn(),
    note: '',
    setNote: vi.fn(),
    isProcessingSale: false,
    lastSaleCode: null,
    emitInvoice: false,
    setEmitInvoice: vi.fn(),
    currency: 'PEN',
    setCurrency: vi.fn(),
    exchangeRate: '',
    setExchangeRate: vi.fn(),
    addToCart: vi.fn(),
    removeFromCart: vi.fn(),
    updateQuantity: vi.fn(),
    clearCart: vi.fn(),
    cartItemsCount: 0,
    subtotal: 0,
    discountAmount: 0,
    total: 0,
    includedTax: 0,
    amountReceivedNumber: 0,
    changeAmount: 0,
    amountMissing: 0,
    quickCashActions: [],
    checkoutDisabled: true,
    handleCheckout: vi.fn(),
    getEffectiveStock: vi.fn().mockReturnValue(0),
  }),
}));

vi.mock('./usePosCatalogData', () => ({
  usePosCatalogData: () => ({
    loading: false,
    loadError: null,
    categories: [],
    menuData: [],
    usingOfflineCatalog: false,
    lastUpdatedAt: null,
    paymentMethods: [],
    selectedPaymentMethodId: null,
    setSelectedPaymentMethodId: vi.fn(),
    warehouses: [],
    selectedWarehouseId: null,
    setSelectedWarehouseId: vi.fn(),
  }),
}));

import POSPageDefault, { POSPage } from './POSPage';

describe('POSPage module (smoke)', () => {
  it('exports a default component that is a function', () => {
    expect(typeof POSPageDefault).toBe('function');
  });

  it('named export POSPage is the same reference as the default export', () => {
    expect(POSPage).toBe(POSPageDefault);
  });
});
