import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import type { TenantRuntimeConfig } from '@shared/hooks';
import { hasPermission } from '@shared/permissions';
import { getSessionUser } from '@shared/sessionInfo';
import type { AdminMenuItem } from '@nubitio/admin';

const API = getCoreApiBaseUrl();

type AdminMenuSubItem = NonNullable<AdminMenuItem['items']>[number];

const WAREHOUSE_PATHS = new Set(['warehouse', 'inventory/warehouses']);
const TRANSFER_PATHS = new Set(['transfer']);
const DEPRECATED_MENU_PATHS = new Set(['reports']);
const ADVANCED_REPORT_PATHS = new Set(['ple']);

// The server is the source of truth for authorization, but hiding unavailable
// destinations prevents a user from being invited into a screen that will
// inevitably return a 403.  Keeping this mapping next to the menu policy also
// makes the commercial navigation reflect the plan and role actually bought.
const MENU_VIEW_PERMISSION: Record<string, string> = {
  billing: 'sales.view',
  pos: 'pos.access',
  purchases: 'purchases.view',
  'purchase-orders': 'purchase_orders.view',
  catalog: 'catalog.view',
  inventory: 'inventory.view',
  customer: 'customers.view',
  cashbox: 'cash.view',
  subscriptions: 'subscriptions.view',
  'accounts-payable': 'accounts_payable.view',
  // Receivables are outstanding customer sales, not supplier payables.
  'accounts-receivable': 'sales.view',
  treasury: 'treasury.view',
  'finance-summary': 'treasury.view',
  'bank-reconciliation': 'bank_reconciliation.view',
  'financial-reports': 'reports.view',
  accounting: 'accounting.view',
  'expense-reports': 'expense_reports.view',
  'fixed-assets': 'fixed_assets.view',
  'sale-quotes': 'sale_quotes.view',
  employees: 'payroll.view',
  'payroll-runs': 'payroll.view',
  ple: 'reports.ple',
  config: 'settings.view',
  'setup/import': 'settings.view',
};

const NAVIGATION_TIER_RANK: Record<TenantRuntimeConfig['profile']['navigationTier'], number> = {
  basic: 0,
  standard: 1,
  advanced: 2,
};

const MENU_MIN_TIER = new Map<string, TenantRuntimeConfig['profile']['navigationTier']>([
  ['purchases', 'standard'],
  ['inventory', 'standard'],
  ['ple', 'advanced'],
  ['subscriptions', 'advanced'],
  ['expense-reports', 'advanced'],
]);

const MENU_REQUIRED_CAPABILITY: Record<string, string> = {
  subscriptions: 'billing.recurring',
  employees: 'payroll.core',
  'payroll-runs': 'payroll.core',
  'afp-rates': 'payroll.core',
  treasury: 'treasury.accounts',
  'finance-summary': 'treasury.accounts',
  'bank-reconciliation': 'treasury.bank_reconciliation',
  'fixed-assets': 'fixed_assets.tracking',
  'expense-reports': 'expense_reports.core',
  'financial-reports': 'reporting.advanced',
  accounting: 'accounting.general_ledger',
};

// Owners open the system to operate, not to browse an ERP sitemap. Keep the
// daily sequence first and leave specialised/administrative areas afterwards.
const DAILY_OPERATION_ORDER = [
  'dashboard',
  'pos',
  'cashbox',
  'billing',
  'customer',
  'catalog',
  'inventory',
  'purchases',
  'purchase-orders',
  'accounts-receivable',
  'accounts-payable',
  'sale-quotes',
  'bank-reconciliation',
  'financial-reports',
  'accounting',
  'employees',
  'payroll-runs',
  'config',
] as const;

const DAILY_OPERATION_INDEX: ReadonlyMap<string, number> = new Map(
  DAILY_OPERATION_ORDER.map((path, index) => [path, index]),
);

function menuItemSortIndex(item: AdminMenuItem): number {
  const ownIndex = DAILY_OPERATION_INDEX.get(normalizePath(item.path));
  if (ownIndex !== undefined) return ownIndex;

  return Math.min(
    ...(item.items ?? []).map(
      (child) => DAILY_OPERATION_INDEX.get(normalizePath(child.path)) ?? Number.MAX_SAFE_INTEGER,
    ),
  );
}

// Hub sections shown as sidebar sub-items. The hub pages stay routable, so bookmarks and
// RBAC are unchanged; the in-page tab row is hidden on desktop while the sidebar is open.
// `when` mirrors the visibility rule of the matching tab in each *HubLayout.
type HubSubmenuEntry = {
  text: string;
  path: string;
  when?: (runtimeConfig: TenantRuntimeConfig) => boolean;
};

const atLeastStandard = (runtimeConfig: TenantRuntimeConfig) =>
  NAVIGATION_TIER_RANK[runtimeConfig.profile.navigationTier ?? 'basic'] >=
  NAVIGATION_TIER_RANK.standard;

const HUB_SUBMENUS: Record<string, HubSubmenuEntry[]> = {
  billing: [
    { text: 'Ventas', path: '/billing/sales' },
    { text: 'Consultas', path: '/billing/consult', when: atLeastStandard },
    { text: 'Comprobantes', path: '/billing/documents' },
    { text: 'Notas de crédito', path: '/billing/credit-notes', when: atLeastStandard },
  ],
  catalog: [
    { text: 'Productos', path: '/catalog/products' },
    { text: 'Categorías', path: '/catalog/categories', when: atLeastStandard },
    { text: 'Unidades', path: '/catalog/units', when: atLeastStandard },
    { text: 'Precios', path: '/catalog/prices', when: atLeastStandard },
    { text: 'Proveedores', path: '/catalog/suppliers', when: atLeastStandard },
    { text: 'Métodos de pago', path: '/catalog/payment-methods', when: atLeastStandard },
  ],
  cashbox: [
    { text: 'Cajas', path: '/cashbox/dashboard' },
    { text: 'Registros', path: '/cashbox/registers' },
    { text: 'Categorías', path: '/cashbox/categories' },
  ],
  inventory: [
    { text: 'Existencias', path: '/inventory/stock' },
    { text: 'Movimientos', path: '/inventory/movements', when: atLeastStandard },
    { text: 'Operaciones', path: '/inventory/operations' },
    { text: 'Reorden', path: '/inventory/reorder' },
    {
      text: 'Almacenes',
      path: '/inventory/warehouses',
      when: (runtimeConfig) => runtimeConfig.ui.inventory.showWarehouseManagement === true,
    },
  ],
  subscriptions: [
    { text: 'Planes', path: '/subscriptions/plans' },
    { text: 'Suscripciones de clientes', path: '/subscriptions/customer-subscriptions' },
    { text: 'Emisiones', path: '/subscriptions/runs' },
    { text: 'Líneas', path: '/subscriptions/lines' },
  ],
};

function hubSubItems(path: string, runtimeConfig: TenantRuntimeConfig): AdminMenuSubItem[] {
  return (HUB_SUBMENUS[path] ?? [])
    .filter((entry) => !entry.when || entry.when(runtimeConfig))
    .map(({ text, path: childPath }) => ({ text, path: childPath }));
}

function normalizePath(path: unknown): string {
  return String(path ?? '')
    .replace(/^\/+/, '')
    .toLowerCase();
}

function isAllowedMenuPath(path: string, runtimeConfig: TenantRuntimeConfig): boolean {
  const minTier = MENU_MIN_TIER.get(path);
  const currentTier = runtimeConfig.profile.navigationTier ?? 'basic';

  if (minTier && NAVIGATION_TIER_RANK[currentTier] < NAVIGATION_TIER_RANK[minTier]) {
    return false;
  }

  if (!runtimeConfig.ui.inventory.showWarehouseManagement && WAREHOUSE_PATHS.has(path)) {
    return false;
  }

  if (!runtimeConfig.ui.inventory.showTransfers && TRANSFER_PATHS.has(path)) {
    return false;
  }

  if (DEPRECATED_MENU_PATHS.has(path)) {
    return false;
  }

  if (!runtimeConfig.ui.reports.showAdvancedReports && ADVANCED_REPORT_PATHS.has(path)) {
    return false;
  }

  const requiredCapability = MENU_REQUIRED_CAPABILITY[path];
  if (requiredCapability && runtimeConfig.capabilities[requiredCapability]?.enabled !== true) {
    return false;
  }

  const requiredPermission = MENU_VIEW_PERMISSION[path];
  // During bootstrap and in pure policy tests there is no authenticated
  // session yet. Once a session exists, its grants must shape the menu.
  if (requiredPermission && getSessionUser() && !hasPermission(requiredPermission)) {
    return false;
  }

  return true;
}

function filterSubItemsByRuntimeConfig(
  items: AdminMenuSubItem[],
  runtimeConfig: TenantRuntimeConfig,
): AdminMenuSubItem[] {
  return items.filter((item) => isAllowedMenuPath(normalizePath(item.path), runtimeConfig));
}

export function filterMenuByRuntimeConfig(
  items: AdminMenuItem[],
  runtimeConfig: TenantRuntimeConfig,
): AdminMenuItem[] {
  const filtered = items.flatMap((item) => {
    const path = normalizePath(item.path);
    const childItems = item.items ?? [];
    const filteredChildren = filterSubItemsByRuntimeConfig(childItems, runtimeConfig);

    if (path && !isAllowedMenuPath(path, runtimeConfig)) {
      return [];
    }

    if (childItems.length > 0 && filteredChildren.length === 0 && !path) {
      return [];
    }

    const hubChildren = filteredChildren.length === 0 ? hubSubItems(path, runtimeConfig) : [];
    return [
      {
        ...item,
        items: hubChildren.length > 0 ? hubChildren : filteredChildren,
      } satisfies AdminMenuItem,
    ];
  });

  return filtered.sort((a, b) => {
    return menuItemSortIndex(a) - menuItemSortIndex(b);
  });
}

class MenuService {
  getMenuByRoleId(roleId: number) {
    if (roleId === null || roleId === undefined) {
      roleId = 0;
    }
    return httpClient.get<AdminMenuItem[]>(API + `menu/${roleId}`);
  }

  async getMenuByRoleIdForRuntimeConfig(roleId: number, runtimeConfig: TenantRuntimeConfig) {
    const response = await this.getMenuByRoleId(roleId);
    return {
      ...response,
      data: filterMenuByRuntimeConfig(response.data, runtimeConfig),
    };
  }
}

export const menuService = new MenuService();
