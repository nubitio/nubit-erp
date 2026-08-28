import { describe, expect, it } from 'vitest';
import type { AdminMenuItem } from '@nubitio/admin';
import type { TenantRuntimeConfig } from '@shared/hooks';
import { filterMenuByRuntimeConfig } from './menuService';

function runtimeConfig(
  navigationTier: TenantRuntimeConfig['profile']['navigationTier'],
  overrides: Partial<TenantRuntimeConfig['ui']['inventory'] & TenantRuntimeConfig['ui']['reports']> = {},
  capabilities: TenantRuntimeConfig['capabilities'] = {},
): TenantRuntimeConfig {
  return {
    profile: {
      preset: 'retail',
      vertical: 'retail',
      simpleStoreMode: navigationTier === 'basic',
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

describe('filterMenuByRuntimeConfig', () => {
  it('keeps only basic menu entries for basic tenants', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['dashboard', 'pos', 'billing', 'catalog', 'purchases', 'inventory', 'ple', 'config']),
      runtimeConfig('basic'),
    );

    expect(visiblePaths(filtered)).toEqual(['dashboard', 'pos', 'billing', 'catalog', 'config']);
  });

  it('puts the actions an owner performs every day before specialised areas', () => {
    const filtered = filterMenuByRuntimeConfig(
      menu(['config', 'catalog', 'billing', 'cashbox', 'pos', 'dashboard']),
      runtimeConfig('advanced'),
    );

    expect(visiblePaths(filtered)).toEqual(['dashboard', 'pos', 'cashbox', 'billing', 'catalog', 'config']);
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
      runtimeConfig('basic'),
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
});
