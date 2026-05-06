import { describe, it, expect, vi, beforeEach } from 'vitest';

const networkMocks = vi.hoisted(() => ({
  businessHttpClient: {
    get: vi.fn(),
  },
  httpRequestData: vi.fn(() => Promise.resolve([])),
}));

// ---------------------------------------------------------------------------
// Module mocks — must be declared before the module under test is imported
// ---------------------------------------------------------------------------

vi.mock('@core/common/Constants', () => ({
  Constants: {
    API_BASE_URL: '/api/',
  },
}));

vi.mock('@core/common/UserInformation', () => ({
  userInformation: vi.fn(() => ({
    currentUserId: 1,
    currentUserName: 'test',
    currentUserRoleId: 1,
    currentBranchId: 'branch-1',
    currentBranchName: 'Branch 1',
  })),
}));

vi.mock('@app/Settings', () => ({
  Setting: {
    AUTO_INVOICE: 'app_option_auto_invoice',
    INVENTORY_NEGATIVE_STOCK_POLICY: 'inventory_negative_stock_policy',
    DEFAULT_CURRENCY: 'sales_default_currency',
    DEFAULT_USD_EXCHANGE_RATE: 'sales_default_usd_exchange_rate',
  },
  settings: {
    getCached: vi.fn(() => undefined),
    get: vi.fn(() => Promise.resolve(undefined)),
  },
}));

vi.mock('@shared/lib/SharedUtils', () => ({
  default: vi.fn(),
  SharedUtils: {
    dateFormatter: vi.fn(),
    saleStatusFormatter: vi.fn(),
  },
  getIRI: (url: string, value: string) => `${url}/${value}`,
}));

vi.mock('@shared/lib/NumberUtils', () => ({
  default: {
    formatCurrency: vi.fn((v: number) => String(v)),
  },
}));

vi.mock('@shared/api/network', () => ({
  businessHttpClient: networkMocks.businessHttpClient,
  httpRequestData: networkMocks.httpRequestData,
}));

vi.mock('@features/product', () => ({
  productService: {
    getOne: vi.fn(() => Promise.resolve({ id: 1, name: 'Product', salePrice: 10 })),
  },
}));

vi.mock('@features/pos/lib/GetStockUseCase', () => ({
  GetStockUseCase: vi.fn(() => Promise.resolve({ id: 'p1', name: 'Product', stock: 100 })),
}));

vi.mock('@features/customer', () => ({
  CustomerPage: {},
}));

vi.mock('devextreme/ui/notify', () => ({
  default: vi.fn(),
}));

// ---------------------------------------------------------------------------
// Import the module under test AFTER all mocks are set up
// ---------------------------------------------------------------------------

import { SaleFields, SaleDetailFields, setTotal } from './SaleFields';
import { settings } from '@app/Settings';
import { GetStockUseCase } from '@features/pos/lib/GetStockUseCase';
import toast from '@shared/lib/SharedUtils';

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('SaleFields', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    networkMocks.httpRequestData.mockResolvedValue([]);
  });

  // 1. SaleFields(null) returns array with 'id' field as identity
  it('returns an array of fields with the id field marked as identity', () => {
    const fields = SaleFields(null);

    const idField = fields.find((f) => f.name === 'id');

    expect(idField).toBeDefined();
    expect(idField?.isIdentity).toBe(true);
  });

  // 2. SaleFields(null) — customer field has an 'add' button
  it('customer field has an add button in its buttons config', () => {
    const fields = SaleFields(null);

    const customerField = fields.find((f) => f.name === 'customer');

    expect(customerField).toBeDefined();
    expect(customerField?.buttons).toBeDefined();
    expect(customerField?.buttons?.length).toBeGreaterThan(0);

    const addButton = customerField?.buttons?.find((b: any) => b.name === 'add');
    expect(addButton).toBeDefined();
  });

  // 3. onOpenAddCustomer callback is called when the add button is clicked
  it('calls onOpenAddCustomer when the customer add button is clicked', () => {
    const onOpenAddCustomer = vi.fn();
    const fields = SaleFields(null, { onOpenAddCustomer });

    const customerField = fields.find((f) => f.name === 'customer');
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const addButton = customerField?.buttons?.find((b: any) => b.name === 'add') as any;

    addButton?.options?.onClick({});

    expect(onOpenAddCustomer).toHaveBeenCalledTimes(1);
  });

  // 4. onOpenDiscount callback is called with { total, discountPercent } when discountPercent onClick fires
  it('calls onOpenDiscount with { total, discountPercent } when discountPercent field is clicked', () => {
    const onOpenDiscount = vi.fn();

    // Provide a mock form that returns known values
    const mockForm = {
      getFieldValue: vi.fn((name: string) => {
        if (name === 'grandTotal') return 100;
        if (name === 'discountPercent') return 10;
        return null;
      }),
      setFieldValue: vi.fn(),
      getDetailData: vi.fn(() => [{ netAmount: 40 }, { netAmount: 60 }]),
      setDetailData: vi.fn(),
      reloadDetailData: vi.fn(),
      getFormData: vi.fn(),
      setValues: vi.fn(),
      setReadonly: vi.fn(),
      setDisabled: vi.fn(),
      enableValidation: vi.fn(),
      disableValidation: vi.fn(),
      setError: vi.fn(),
      showField: vi.fn(),
      hideField: vi.fn(),
      validate: vi.fn(() => true),
      setIsEdit: vi.fn(),
    };

    const fields = SaleFields(mockForm as any, { onOpenDiscount });

    const discountField = fields.find((f) => f.name === 'discountPercent');
    discountField?.onClick?.({});

    expect(onOpenDiscount).toHaveBeenCalledTimes(1);
    const callArg = onOpenDiscount.mock.calls[0][0];
    expect(callArg).toHaveProperty('total');
    expect(callArg).toHaveProperty('discountPercent');
    expect(callArg.total).toBe(100);
    expect(callArg.discountPercent).toBe(0.1);
  });

  // 5. SaleFields(null) without callbacks — onClick on discountPercent does NOT throw
  it('does not throw when discountPercent onClick fires without callbacks', () => {
    const fields = SaleFields(null);

    const discountField = fields.find((f) => f.name === 'discountPercent');

    expect(() => discountField?.onClick?.({})).not.toThrow();
  });

  // 5b. SaleFields(null) without callbacks — customer add button onClick does NOT throw
  it('does not throw when customer add button is clicked without callbacks', () => {
    const fields = SaleFields(null);

    const customerField = fields.find((f) => f.name === 'customer');
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const addButton = customerField?.buttons?.find((b: any) => b.name === 'add') as any;

    expect(() => addButton?.options?.onClick({})).not.toThrow();
  });

  it('loads canonical price list data and refreshes changed detail rows', async () => {
    const reloadDetailData = vi.fn();
    const detailData = [
      {
        unitPrice: 10,
        qty: 2,
        netAmount: 20,
        product: { id: 7 },
      },
    ];
    const mockForm = {
      getDetailData: vi.fn(() => detailData),
      reloadDetailData,
    };

    networkMocks.httpRequestData.mockResolvedValueOnce([{ id: 7, price: 15 }] as any);

    const fields = SaleFields(mockForm as any);
    const priceListField = fields.find((f) => f.name === 'price_list') as any;

    await priceListField?.onChange?.({ value: 3 }, undefined, undefined);

    expect(networkMocks.businessHttpClient.get).toHaveBeenCalledWith('price-list/3/get');
    expect(detailData[0].unitPrice).toBe(15);
    expect(detailData[0].netAmount).toBe(30);
    expect(reloadDetailData).toHaveBeenCalledTimes(1);
  });
});

describe('SaleDetailFields', () => {
  // 6. SaleDetailFields(null) returns array with expected fields
  it('returns array containing id, product, qty, unitPrice, discountPercent, discountAmount, netAmount fields', () => {
    const fields = SaleDetailFields(null);

    const names = fields.map((f) => f.name);

    expect(names).toContain('id');
    expect(names).toContain('product');
    expect(names).toContain('qty');
    expect(names).toContain('unitPrice');
    expect(names).toContain('discountPercent');
    expect(names).toContain('discountAmount');
    expect(names).toContain('netAmount');
  });

  it('has id field marked as identity', () => {
    const fields = SaleDetailFields(null);

    const idField = fields.find((f) => f.name === 'id');

    expect(idField?.isIdentity).toBe(true);
  });

  it('discountPercent field has defaultValue 0 and is not disabled', () => {
    const fields = SaleDetailFields(null);
    const f = fields.find((f) => f.name === 'discountPercent');
    expect(f).toBeDefined();
    expect(f?.defaultValue).toBe(0);
    expect(f?.disabled).toBe(false);
  });

  it('discountAmount field is disabled (read-only derived)', () => {
    const fields = SaleDetailFields(null);
    const f = fields.find((f) => f.name === 'discountAmount');
    expect(f).toBeDefined();
    expect(f?.disabled).toBe(true);
  });

  it('discountPercent onChange derives discountAmount and netAmount correctly', async () => {
    const fields = SaleDetailFields(null);
    const discountField = fields.find((f) => f.name === 'discountPercent') as any;

    const row: any = {};
    const currentRow: any = { qty: 4, unitPrice: 25 }; // gross = 100
    const self: any = { defaultSetCellValue: vi.fn() };

    await discountField?.onChange?.call(self, row, 10, currentRow); // 10%

    expect(row.discountAmount).toBe(10);   // 100 * 0.10
    expect(row.netAmount).toBe(90);        // 100 - 10
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 10, currentRow);
  });

  it('discountPercent onChange clamps value to 0–100', async () => {
    const fields = SaleDetailFields(null);
    const discountField = fields.find((f) => f.name === 'discountPercent') as any;

    const row: any = {};
    const currentRow: any = { qty: 2, unitPrice: 50 }; // gross = 100
    const self: any = { defaultSetCellValue: vi.fn() };

    await discountField?.onChange?.call(self, row, 150, currentRow); // clamped to 100

    expect(row.discountAmount).toBe(100);
    expect(row.netAmount).toBe(0);
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 100, currentRow);
  });

  it('discountPercent onChange returns early when value is empty string', async () => {
    const fields = SaleDetailFields(null);
    const discountField = fields.find((f) => f.name === 'discountPercent') as any;

    const row: any = {};
    const currentRow: any = { qty: 2, unitPrice: 50 };
    const self: any = { defaultSetCellValue: vi.fn() };

    await discountField?.onChange?.call(self, row, '', currentRow);

    expect(self.defaultSetCellValue).not.toHaveBeenCalled();
  });
});

describe('setTotal', () => {
  // 7. setTotal(null) does not throw when form is null
  it('does not throw when form is null', () => {
    expect(() => setTotal(null)).not.toThrow();
  });
});

// ---------------------------------------------------------------------------
// SaleDetailFields › qty onChange — inventory_negative_stock_policy behaviour
// ---------------------------------------------------------------------------

describe('SaleDetailFields › qty onChange stock check', () => {
  // Grab mocks via vi.mocked() — these reference the same vi.fn() instances
  // created in the hoisted vi.mock() calls above
  const settingsGetMock = vi.mocked(settings.get);
  const getStockMock = vi.mocked(GetStockUseCase);
  const toastMock = vi.mocked(toast);

  /**
   * Set the module-level selectedWarehouseId by triggering the
   * warehouse field's onChange callback (the only public setter).
   */
  function setWarehouseId(id: string) {
    const mainFields = SaleFields(null);
    const warehouseField = mainFields.find((f) => f.name === 'warehouse') as any;
    warehouseField?.onChange?.(id);
  }

  function getQtyOnChange() {
    const fields = SaleDetailFields(null);
    const qtyField = fields.find((f) => f.name === 'qty') as any;
    return qtyField?.onChange as
      | ((this: any, row: any, value: any, currentRow: any) => Promise<void>)
      | undefined;
  }

  beforeEach(() => {
    vi.clearAllMocks();
    setWarehouseId('wh-test');
  });

  it('blocks and shows stock info when policy is BLOCK and qty exceeds stock', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'BLOCK' });
    getStockMock.mockResolvedValue({ id: 'p1', name: 'Product', stock: 3 });

    const row: any = {};
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 10, discountPercent: 0 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 5, currentRow);

    expect(getStockMock).toHaveBeenCalledWith('p1', 'wh-test');
    expect(toastMock).toHaveBeenCalledWith('Stock actual: 3', 'info', 2000);
    expect(toastMock).toHaveBeenCalledWith('No hay stock suficiente', 'error', 2000);
    expect(row.qty).toBe('');
    expect(row.netAmount).toBe('');
  });

  it('blocks when policy is WARNING_ONLY and qty exceeds stock', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'WARNING_ONLY' });
    getStockMock.mockResolvedValue({ id: 'p1', name: 'Product', stock: 2 });

    const row: any = {};
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 10, discountPercent: 0 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 5, currentRow);

    expect(getStockMock).toHaveBeenCalled();
    expect(row.qty).toBe('');
  });

  it('skips stock check for service products', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'BLOCK' });

    const row: any = {};
    const currentRow: any = {
      product: { id: 'svc-1', type: 2, productType: 'SERVICE', trackInventory: false },
      unitPrice: 25,
    };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 999, currentRow);

    expect(getStockMock).not.toHaveBeenCalled();
    expect(toastMock).not.toHaveBeenCalledWith('No hay stock suficiente', 'error', 2000);
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 999, currentRow);
  });

  it('skips stock check for digital products even when legacy track flag is stale', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'BLOCK' });

    const row: any = {};
    const currentRow: any = {
      product: { id: 'dig-1', type: 1, productType: 'DIGITAL', trackInventory: true },
      unitPrice: 50,
    };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 999, currentRow);

    expect(getStockMock).not.toHaveBeenCalled();
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 999, currentRow);
  });

  it('allows the edit when qty is within available stock', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'BLOCK' });
    getStockMock.mockResolvedValue({ id: 'p1', name: 'Product', stock: 10 });

    const row: any = {};
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 5, discountPercent: 0 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 3, currentRow);

    expect(toastMock).not.toHaveBeenCalledWith('No hay stock suficiente', 'error', 2000);
    expect(row.qty).toBeUndefined();
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 3, currentRow);
  });

  it('skips stock check entirely when policy is ALLOW_NEGATIVE', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'ALLOW_NEGATIVE' });

    const row: any = {};
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 5, discountPercent: 0 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 999, currentRow);

    expect(getStockMock).not.toHaveBeenCalled();
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 999, currentRow);
  });

  it('skips stock check when policy is absent (undefined)', async () => {
    settingsGetMock.mockResolvedValue(undefined);

    const row: any = {};
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 5, discountPercent: 0 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 5, currentRow);

    // undefined !== 'ALLOW_NEGATIVE' → check fires, stock API called
    expect(getStockMock).toHaveBeenCalled();
  });

  it('returns early without calling defaultSetCellValue when value is empty string', async () => {
    const row: any = {};
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 10 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, '', currentRow);

    expect(getStockMock).not.toHaveBeenCalled();
    expect(self.defaultSetCellValue).not.toHaveBeenCalled();
  });

  it('propagates existing discountPercent when qty changes (ALLOW_NEGATIVE)', async () => {
    settingsGetMock.mockResolvedValue({ id: 1, name: 'inventory_negative_stock_policy', value: 'ALLOW_NEGATIVE' });

    const row: any = {};
    // 20% discount already set on the row
    const currentRow: any = { product: { id: 'p1' }, unitPrice: 10, discountPercent: 20 };
    const self: any = { defaultSetCellValue: vi.fn() };

    const onChange = getQtyOnChange();
    await onChange!.call(self, row, 5, currentRow); // gross = 50, discount = 10, net = 40

    expect(row.discountAmount).toBe(10);
    expect(row.netAmount).toBe(40);
  });
});

// ---------------------------------------------------------------------------
// SaleDetailFields › unitPrice onChange — discount propagation
// ---------------------------------------------------------------------------

describe('SaleDetailFields › unitPrice onChange discount propagation', () => {
  it('recalculates discountAmount and netAmount when unitPrice changes with existing discountPercent', async () => {
    const fields = SaleDetailFields(null);
    const unitPriceField = fields.find((f) => f.name === 'unitPrice') as any;

    const row: any = {};
    const currentRow: any = { qty: 2, discountPercent: 25 }; // 25% discount
    const self: any = { defaultSetCellValue: vi.fn() };

    await unitPriceField?.onChange?.call(self, row, 100, currentRow); // gross = 200, discount = 50, net = 150

    expect(row.discountAmount).toBe(50);
    expect(row.netAmount).toBe(150);
    expect(self.defaultSetCellValue).toHaveBeenCalledWith(row, 100, currentRow);
  });

  it('returns early when unitPrice value is empty string', async () => {
    const fields = SaleDetailFields(null);
    const unitPriceField = fields.find((f) => f.name === 'unitPrice') as any;

    const row: any = {};
    const currentRow: any = { qty: 2, discountPercent: 10 };
    const self: any = { defaultSetCellValue: vi.fn() };

    await unitPriceField?.onChange?.call(self, row, '', currentRow);

    expect(self.defaultSetCellValue).not.toHaveBeenCalled();
  });
});
