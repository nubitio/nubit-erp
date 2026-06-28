import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import type { TenantRuntimeConfig } from '@shared/hooks';

const API = getCoreApiBaseUrl();
import type { AdminMenuItem } from '@nubitio/admin';

type AdminMenuSubItem = NonNullable<AdminMenuItem['items']>[number];

const WAREHOUSE_PATHS = new Set(['warehouse', 'inventory/warehouses']);
const TRANSFER_PATHS = new Set(['transfer']);
const DEPRECATED_MENU_PATHS = new Set(['reports']);
const ADVANCED_REPORT_PATHS = new Set(['ple']);

const NAVIGATION_TIER_RANK: Record<TenantRuntimeConfig['profile']['navigationTier'], number> = {
  basic: 0,
  standard: 1,
  advanced: 2,
};

const MENU_MIN_TIER = new Map<string, TenantRuntimeConfig['profile']['navigationTier']>([
  ['purchases', 'standard'],
  ['inventory', 'standard'],
  ['ple', 'advanced'],
]);

function normalizePath(path: unknown): string {
  return String(path ?? '').replace(/^\/+/, '').toLowerCase();
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

  return true;
}

function filterSubItemsByRuntimeConfig(
  items: AdminMenuSubItem[],
  runtimeConfig: TenantRuntimeConfig,
): AdminMenuSubItem[] {
  return items.filter((item) => isAllowedMenuPath(normalizePath(item.path), runtimeConfig));
}

function filterMenuByRuntimeConfig(
  items: AdminMenuItem[],
  runtimeConfig: TenantRuntimeConfig,
): AdminMenuItem[] {
  return items.flatMap((item) => {
    const path = normalizePath(item.path);
    const childItems = item.items ?? [];
    const filteredChildren = filterSubItemsByRuntimeConfig(childItems, runtimeConfig);

    if (path && !isAllowedMenuPath(path, runtimeConfig)) {
      return [];
    }

    if (childItems.length > 0 && filteredChildren.length === 0 && !path) {
      return [];
    }

    return [{ ...item, items: filteredChildren } satisfies AdminMenuItem];
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
