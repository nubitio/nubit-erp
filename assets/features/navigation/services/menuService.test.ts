import { afterEach, describe, expect, it } from 'vitest';
import type { AdminMenuItem } from '@nubitio/admin';
import type { TenantRuntimeConfig } from '@shared/hooks';
import { filterMenuByRuntimeConfig, normalizeMenuDestinations } from './menuService';

function runtimeConfig(
  navigationTier: TenantRuntimeConfig['profile']['navigationTier'],
  overrides: Partial<
    TenantRuntimeConfig['ui']['inventory'] & TenantRuntimeConfig['ui']['reports']
  > = {},
  capabilities: TenantRuntimeConfig['capabilities'] = {},
): TenantRuntimeConfig {
  return {
    profile: {
      preset: 'retail',
      vertical: 'retail',
      simpleStoreMode: false,
      navigationTier,
      onboardingCompleted: true,
    },
    counts: { branches: 1, warehouses: 1 },
    defaults: { branch: null, warehouse: null },
    capabilities,
    settings: {
      requireCashRegister: false,
      autoInvoice: true,
      autoPrint: true,
      cashRoundingEnabled: false,
      printMethod: 'WEBUSB',
      defaultCurrency: 'PEN',
      negativeStockPolicy: 'BLOCK',
    },
    ui: {
      shell: { showBranchPicker: false },
      inventory: {
        showWarehouseSelector: false,
        showWarehouseManagement: overrides.showWarehouseManagement ?? false,
        showTransfers: overrides.showTransfers ?? false,
        showLotFields: false,
      },
      pos: { allowLinePriceOverride: true, allowCatalogPriceEdit: true },
      reports: {
        showAdvancedReports: overrides.showAdvancedReports ?? false,
        showExcelExport: overrides.showExcelExport ?? false,
      },
    },
  };
}

function menu(paths: string[]): AdminMenuItem[] {
  return paths.map((path) => ({ path }) as AdminMenuItem);
}

function visiblePaths(items: AdminMenuItem[]): string[] {
  return items.map((item) => String(item.path));
}

afterEach(() => {
  localStorage.clear();
});

describe('normalizeMenuDestinations', () => {
  it('roots menu and nested destinations so they cannot resolve relative to the current page', () => {
    expect(
      normalizeMenuDestinations([
        { text: 'POS', path: 'pos' },
        { text: 'Grupo', path: '', items: [{ text: 'Compras', path: 'purchases' }] },
        { text: 'Ya absoluto', path: '/billing/sales' },
      ] as AdminMenuItem[]),
    ).toEqual([
      { text: 'POS', path: '/pos' },
      { text: 'Grupo', path: '', items: [{ text: 'Compras', path: '/purchases' }] },
      { text: 'Ya absoluto', path: '/billing/sales' },
    ]);
  });
});

describe('filterMenuByRuntimeConfig', () => {
  it('gives every plan the full day-to-day menu and keeps the specialised areas out', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['dashboard', 'pos', 'billing', 'catalog', 'purchases', 'inventory', 'ple', 'config']),
      runtimeConfig('standard'),
    );

    expect(visiblePaths(filtered)).toEqual([
      'dashboard',
      'pos',
      'billing',
      'catalog',
      'inventory',
      'purchases',
      'config',
    ]);
  });

  it('puts the actions an owner performs every day before specialised areas', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['config', 'catalog', 'billing', 'pos', 'dashboard']),
      runtimeConfig('advanced'),
    );

    expect(visiblePaths(filtered)).toEqual(['dashboard', 'pos', 'billing', 'catalog', 'config']);
  });

  it('uses the first daily action of a menu group to order that group', () => {
    const filtered = filterMenuByRuntimeConfig(
      [
        { path: 'config' },
        { path: '', items: [{ path: 'purchases' }] as AdminMenuItem['items'] },
        { path: 'pos' },
      ] as AdminMenuItem[],
      runtimeConfig('advanced'),
    );

    expect(visiblePaths(filtered)).toEqual(['pos', '', 'config']);
  });

  it('shows standard entries but still hides advanced PLE without reports entitlement', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['billing', 'catalog', 'purchases', 'inventory', 'ple']),
      runtimeConfig('standard'),
    );

    expect(visiblePaths(filtered)).toEqual(['billing', 'catalog', 'inventory', 'purchases']);
  });

  it('shows PLE for advanced tenants with advanced reports enabled', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['billing', 'catalog', 'purchases', 'inventory', 'ple']),
      runtimeConfig('advanced', { showAdvancedReports: true }),
    );

    expect(visiblePaths(filtered)).toEqual(['billing', 'catalog', 'inventory', 'purchases', 'ple']);
  });

  it('removes deprecated reports and capability-gated warehouse/transfer paths', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['reports', 'warehouse', 'inventory/warehouses', 'transfer', 'billing']),
      runtimeConfig('advanced', { showAdvancedReports: true }),
    );

    expect(visiblePaths(filtered)).toEqual(['billing']);
  });

  it('keeps parent menu groups when at least one child remains', () => {
    const filtered = filterMenuByRuntimeConfig(
      [
        {
          path: '',
          items: [{ path: 'reports' }, { path: 'billing' }] as AdminMenuItem['items'],
        } as AdminMenuItem,
      ],
      runtimeConfig('standard'),
    );

    expect(filtered).toHaveLength(1);
    expect(filtered[0].items?.map((item) => item.path)).toEqual(['billing']);
  });

  it('hides unfinished ERP modules unless their capability is enabled', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu([
        'pos',
        'billing',
        'subscriptions',
        'employees',
        'payroll-runs',
        'bank-reconciliation',
        'fixed-assets',
        'expense-reports',
        'financial-reports',
        'accounting',
        'sale-quotes',
      ]),
      runtimeConfig('advanced', { showAdvancedReports: true }),
    );

    expect(visiblePaths(filtered)).toEqual(['pos', 'billing', 'sale-quotes']);
  });

  it('shows payroll when the payroll capability is enabled', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['employees', 'payroll-runs', 'pos']),
      runtimeConfig('advanced', {}, { 'payroll.core': { enabled: true, config: {} } }),
    );

    expect(visiblePaths(filtered)).toEqual(['pos', 'employees', 'payroll-runs']);
  });

  it('hides restaurant navigation from retail tenants even when their plan includes the module', () => {
    const restaurantGroup: AdminMenuItem[] = [
      {
        text: 'Restaurante',
        path: '',
        items: [
          { text: 'Mesas', path: '/restaurant/mesas' },
          { text: 'Cocina', path: '/restaurant/kds' },
        ],
      },
    ];
    const retailConfig = {
      ...runtimeConfig(
        'standard',
        {},
        {
          'restaurant.table_management': { enabled: true, config: {} },
        },
      ),
      profile: {
        ...runtimeConfig('standard').profile,
        vertical: 'retail',
        preset: 'retail',
      },
    };

    expect(filterMenuByRuntimeConfig(restaurantGroup, retailConfig)).toEqual([]);
  });

  it('shows restaurant navigation only for the restaurant vertical with its capability enabled', () => {
    const restaurantGroup: AdminMenuItem[] = [
      {
        text: 'Restaurante',
        path: '',
        items: [
          { text: 'Mesas', path: '/restaurant/mesas' },
          { text: 'Cocina', path: '/restaurant/kds' },
        ],
      },
    ];
    const baseConfig = runtimeConfig(
      'standard',
      {},
      {
        'restaurant.table_management': { enabled: true, config: {} },
      },
    );
    const restaurantConfig = {
      ...baseConfig,
      profile: { ...baseConfig.profile, vertical: 'restaurant', preset: 'restaurant' },
    };

    expect(filterMenuByRuntimeConfig(restaurantGroup, restaurantConfig)[0]?.items).toHaveLength(2);
    expect(
      filterMenuByRuntimeConfig(restaurantGroup, {
        ...restaurantConfig,
        capabilities: {},
      }),
    ).toEqual([]);
  });

  it('treats receivables as sales and keeps supplier payables separately authorized', () => {
    localStorage.setItem(
      'user',
      JSON.stringify({
        username: 'seller',
        permissions: ['sales.view'],
      }),
    );

    const filtered = filterMenuByRuntimeConfig(
      menu(['accounts-receivable', 'accounts-payable']),
      runtimeConfig('advanced'),
    );

    expect(visiblePaths(filtered)).toEqual(['accounts-receivable']);
  });

  describe('cash screens (Cajas y turnos)', () => {
    const cashMenu = () => menu(['cashbox']);
    const asUser = (permissions: string[]) =>
      localStorage.setItem('user', JSON.stringify({ username: 'u', permissions }));

    it('hides them from a cashier: they open, move and close the drawer from the POS', () => {
      asUser(['cash.view', 'cash.open', 'cash.close', 'pos.access']);
      expect(
        visiblePaths(filterMenuByRuntimeConfig(cashMenu(), runtimeConfig('standard'))),
      ).toEqual([]);
    });

    it.each([['cash.manage'], ['cash.close_any'], ['cash.movement_any'], ['treasury.view']])(
      'shows them to whoever supervises the drawers (%s)',
      (supervisory) => {
        asUser(['cash.view', 'pos.access', supervisory]);
        expect(
          visiblePaths(filterMenuByRuntimeConfig(cashMenu(), runtimeConfig('standard'))),
        ).toEqual(['cashbox']);
      },
    );

    it('keeps them for a role that cannot reach the POS, which has no other way to open a register', () => {
      asUser(['cash.view', 'cash.open']);
      expect(
        visiblePaths(filterMenuByRuntimeConfig(cashMenu(), runtimeConfig('standard'))),
      ).toEqual(['cashbox']);
    });

    it('hides them from whoever cannot view the cash at all', () => {
      asUser(['treasury.view']);
      expect(
        visiblePaths(filterMenuByRuntimeConfig(cashMenu(), runtimeConfig('standard'))),
      ).toEqual([]);
    });
  });

  it('turns Facturación into a group with all its hub sections, on every plan', () => {
    const billing: AdminMenuItem[] = [{ text: 'Facturación', path: 'billing', items: [] }];

    const standard = filterMenuByRuntimeConfig(billing, runtimeConfig('standard'));
    expect(standard[0].items?.map((item) => item.path)).toEqual([
      '/billing/sales',
      '/billing/consult',
      '/billing/documents',
      '/billing/credit-notes',
    ]);
  });

  it('lists the other hubs as sub-items and follows the tab visibility rules', () => {
    const menu: AdminMenuItem[] = [
      { text: 'Catálogo', path: 'catalog', items: [] },
      { text: 'Inventario', path: 'inventory', items: [] },
    ];

    const standard = filterMenuByRuntimeConfig(menu, runtimeConfig('standard'));
    const paths = (key: string) =>
      standard.find((item) => item.path === key)?.items?.map((child) => child.path);
    // Catalog setup is part of every plan: a shop must be able to define its categories, units and prices.
    expect(paths('catalog')).toEqual([
      '/catalog/products',
      '/catalog/categories',
      '/catalog/units',
      '/catalog/prices',
      '/catalog/suppliers',
      '/catalog/payment-methods',
    ]);
    expect(
      standard.find((item) => item.path === 'inventory')?.items?.map((child) => child.path),
    ).toEqual([
      '/inventory/stock',
      '/inventory/movements',
      '/inventory/operations',
      '/inventory/reorder',
    ]);

    const withWarehouses = filterMenuByRuntimeConfig(
      menu,
      runtimeConfig('standard', { showWarehouseManagement: true }),
    );
    expect(
      withWarehouses.find((item) => item.path === 'inventory')?.items?.map((child) => child.path),
    ).toContain('/inventory/warehouses');
  });
});

describe('Administración group', () => {
  const administration = [
    {
      text: 'Administración',
      items: [
        { text: 'Usuarios', path: '/config/users' },
        { text: 'Roles', path: '/config/roles' },
        { text: 'Actividad', path: '/config/activity' },
      ],
    },
  ] as unknown as AdminMenuItem[];
  const childPaths = (items: AdminMenuItem[]) => items[0]?.items?.map((child) => child.path);

  it('is part of every plan, for whoever holds the permissions', () => {
    expect(
      childPaths(filterMenuByRuntimeConfig(administration, runtimeConfig('standard'))),
    ).toEqual(['/config/users', '/config/roles', '/config/activity']);
  });

  it('offers each page only to who holds its permission', () => {
    localStorage.setItem(
      'user',
      JSON.stringify({ username: 'auditor', permissions: ['activity.view'] }),
    );
    const filtered = filterMenuByRuntimeConfig(administration, runtimeConfig('standard'));
    expect(childPaths(filtered)).toEqual(['/config/activity']);
  });
});
