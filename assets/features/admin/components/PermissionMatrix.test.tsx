import { afterEach, describe, expect, it } from 'vitest';
import { createRef } from 'react';
import { render, screen, fireEvent, act, cleanup } from '@testing-library/react';

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

describe('PermissionMatrix', () => {
  it('initializes granted keys from the loaded modules', () => {
    const ref = renderMatrix();

    expect(ref.current?.getGrantedKeys()).toEqual(['sales.view']);
    expect((screen.getByLabelText('Ver comprobantes') as HTMLInputElement).checked).toBe(true);
    expect((screen.getByLabelText('Emitir comprobantes') as HTMLInputElement).checked).toBe(false);
  });

  it('toggles a single permission', () => {
    const ref = renderMatrix();

    fireEvent.click(screen.getByLabelText('Emitir comprobantes'));

    expect(ref.current?.getGrantedKeys()).toEqual(
      expect.arrayContaining(['sales.view', 'sales.create']),
    );
  });

  it('module checkbox selects and clears the whole module', () => {
    const ref = renderMatrix();

    fireEvent.click(screen.getByLabelText('Ventas'));
    expect(ref.current?.getGrantedKeys()).toEqual(
      expect.arrayContaining(['sales.view', 'sales.create']),
    );

    fireEvent.click(screen.getByLabelText('Ventas'));
    expect(ref.current?.getGrantedKeys()).toEqual([]);
  });

  it('filters modules and permissions by search query', () => {
    renderMatrix();

    fireEvent.change(screen.getByLabelText('Buscar permiso'), {
      target: { value: 'abrir' },
    });

    expect(screen.getByLabelText('Abrir caja')).toBeTruthy();
    expect(screen.queryByLabelText('Ver comprobantes')).toBeNull();
  });

  it('keeps hidden selections when a filter is active', () => {
    const ref = renderMatrix();

    fireEvent.change(screen.getByLabelText('Buscar permiso'), {
      target: { value: 'abrir' },
    });
    fireEvent.click(screen.getByLabelText('Abrir caja'));

    expect(ref.current?.getGrantedKeys()).toEqual(
      expect.arrayContaining(['sales.view', 'cash.open']),
    );
  });
});
