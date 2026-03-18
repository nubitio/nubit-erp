// @vitest-environment jsdom
/**
 * Unit tests for DxDetailDataGrid — SLICE-010 T010-1
 *
 * Coverage:
 *   - renders without crashing when fields is undefined
 *   - renders without crashing when url is undefined
 *   - does not call getDataStore when url is falsy
 *   - calls getDataStore with correct url and idField when url is provided
 *   - falls back to keyExpr "id" when getIdField returns empty string
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render } from '@testing-library/react';

// ---------------------------------------------------------------------------
// Hoisted mock variables — must be defined before vi.mock factory runs
// ---------------------------------------------------------------------------

const { mockGetDataStore } = vi.hoisted(() => ({
  mockGetDataStore: vi.fn(() => ({})),
}));

// ---------------------------------------------------------------------------
// Module-level mocks
// ---------------------------------------------------------------------------

vi.mock('../../shared/data/DxUtils', () => ({
  dxUtils: {
    getDataStore: mockGetDataStore,
  },
}));

vi.mock('devextreme-react', () => ({
  DataGrid: ({ children }: { children?: React.ReactNode }) =>
    React.createElement('div', { 'data-testid': 'data-grid' }, children),
}));

vi.mock('devextreme-react/data-grid', () => ({
  FilterRow: () => null,
  LoadPanel: () => null,
  Paging: () => null,
  Sorting: () => null,
}));

vi.mock('./DxDataGridUtils', () => ({
  buildColumns: vi.fn(() => []),
  getIdField: vi.fn((fields: unknown[]) => {
    if (!Array.isArray(fields) || fields.length === 0) return '';
    const identity = (fields as Array<{ isIdentity?: boolean; name?: string }>).find(
      (f) => f.isIdentity,
    );
    return identity?.name ?? '';
  }),
}));

// ---------------------------------------------------------------------------
// Import after mocks
// ---------------------------------------------------------------------------

import { DxDetailDataGrid } from './DxDetailDataGrid';
import { dxUtils } from '../../shared/data/DxUtils';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const makeFields = () => [
  { name: 'id', label: 'Id', isIdentity: true, type: 'none' } as any,
  { name: 'product', label: 'Producto', isIdentity: false, type: 'text' } as any,
];

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DxDetailDataGrid', () => {
  beforeEach(() => {
    mockGetDataStore.mockClear();
  });

  it('renders without crashing when fields is undefined', () => {
    expect(() => {
      const { unmount } = render(
        React.createElement(DxDetailDataGrid, {
          options: { url: '/api/items', fields: undefined },
        }),
      );
      unmount();
    }).not.toThrow();
  });

  it('renders without crashing when url is undefined', () => {
    expect(() => {
      const { unmount } = render(
        React.createElement(DxDetailDataGrid, {
          options: { url: undefined, fields: makeFields() },
        }),
      );
      unmount();
    }).not.toThrow();
  });

  it('renders without crashing when both url and fields are undefined', () => {
    expect(() => {
      const { unmount } = render(
        React.createElement(DxDetailDataGrid, {
          options: { url: undefined, fields: undefined },
        }),
      );
      unmount();
    }).not.toThrow();
  });

  it('does NOT call getDataStore when url is undefined', () => {
    render(
      React.createElement(DxDetailDataGrid, {
        options: { url: undefined, fields: makeFields() },
      }),
    );
    expect(dxUtils.getDataStore).not.toHaveBeenCalled();
  });

  it('does NOT call getDataStore when url is an empty string', () => {
    render(
      React.createElement(DxDetailDataGrid, {
        options: { url: '', fields: makeFields() },
      }),
    );
    expect(dxUtils.getDataStore).not.toHaveBeenCalled();
  });

  it('calls getDataStore with the correct url when url is provided', () => {
    render(
      React.createElement(DxDetailDataGrid, {
        options: { url: '/api/purchases/42/items', fields: makeFields() },
      }),
    );
    expect(dxUtils.getDataStore).toHaveBeenCalledTimes(1);
    expect(dxUtils.getDataStore).toHaveBeenCalledWith('/api/purchases/42/items', 'id');
  });

  it('falls back to keyExpr "id" when getIdField returns empty string (no identity field)', () => {
    // fields with no isIdentity:true → getIdField returns ''
    const fieldsWithoutId = [
      { name: 'product', label: 'Producto', isIdentity: false, type: 'text' } as any,
    ];
    render(
      React.createElement(DxDetailDataGrid, {
        options: { url: '/api/items', fields: fieldsWithoutId },
      }),
    );
    // Should call getDataStore with fallback 'id' (not empty string)
    expect(dxUtils.getDataStore).toHaveBeenCalledWith('/api/items', 'id');
  });

  it('renders the data-grid container', () => {
    const { getByTestId, unmount } = render(
      React.createElement(DxDetailDataGrid, {
        options: { url: '/api/items', fields: makeFields() },
      }),
    );
    expect(getByTestId('data-grid')).toBeTruthy();
    unmount();
  });
});
