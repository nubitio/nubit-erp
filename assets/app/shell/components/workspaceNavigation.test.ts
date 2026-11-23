import { describe, expect, it } from 'vitest';
import {
  mobileWorkspaceDestinations,
  posExitPath,
  workspaceDestinations,
  workspaceSectionTitle,
} from './workspaceNavigation';

describe('workspace navigation', () => {
  it('uses only authorized entries and deduplicates parent/child destinations', () => {
    const menu = [
      {
        text: 'Catálogo',
        path: 'catalog',
        icon: 'ph-package',
        items: [
          { text: 'Productos', path: '/catalog/products' },
          { text: 'Duplicado', path: '/catalog/' },
        ],
      },
    ];
    expect(workspaceDestinations(menu).map((item) => item.path)).toEqual([
      '/catalog',
      '/catalog/products',
    ]);
    expect(workspaceDestinations(menu)[1].icon).toBe('ph-package');
    expect(mobileWorkspaceDestinations(menu).map((item) => item.path)).toEqual(['/catalog']);
  });

  it('does not invent POS, customers or billing access absent from the menu', () => {
    expect(mobileWorkspaceDestinations([])).toEqual([]);
    expect(mobileWorkspaceDestinations([{ text: 'Inicio', path: '/dashboard' }])).toEqual([
      { text: 'Inicio', path: '/dashboard', icon: undefined },
    ]);
  });

  it('rejects external URLs, protocol-relative paths, query and hash redirects', () => {
    const paths = [
      'https://example.com',
      '//example.com',
      'javascript:alert(1)',
      '/catalog?next=external',
      '/catalog#target',
      '/catalog\\escape',
    ];
    expect(workspaceDestinations(paths.map((path) => ({ path, text: 'Invalid' })))).toEqual([]);
  });
});

it('uses the actual section for grouped routes and keeps dashboard naming unchanged', () => {
  const menu = [
    { text: 'Inicio', path: '/dashboard' },
    { text: 'Preferencias generales', path: '/config' },
  ];
  expect(workspaceSectionTitle('/config/roles', menu, 'ERP')).toBe('Administración');
  expect(workspaceSectionTitle('/cash-session/10/close', menu, 'Sucursal')).toBe('Finanzas');
  expect(workspaceSectionTitle('/accounts-payable', menu, 'ERP')).toBe('Finanzas');
  for (const path of [
    '/accounts-receivable',
    '/bank-reconciliation',
    '/bank-reconciliation/12',
    '/bank-accounts',
    '/financial-reports',
    '/accounting',
    '/expense-reports',
    '/fixed-assets',
    '/cashbox/dashboard',
    '/cash-register/3/shifts',
  ]) {
    // Even when the menu does not list them (capability off), never fall back to the branch name.
    expect(workspaceSectionTitle(path, [], 'Sucursal Principal')).toBe('Finanzas');
  }
  expect(workspaceSectionTitle('/dashboard', menu, 'ERP')).toBe('Inicio');
  expect(workspaceSectionTitle('/other', menu, 'ERP')).toBe('ERP');
});

describe('posExitPath', () => {
  const item = (path: string, items?: Array<{ text: string; path: string }>) =>
    ({ text: path, path, items }) as never;

  it('prefers Inicio when the user has it', () => {
    expect(posExitPath([item('/pos'), item('/billing'), item('/dashboard')])).toBe('/dashboard');
  });

  it('falls back to the first other destination, grouped ones included', () => {
    expect(
      posExitPath([
        item('/pos'),
        { text: 'Finanzas', items: [{ text: 'A', path: '/treasury' }] } as never,
      ]),
    ).toBe('/treasury');
  });

  it('is null when the POS is all the user can reach', () => {
    expect(posExitPath([item('/pos')])).toBeNull();
    expect(posExitPath([])).toBeNull();
  });
});
