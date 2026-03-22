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
    CHECK_STOCK: 'app_option_check_stock',
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
  GetStockUseCase: vi.fn(() => Promise.resolve({ stock: 100 })),
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
      getDetailData: vi.fn(() => []),
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
  it('returns array containing id, product, qty, unitPrice, netAmount fields', () => {
    const fields = SaleDetailFields(null);

    const names = fields.map((f) => f.name);

    expect(names).toContain('id');
    expect(names).toContain('product');
    expect(names).toContain('qty');
    expect(names).toContain('unitPrice');
    expect(names).toContain('netAmount');
  });

  it('has id field marked as identity', () => {
    const fields = SaleDetailFields(null);

    const idField = fields.find((f) => f.name === 'id');

    expect(idField?.isIdentity).toBe(true);
  });
});

describe('setTotal', () => {
  // 7. setTotal(null) does not throw when form is null
  it('does not throw when form is null', () => {
    expect(() => setTotal(null)).not.toThrow();
  });
});
