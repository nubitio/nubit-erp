import { describe, expect, it } from 'vitest';
import {
  mobileWorkspaceDestinations,
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
