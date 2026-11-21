import { afterEach, describe, expect, it } from 'vitest';
import { createRef } from 'react';
import { render, screen, fireEvent, act, cleanup, within } from '@testing-library/react';

import {
  PermissionMatrix,
  type PermissionMatrixHandle,
  type PermissionModule,
} from './PermissionMatrix';

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
    permissions: [{ key: 'cash.open', label: 'Abrir caja', granted: false }],
  },
];

afterEach(cleanup);

function renderMatrix() {
  const ref = createRef<PermissionMatrixHandle>();
  render(<PermissionMatrix ref={ref} />);
  act(() => {
    ref.current?.setModules(MODULES);
  });
  return ref;
}

const box = (text: string) => {
  const row = screen
    .getAllByRole('row')
    .find((candidate) => candidate.textContent?.trim().startsWith(text));
  if (!row) throw new Error(`No row starts with "${text}"`);
  return within(row).getByRole('checkbox') as HTMLInputElement;
};

describe('PermissionMatrix', () => {
  it('starts from the granted keys of the loaded modules, with modules open', () => {
    const ref = renderMatrix();

    expect(ref.current?.getGrantedKeys()).toEqual(['sales.view']);
    expect(box('Ver comprobantes').checked).toBe(true);
    expect(box('Emitir comprobantes').checked).toBe(false);
  });

  it('toggles a single permission', () => {
    const ref = renderMatrix();

    fireEvent.click(box('Emitir comprobantes'));

    expect(ref.current?.getGrantedKeys()).toEqual(
      expect.arrayContaining(['sales.view', 'sales.create']),
    );
    // with both on, the module reads as on
    expect(box('Ventas').checked).toBe(true);
  });

  it('a module checkbox grants the whole module, and then clears it', () => {
    const ref = renderMatrix();

    fireEvent.click(box('Ventas'));
    expect(ref.current?.getGrantedKeys()).toEqual(
      expect.arrayContaining(['sales.view', 'sales.create']),
    );

    fireEvent.click(box('Ventas'));
    expect(ref.current?.getGrantedKeys()).toEqual([]);
  });

  it('never returns module ids as permissions', () => {
    const ref = renderMatrix();

    fireEvent.click(box('Caja'));

    expect(ref.current?.getGrantedKeys()).toEqual(['sales.view', 'cash.open']);
  });

  it('shows a module with only some permissions granted as indeterminate', () => {
    renderMatrix();

    expect(box('Ventas').indeterminate).toBe(true);
    expect(box('Caja').indeterminate).toBe(false);
  });

  it('counts the granted permissions', () => {
    renderMatrix();

    expect(screen.getByTestId('role-actions-count').textContent).toBe('1 de 3 permisos');
  });

  it('filters with the tree search and keeps selections it hides', () => {
    const ref = renderMatrix();

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'abrir' } });

    expect(screen.queryByText('Ver comprobantes')).toBeNull();
    fireEvent.click(box('Abrir caja'));
    expect(ref.current?.getGrantedKeys()).toEqual(
      expect.arrayContaining(['sales.view', 'cash.open']),
    );
  });
});
