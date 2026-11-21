import { describe, expect, it } from 'vitest';
import {
  grantedKeys,
  initialMenuSelection,
  initialModuleSelection,
  menuNodes,
  moduleNodeId,
  moduleNodes,
  normalizeMenuSelection,
  normalizeModuleSelection,
  selectedMenuIds,
  type PermissionModule,
  type RoleMenu,
} from './permissionSelection';

const MENUS: RoleMenu[] = [
  { id: 1, text: 'Inicio', selected: false, items: [] },
  { id: 201, text: 'Punto de venta', selected: true, items: [] },
  {
    id: 300,
    text: 'Facturación',
    selected: false,
    items: [
      { id: 301, text: 'Comprobantes', selected: true },
      { id: 302, text: 'Notas de crédito', selected: false },
    ],
  },
];

const MODULES: PermissionModule[] = [
  {
    key: 'sales',
    label: 'Ventas',
    permissions: [
      { key: 'sales.view', label: 'Ver comprobantes', granted: true },
      { key: 'sales.create', label: 'Emitir comprobantes', granted: false },
    ],
  },
  {
    key: 'cash',
    label: 'Caja',
    permissions: [{ key: 'cash.open', label: 'Abrir', granted: true }],
  },
];

describe('menus', () => {
  it('lists each menu followed by its entries, as rows of one tree', () => {
    expect(menuNodes(MENUS).map((n) => [n.id, n.parentId])).toEqual([
      [1, null],
      [201, null],
      [300, null],
      [301, 300],
      [302, 300],
    ]);
  });

  it('starts from what the server flagged, and a menu with an entry on is on', () => {
    expect([...initialMenuSelection(MENUS)].sort()).toEqual([201, 300, 301].sort());
  });

  it('turns a menu on when an entry is switched on, and off with its last entry', () => {
    const on = normalizeMenuSelection(MENUS, new Set([302]));
    expect(on.has(300)).toBe(true);
    const off = normalizeMenuSelection(MENUS, new Set([300]));
    expect(off.has(300)).toBe(false);
  });

  it('leaves a menu without entries as chosen', () => {
    expect(normalizeMenuSelection(MENUS, new Set([1])).has(1)).toBe(true);
    expect(normalizeMenuSelection(MENUS, new Set()).has(201)).toBe(false);
  });

  it('saves each menu that is on followed by its entries that are on', () => {
    expect(selectedMenuIds(MENUS, new Set([1, 300, 301, 302]))).toEqual([1, 300, 301, 302]);
    expect(selectedMenuIds(MENUS, new Set([300]))).toEqual([300]);
    expect(selectedMenuIds(MENUS, new Set())).toEqual([]);
  });
});

describe('modules', () => {
  it('prefixes module ids so they cannot collide with a permission key', () => {
    expect(moduleNodes(MODULES).map((n) => n.id)).toEqual([
      moduleNodeId('sales'),
      'sales.view',
      'sales.create',
      moduleNodeId('cash'),
      'cash.open',
    ]);
  });

  it('reads a module as on only when every permission is', () => {
    const selection = initialModuleSelection(MODULES);
    expect(selection.has(moduleNodeId('sales'))).toBe(false);
    expect(selection.has(moduleNodeId('cash'))).toBe(true);
    const all = normalizeModuleSelection(MODULES, new Set(['sales.view', 'sales.create']));
    expect(all.has(moduleNodeId('sales'))).toBe(true);
  });

  it('saves permission keys only, never module ids', () => {
    const selection = normalizeModuleSelection(MODULES, new Set(['sales.view', 'cash.open']));
    expect(grantedKeys(MODULES, selection)).toEqual(['sales.view', 'cash.open']);
  });
});
