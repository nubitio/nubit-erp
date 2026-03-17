// @vitest-environment jsdom
/**
 * Unit tests for SLICE-006 Wave 2
 *
 * Coverage:
 *   useDependsOn:
 *     - no-op when no field has dependsOn
 *     - no-op when field has dependsOn but no url
 *     - calls invalidateQueries for field with dependsOn + url
 *     - only re-fires when dependency values change
 *
 *   useRouting:
 *     - returns neutral defaults when routing is undefined
 *     - returns neutral defaults when routeParam is not in URL
 *     - returns initialRecordId when routeParam matches a non-"new" URL segment
 *     - returns initialIsNew=true when URL param value is "new"
 *     - returns empty initialFilters when syncFiltersToUrl is false
 *     - reads initialFilters from URL search params when syncFiltersToUrl is true
 *     - syncFilters is a no-op when syncFiltersToUrl is false/undefined
 *
 *   ResourceConfig routing type:
 *     - ResourceRouting interface accepts expected fields
 *     - ResourceConfig accepts optional routing property
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { act } from 'react';
import React from 'react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { useDependsOn } from './rules/useDependsOn';
import { useRouting } from './routing/useRouting';
import type { ResourceRouting } from './ResourceConfig';

// ---------------------------------------------------------------------------
// Mocks
// ---------------------------------------------------------------------------

const mockInvalidateQueries = vi.fn();
vi.mock('@tanstack/react-query', () => ({
  useQueryClient: () => ({
    invalidateQueries: mockInvalidateQueries,
  }),
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeField(overrides: Partial<Field> = {}): Field {
  return {
    isIdentity: false,
    type: FieldType.TEXT,
    col: 12,
    name: 'testField',
    label: 'Test',
    width: undefined,
    height: null,
    minWidth: undefined,
    minHeight: undefined,
    align: 'left',
    sortable: true,
    filterable: true,
    hideable: true,
    validators: [],
    mode: 'local',
    url: undefined,
    loadOptions: [],
    filters: [],
    byKeyUrl: null,
    textField: '',
    valueField: '',
    valueType: 'string',
    format: '',
    selectedFilterOperation: undefined,
    filterValue: undefined,
    data: [],
    formatter: undefined,
    itemFormatter: undefined,
    visible: true,
    defaultValue: undefined,
    onChange: undefined,
    onSelect: undefined,
    onClick: undefined,
    onKeyPress: undefined,
    readonly: false,
    disabled: false,
    hidden: false,
    required: false,
    precision: 0,
    accept: null,
    buttons: [],
    hidingPriority: undefined,
    searchEnabled: true,
    searchExpr: null,
    fixed: false,
    fixedPosition: undefined,
    helpText: undefined,
    contentRender: null,
    visibleOnForm: true,
    maxLength: undefined,
    multiple: false,
    editorOptions: undefined,
    sendAsString: false,
    ...overrides,
  };
}

// ---------------------------------------------------------------------------
// useDependsOn
// ---------------------------------------------------------------------------

describe('useDependsOn', () => {
  beforeEach(() => {
    mockInvalidateQueries.mockClear();
  });

  it('does not call invalidateQueries when no field has dependsOn', () => {
    const fields: Field[] = [makeField({ name: 'name' }), makeField({ name: 'email' })];
    renderHook(() => useDependsOn(fields, {}));

    expect(mockInvalidateQueries).not.toHaveBeenCalled();
  });

  it('does not call invalidateQueries when field has dependsOn but no url', () => {
    const fields: Field[] = [
      makeField({ name: 'subcategory', dependsOn: ['category'], url: undefined }),
    ];
    renderHook(() => useDependsOn(fields, { category: 'electronics' }));

    expect(mockInvalidateQueries).not.toHaveBeenCalled();
  });

  it('calls invalidateQueries with the field url when dependsOn is set and url exists', () => {
    const fields: Field[] = [
      makeField({
        name: 'subcategory',
        url: '/api/subcategories',
        dependsOn: ['category'],
      }),
    ];
    renderHook(() => useDependsOn(fields, { category: 'electronics' }));

    expect(mockInvalidateQueries).toHaveBeenCalledWith({
      queryKey: ['/api/subcategories'],
    });
  });

  it('calls invalidateQueries once per field with dependsOn + url on mount', () => {
    const fields: Field[] = [
      makeField({ name: 'sub', url: '/api/sub', dependsOn: ['cat'] }),
      makeField({ name: 'child', url: '/api/child', dependsOn: ['cat', 'sub'] }),
      makeField({ name: 'simple' }), // no dependsOn — should not trigger
    ];
    renderHook(() => useDependsOn(fields, { cat: 'a', sub: 'b' }));

    expect(mockInvalidateQueries).toHaveBeenCalledTimes(2);
    expect(mockInvalidateQueries).toHaveBeenCalledWith({ queryKey: ['/api/sub'] });
    expect(mockInvalidateQueries).toHaveBeenCalledWith({ queryKey: ['/api/child'] });
  });

  it('re-runs and calls invalidateQueries when a dependency value changes', () => {
    const fields: Field[] = [makeField({ name: 'sub', url: '/api/sub', dependsOn: ['cat'] })];
    const { rerender } = renderHook(
      ({ formData }: { formData: Record<string, unknown> }) => useDependsOn(fields, formData),
      { initialProps: { formData: { cat: 'electronics' } } },
    );

    const callCountAfterMount = mockInvalidateQueries.mock.calls.length;

    // Same value — should not re-trigger
    rerender({ formData: { cat: 'electronics' } });
    expect(mockInvalidateQueries.mock.calls.length).toBe(callCountAfterMount);

    // Changed value — should re-trigger
    rerender({ formData: { cat: 'clothing' } });
    expect(mockInvalidateQueries.mock.calls.length).toBeGreaterThan(callCountAfterMount);
  });
});

// ---------------------------------------------------------------------------
// useRouting
// ---------------------------------------------------------------------------

describe('useRouting', () => {
  it('returns neutral defaults when routing is undefined', () => {
    const { result } = renderHook(() => useRouting(undefined), {
      wrapper: ({ children }: { children: React.ReactNode }) =>
        React.createElement(MemoryRouter, { initialEntries: ['/products'] }, children),
    });

    expect(result.current.initialRecordId).toBeNull();
    expect(result.current.initialIsNew).toBe(false);
    expect(result.current.initialFilters).toEqual({});
    expect(typeof result.current.syncFilters).toBe('function');
  });

  it('returns neutral defaults when routeParam is absent from routing config', () => {
    const routing: ResourceRouting = { syncFiltersToUrl: false };
    const { result } = renderHook(() => useRouting(routing), {
      wrapper: ({ children }: { children: React.ReactNode }) =>
        React.createElement(MemoryRouter, { initialEntries: ['/products/42'] }, children),
    });

    expect(result.current.initialRecordId).toBeNull();
    expect(result.current.initialIsNew).toBe(false);
  });

  it('returns initialRecordId when routeParam matches a record id in URL', () => {
    const routing: ResourceRouting = { routeParam: 'id' };

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(
        MemoryRouter,
        { initialEntries: ['/products/42'] },
        React.createElement(
          Routes,
          null,
          React.createElement(Route, {
            path: '/products/:id',
            element: React.createElement(React.Fragment, null, children),
          }),
        ),
      );

    const { result } = renderHook(() => useRouting(routing), { wrapper });

    expect(result.current.initialRecordId).toBe('42');
    expect(result.current.initialIsNew).toBe(false);
  });

  it('returns initialIsNew=true when URL param value is "new"', () => {
    const routing: ResourceRouting = { routeParam: 'id' };

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(
        MemoryRouter,
        { initialEntries: ['/products/new'] },
        React.createElement(
          Routes,
          null,
          React.createElement(Route, {
            path: '/products/:id',
            element: React.createElement(React.Fragment, null, children),
          }),
        ),
      );

    const { result } = renderHook(() => useRouting(routing), { wrapper });

    expect(result.current.initialIsNew).toBe(true);
    expect(result.current.initialRecordId).toBeNull();
  });

  it('returns empty initialFilters when syncFiltersToUrl is false', () => {
    const routing: ResourceRouting = { syncFiltersToUrl: false };

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(
        MemoryRouter,
        { initialEntries: ['/products?name=foo&status=active'] },
        children,
      );

    const { result } = renderHook(() => useRouting(routing), { wrapper });

    expect(result.current.initialFilters).toEqual({});
  });

  it('reads initialFilters from URL search params when syncFiltersToUrl is true', () => {
    const routing: ResourceRouting = { syncFiltersToUrl: true };

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(
        MemoryRouter,
        { initialEntries: ['/products?name=foo&status=active'] },
        children,
      );

    const { result } = renderHook(() => useRouting(routing), { wrapper });

    expect(result.current.initialFilters).toEqual({ name: 'foo', status: 'active' });
  });

  it('syncFilters is a no-op when syncFiltersToUrl is false/undefined', () => {
    const routing: ResourceRouting = { syncFiltersToUrl: false };

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(MemoryRouter, { initialEntries: ['/products'] }, children);

    const { result } = renderHook(() => useRouting(routing), { wrapper });

    // Should not throw
    expect(() => {
      act(() => {
        result.current.syncFilters({ name: 'test' });
      });
    }).not.toThrow();
  });

  it('syncFilters updates URL search params when syncFiltersToUrl is true', () => {
    const routing: ResourceRouting = { syncFiltersToUrl: true };

    const wrapper = ({ children }: { children: React.ReactNode }) =>
      React.createElement(MemoryRouter, { initialEntries: ['/products'] }, children);

    const { result } = renderHook(() => useRouting(routing), { wrapper });

    act(() => {
      result.current.syncFilters({ category: 'electronics', inStock: 'true' });
    });

    // After sync, the filters should be readable from the updated URL
    expect(result.current.initialFilters).toEqual({
      category: 'electronics',
      inStock: 'true',
    });
  });
});

// ---------------------------------------------------------------------------
// ResourceConfig routing type
// ---------------------------------------------------------------------------

describe('ResourceRouting type contract', () => {
  it('ResourceRouting interface accepts routeParam and syncFiltersToUrl', () => {
    const routing: ResourceRouting = {
      routeParam: 'id',
      syncFiltersToUrl: true,
    };
    expect(routing.routeParam).toBe('id');
    expect(routing.syncFiltersToUrl).toBe(true);
  });

  it('ResourceRouting fields are all optional', () => {
    const routing: ResourceRouting = {};
    expect(routing.routeParam).toBeUndefined();
    expect(routing.syncFiltersToUrl).toBeUndefined();
  });
});
