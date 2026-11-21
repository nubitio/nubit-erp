import type { TreeNodeId } from '@nubitio/ui';

/** A row of the permission trees: a group (menu, module) or one of its entries. */
export interface PermissionNode {
  id: TreeNodeId;
  label: string;
  parentId: TreeNodeId | null;
}

export type RoleMenu = {
  id: number;
  text: string;
  selected?: boolean;
  items: { id: number; text: string; selected: boolean }[];
};

export type PermissionModule = {
  key: string;
  label: string;
  permissions: { key: string; label: string; granted: boolean }[];
};

const MODULE_PREFIX = 'module:';
export const moduleNodeId = (key: string): string => `${MODULE_PREFIX}${key}`;

// ── Menus: the role sees a menu when any of its entries is on ──────────────────────────────────

export function menuNodes(menus: readonly RoleMenu[]): PermissionNode[] {
  return menus.flatMap((menu) => [
    { id: menu.id, label: menu.text, parentId: null },
    ...menu.items.map((item) => ({ id: item.id, label: item.text, parentId: menu.id })),
  ]);
}

/** What the server says is on. A menu with any entry on is on, even if it was not flagged itself. */
export function initialMenuSelection(menus: readonly RoleMenu[]): Set<TreeNodeId> {
  return normalizeMenuSelection(
    menus,
    new Set(
      menus.flatMap((menu) => [
        ...(menu.selected ? [menu.id] : []),
        ...menu.items.filter((item) => item.selected).map((item) => item.id),
      ]),
    ),
  );
}

/**
 * Keeps the menu ids consistent with their entries: a menu with entries is on exactly when one of
 * them is. A menu without entries keeps whatever was chosen for it.
 */
export function normalizeMenuSelection(
  menus: readonly RoleMenu[],
  selected: ReadonlySet<TreeNodeId>,
): Set<TreeNodeId> {
  const next = new Set(selected);
  for (const menu of menus) {
    if (menu.items.length === 0) continue;
    if (menu.items.some((item) => next.has(item.id))) next.add(menu.id);
    else next.delete(menu.id);
  }
  return next;
}

/** Ids to save: each menu that is on, followed by its entries that are on. */
export function selectedMenuIds(
  menus: readonly RoleMenu[],
  selected: ReadonlySet<TreeNodeId>,
): number[] {
  const ids = menus.flatMap((menu) => {
    const entries = menu.items.filter((item) => selected.has(item.id)).map((item) => item.id);
    return entries.length > 0 || selected.has(menu.id) ? [menu.id, ...entries] : [];
  });
  return [...new Set(ids)];
}

// ── Actions: a module reads as on only when every one of its permissions is ────────────────────

export function moduleNodes(modules: readonly PermissionModule[]): PermissionNode[] {
  return modules.flatMap((module) => [
    { id: moduleNodeId(module.key), label: module.label, parentId: null },
    ...module.permissions.map((permission) => ({
      id: permission.key,
      label: permission.label,
      parentId: moduleNodeId(module.key),
    })),
  ]);
}

export function initialModuleSelection(modules: readonly PermissionModule[]): Set<TreeNodeId> {
  return normalizeModuleSelection(
    modules,
    new Set(
      modules.flatMap((module) =>
        module.permissions.filter((permission) => permission.granted).map((p) => p.key),
      ),
    ),
  );
}

export function normalizeModuleSelection(
  modules: readonly PermissionModule[],
  selected: ReadonlySet<TreeNodeId>,
): Set<TreeNodeId> {
  const next = new Set(selected);
  for (const module of modules) {
    const id = moduleNodeId(module.key);
    const all =
      module.permissions.length > 0 &&
      module.permissions.every((permission) => next.has(permission.key));
    if (all) next.add(id);
    else next.delete(id);
  }
  return next;
}

export function grantedKeys(
  modules: readonly PermissionModule[],
  selected: ReadonlySet<TreeNodeId>,
): string[] {
  return modules.flatMap((module) =>
    module.permissions.filter((permission) => selected.has(permission.key)).map((p) => p.key),
  );
}

/** How many of a group's entries are on, for the "2/5" shown beside its name. */
export function countOn(
  entries: readonly { id: TreeNodeId }[],
  selected: ReadonlySet<TreeNodeId>,
): number {
  return entries.filter((entry) => selected.has(entry.id)).length;
}
