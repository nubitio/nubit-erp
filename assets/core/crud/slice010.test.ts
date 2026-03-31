// @vitest-environment jsdom
/**
 * Unit tests for CrudPage master-detail prop forwarding — SLICE-010 T010-1
 *
 * Coverage:
 *   - CrudPage forwards detailUrl and detailFields to DxDataGrid when set
 *   - CrudPage passes undefined for detailUrl/detailFields when not set (no regression)
 */
import { describe, it, expect, vi } from 'vitest';
import React from 'react';
import { render } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Module-level mocks — must be hoisted before any imports
// ---------------------------------------------------------------------------

// Capture props passed to DxDataGrid so we can assert on them
let capturedDataGridProps: Record<string, unknown> = {};

vi.mock('../datagrid/DxDataGrid', () => ({
  DxDataGrid: (props: Record<string, unknown>) => {
    capturedDataGridProps = props;
    return React.createElement('div', { 'data-testid': 'dx-data-grid' });
  },
}));

vi.mock('../dialog/DxDialog', () => ({
  DxDialog: ({ children }: { children?: React.ReactNode }) =>
    React.createElement('div', { 'data-testid': 'dx-dialog' }, children),
}));

vi.mock('../form/DxForm', () => ({
  DxForm: React.forwardRef(function MockDxForm(_props: unknown, _ref: unknown) {
    return React.createElement('div', { 'data-testid': 'dx-form' });
  }),
  FORM_EVENTS: {
    ROW_ADDED: 'form:row.added',
    ROW_UPDATED: 'form:row.updated',
    ROW_REMOVED: 'form:row.removed',
  },
}));

vi.mock('./CrudPage.scss', () => ({}));

vi.mock('@core/event/createCrudEvents', () => ({
  createCrudEvents: (id: string) => ({
    ADD: `${id}:add`,
    EDIT: `${id}:edit`,
    DELETE: `${id}:delete`,
    SAVE: `${id}:save`,
    CANCEL: `${id}:cancel`,
    SUCCESS: `${id}:success`,
    LOADING: `${id}:loading`,
  }),
}));

// ---------------------------------------------------------------------------
// Imports — after mocks
// ---------------------------------------------------------------------------
import { CrudPage } from './CrudPage';
import { defineResource } from './defineResource';
import type { Field } from '../field/Field';

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const stubDetailFields: Field[] = [
  { name: 'id', label: 'Id', type: 'none', isIdentity: true } as unknown as Field,
  { name: 'product', label: 'Producto', type: 'text', isIdentity: false } as unknown as Field,
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('CrudPage › master-detail prop forwarding', () => {
  it('forwards detailUrl and detailFields to DxDataGrid when configured', () => {
    const resource = defineResource({
      id: 'purchases',
      title: 'Compras',
      apiUrl: '/api/purchases',
      fields: [],
      detailUrl: '/api/purchases/{id}/items',
      detailFields: stubDetailFields,
    });

    const { unmount } = render(React.createElement(CrudPage, { resource }));

    expect(capturedDataGridProps['detailUrl']).toBe('/api/purchases/{id}/items');
    expect(capturedDataGridProps['detailFields']).toBe(stubDetailFields);

    unmount();
  });

  it('passes undefined for detailUrl and detailFields when not configured (no regression)', () => {
    const resource = defineResource({
      id: 'products',
      title: 'Productos',
      apiUrl: '/api/products',
      fields: [],
      // detailUrl and detailFields intentionally omitted
    });

    const { unmount } = render(React.createElement(CrudPage, { resource }));

    expect(capturedDataGridProps['detailUrl']).toBeUndefined();
    expect(capturedDataGridProps['detailFields']).toBeUndefined();

    unmount();
  });
});
