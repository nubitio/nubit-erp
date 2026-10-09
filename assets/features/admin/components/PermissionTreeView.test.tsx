import { afterEach, describe, expect, it } from 'vitest';
import { createRef } from 'react';
import { render, screen, fireEvent, act, cleanup } from '@testing-library/react';

import { PermissionTreeView, type PermissionTreeHandle, type RoleMenu } from './PermissionTreeView';

const MENUS: RoleMenu[] = [
  {
    id: 1,
    text: 'Inicio',
    selected: false,
    items: [],
  },
  {
    id: 201,
    text: 'Punto de venta',
    selected: true,
    items: [],
  },
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

afterEach(cleanup);

function renderTree(menus: RoleMenu[] = MENUS) {
  const ref = createRef<PermissionTreeHandle>();
  render(<PermissionTreeView ref={ref} />);
  act(() => {
    ref.current?.setItems(menus);
  });
  return ref;
}

describe('PermissionTreeView', () => {
  it('initializes selection from the loaded menus, including childless menus', () => {
    const ref = renderTree();

    expect((screen.getByLabelText('Inicio') as HTMLInputElement).checked).toBe(false);
    expect((screen.getByLabelText('Punto de venta') as HTMLInputElement).checked).toBe(true);
    expect(ref.current?.getSelectedPermissionIds()).toEqual(
      expect.arrayContaining([201, 300, 301]),
    );
  });

  it('checks and unchecks a menu without children', () => {
    const ref = renderTree();

    fireEvent.click(screen.getByLabelText('Inicio'));
    expect((screen.getByLabelText('Inicio') as HTMLInputElement).checked).toBe(true);
    expect(ref.current?.getSelectedPermissionIds()).toContain(1);

    fireEvent.click(screen.getByLabelText('Inicio'));
    expect((screen.getByLabelText('Inicio') as HTMLInputElement).checked).toBe(false);
    expect(ref.current?.getSelectedPermissionIds()).not.toContain(1);
  });

  it('menu checkbox selects and clears all of its children', () => {
    const ref = renderTree();

    fireEvent.click(screen.getByLabelText('Facturación'));
    expect(ref.current?.getSelectedPermissionIds()).toEqual(
      expect.arrayContaining([300, 301, 302]),
    );

    fireEvent.click(screen.getByLabelText('Facturación'));
    expect(ref.current?.getSelectedPermissionIds()).not.toEqual(expect.arrayContaining([300]));
  });

  it('toggling a child keeps the parent id in sync', () => {
    const ref = renderTree();

    fireEvent.click(screen.getByLabelText('Expandir Facturación'));
    fireEvent.click(screen.getByLabelText('Comprobantes'));

    expect(ref.current?.getSelectedPermissionIds()).not.toEqual(expect.arrayContaining([300, 301]));
  });
});
