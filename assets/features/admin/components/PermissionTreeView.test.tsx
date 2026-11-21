import { afterEach, describe, expect, it } from 'vitest';
import { createRef } from 'react';
import { render, screen, fireEvent, act, cleanup, within } from '@testing-library/react';

import { PermissionTreeView, type PermissionTreeHandle, type RoleMenu } from './PermissionTreeView';

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

afterEach(cleanup);

function renderTree(menus: RoleMenu[] = MENUS) {
  const ref = createRef<PermissionTreeHandle>();
  render(<PermissionTreeView ref={ref} />);
  act(() => {
    ref.current?.setItems(menus);
  });
  return ref;
}

/** The selection checkbox of the row that shows `text`. */
const box = (text: string) => {
  const row = screen
    .getAllByRole('row')
    .find((candidate) => candidate.textContent?.trim().startsWith(text));
  if (!row) throw new Error(`No row starts with "${text}"`);
  return within(row).getByRole('checkbox') as HTMLInputElement;
};

describe('PermissionTreeView', () => {
  it('starts from the loaded menus, including childless ones', () => {
    const ref = renderTree();

    expect(box('Inicio').checked).toBe(false);
    expect(box('Punto de venta').checked).toBe(true);
    expect(ref.current?.getSelectedPermissionIds()).toEqual(
      expect.arrayContaining([201, 300, 301]),
    );
  });

  it('checks and unchecks a menu without entries', () => {
    const ref = renderTree();

    fireEvent.click(box('Inicio'));
    expect(box('Inicio').checked).toBe(true);
    expect(ref.current?.getSelectedPermissionIds()).toContain(1);

    fireEvent.click(box('Inicio'));
    expect(box('Inicio').checked).toBe(false);
    expect(ref.current?.getSelectedPermissionIds()).not.toContain(1);
  });

  it('a partly selected menu selects all of its entries on click, then clears them', () => {
    const ref = renderTree();

    // "Comprobantes" is on and "Notas de crédito" is not
    fireEvent.click(box('Facturación'));
    expect(ref.current?.getSelectedPermissionIds()).toEqual(
      expect.arrayContaining([300, 301, 302]),
    );

    fireEvent.click(box('Facturación'));
    expect(ref.current?.getSelectedPermissionIds()).toEqual([201]);
  });

  it('shows a menu with only some entries on as indeterminate', () => {
    renderTree();

    expect(box('Facturación').indeterminate).toBe(true);
    expect(box('Facturación').checked).toBe(false);
  });

  it('switching the last entry off takes its menu off too', () => {
    const ref = renderTree();

    fireEvent.click(screen.getByRole('button', { name: /Expandir|Expand/ }));
    fireEvent.click(box('Comprobantes'));

    expect(ref.current?.getSelectedPermissionIds()).toEqual([201]);
  });

  it('shows how many entries of a menu are on', () => {
    renderTree();

    expect(screen.getByText(/1\/2/)).toBeTruthy();
  });

  it('starts folded again when another role is loaded', () => {
    const ref = renderTree();
    fireEvent.click(screen.getByRole('button', { name: /Expandir|Expand/ }));
    expect(screen.queryByText('Notas de crédito')).toBeTruthy();

    act(() => ref.current?.setItems(MENUS));

    expect(screen.queryByText('Notas de crédito')).toBeNull();
  });
});
