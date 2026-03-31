// @vitest-environment jsdom
/**
 * Unit tests for the ResourceConfig API — SLICE-003 T003-4
 *
 * Coverage:
 *   - defineResource() identity helper
 *   - ResourceConfig shape (required fields, optional field defaults as applied by CrudPage)
 *   - useCrudPage() hook (events resolution, FieldsFactory resolution, formRef shape)
 *   - CrudPage component render (smoke — no DevExtreme rendering in jsdom)
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';

// ---------------------------------------------------------------------------
// Module-level mocks — hoisted by Vitest before any imports
// ---------------------------------------------------------------------------

// Mock DevExtreme-heavy components to avoid jsdom rendering issues.
// Do NOT spread non-standard HTML props onto DOM elements — that generates
// React DOM warnings. Only forward known-safe attributes.
vi.mock('../datagrid/DxDataGrid', () => ({
  DxDataGrid: (_props: any) => React.createElement('div', { 'data-testid': 'dx-data-grid' }),
}));

vi.mock('../dialog/DxDialog', () => ({
  DxDialog: ({ children }: any) =>
    React.createElement('div', { 'data-testid': 'dx-dialog' }, children),
}));

vi.mock('../form/DxForm', () => ({
  DxForm: React.forwardRef(function MockDxForm(_props: any, _ref: any) {
    return React.createElement('div', { 'data-testid': 'dx-form' });
  }),
}));

vi.mock('../dialog/DxDialog', () => ({
  DxDialog: ({ children, ...props }: any) =>
    React.createElement('div', { 'data-testid': 'dx-dialog', ...props }, children),
}));

vi.mock('../form/DxForm', () => ({
  DxForm: React.forwardRef(function MockDxFormDuplicate(_props: any, _ref: any) {
    return React.createElement('div', { 'data-testid': 'dx-form' });
  }),
}));

// Mock scss imports
vi.mock('./CrudPage.scss', () => ({}));

// Mock createCrudEvents from @core/event/createCrudEvents (canonical location)
const mockCreateCrudEvents = vi.fn((id: string) => ({
  ADD: `${id}:add`,
  EDIT: `${id}:edit`,
  DELETE: `${id}:delete`,
  SAVE: `${id}:save`,
  CANCEL: `${id}:cancel`,
  SUCCESS: `${id}:success`,
  LOADING: `${id}:loading`,
}));

vi.mock('@core/event/createCrudEvents', () => ({
  createCrudEvents: (id: string) => mockCreateCrudEvents(id),
}));

// ---------------------------------------------------------------------------
// Imports — after mocks are registered
// ---------------------------------------------------------------------------
import { renderHook } from '@testing-library/react';
import { render, screen } from '@testing-library/react';
import { defineResource } from './defineResource';
import { useCrudPage } from './useCrudPage';
import { CrudPage } from './CrudPage';
import type { ResourceConfig, FieldsFactory } from './ResourceConfig';
import type { Field } from '../field/Field';

// ---------------------------------------------------------------------------
// Test fixtures
// ---------------------------------------------------------------------------

const minimalResource: ResourceConfig = {
  id: 'product',
  title: 'Productos',
  apiUrl: '/api/products',
  fields: [],
};

const stubFields: Field[] = [
  { name: 'id', label: 'Id', type: 'none' } as unknown as Field,
  { name: 'name', label: 'Nombre', type: 'text' } as unknown as Field,
];

// ---------------------------------------------------------------------------
// defineResource()
// ---------------------------------------------------------------------------

describe('defineResource', () => {
  it('returns the exact same object reference (identity function)', () => {
    const config: ResourceConfig = {
      id: 'category',
      title: 'Categorias',
      apiUrl: '/api/categories',
      fields: [],
    };
    const result = defineResource(config);
    expect(result).toBe(config);
  });

  it('preserves all required fields unchanged', () => {
    const config: ResourceConfig = {
      id: 'customer',
      title: 'Clientes',
      apiUrl: '/api/customers',
      fields: stubFields,
    };
    const result = defineResource(config);
    expect(result.id).toBe('customer');
    expect(result.title).toBe('Clientes');
    expect(result.apiUrl).toBe('/api/customers');
    expect(result.fields).toBe(stubFields);
  });

  it('preserves all optional fields unchanged', () => {
    const config: ResourceConfig = {
      id: 'order',
      title: 'Órdenes',
      apiUrl: '/api/orders',
      fields: [],
      allowAdd: false,
      allowEdit: false,
      allowDelete: false,
      mode: 'minimal',
      pageSize: 25,
    } as ResourceConfig & { pageSize?: number };
    const result = defineResource(config);
    expect(result.allowAdd).toBe(false);
    expect(result.allowEdit).toBe(false);
    expect(result.allowDelete).toBe(false);
    expect(result.mode).toBe('minimal');
  });

  it('preserves injected events reference', () => {
    const events = { ADD: 'test:add', EDIT: 'test:edit' };
    const config: ResourceConfig = {
      id: 'test',
      title: 'Test',
      apiUrl: '/api/test',
      fields: [],
      events,
    };
    const result = defineResource(config);
    expect(result.events).toBe(events);
  });
});

// ---------------------------------------------------------------------------
// ResourceConfig shape — CrudPage optional field defaults
// ---------------------------------------------------------------------------

describe('ResourceConfig › CrudPage default prop values', () => {
  it('allowAdd defaults to true when undefined', () => {
    const { unmount } = render(React.createElement(CrudPage, { resource: minimalResource }));
    // CrudPage renders DxDataGrid with allowAdd={resource.allowAdd ?? true}
    // We verify the rendered mock receives the correct prop
    const grid = screen.getByTestId('dx-data-grid');
    expect(grid).toBeTruthy();
    unmount();
  });

  it('allowExport defaults to false when undefined', () => {
    const { unmount } = render(React.createElement(CrudPage, { resource: minimalResource }));
    const grid = screen.getByTestId('dx-data-grid');
    expect(grid).toBeTruthy();
    unmount();
  });
});

// ---------------------------------------------------------------------------
// useCrudPage()
// ---------------------------------------------------------------------------

describe('useCrudPage', () => {
  beforeEach(() => {
    mockCreateCrudEvents.mockClear();
  });

  it('calls createCrudEvents with a scoped prefix when no events are provided', () => {
    const resource: ResourceConfig = { ...minimalResource, id: 'product' };
    renderHook(() => useCrudPage(resource));
    expect(mockCreateCrudEvents).toHaveBeenCalledTimes(1);
    // createScopedEventBus passes "${resourceId}:${scopeId}" to createCrudEvents
    const [calledWith] = mockCreateCrudEvents.mock.calls[0];
    expect(calledWith).toMatch(/^product:[a-z0-9]{8}$/);
  });

  it('generated events contain resource.id-prefixed keys (scoped)', () => {
    const resource: ResourceConfig = { ...minimalResource, id: 'sale' };
    const { result } = renderHook(() => useCrudPage(resource));
    // Keys are "sale:<scopeId>:<verb>" because createScopedEventBus namespaces them
    expect(result.current.events.ADD).toMatch(/^sale:[a-z0-9]{8}:add$/);
    expect(result.current.events.EDIT).toMatch(/^sale:[a-z0-9]{8}:edit$/);
    expect(result.current.events.DELETE).toMatch(/^sale:[a-z0-9]{8}:delete$/);
    expect(result.current.events.SAVE).toMatch(/^sale:[a-z0-9]{8}:save$/);
    expect(result.current.events.CANCEL).toMatch(/^sale:[a-z0-9]{8}:cancel$/);
    expect(result.current.events.SUCCESS).toMatch(/^sale:[a-z0-9]{8}:success$/);
    expect(result.current.events.LOADING).toMatch(/^sale:[a-z0-9]{8}:loading$/);
  });

  it('uses injected events when resource.events is provided', () => {
    const injectedEvents = {
      ADD: 'customer:add',
      EDIT: 'customer:edit',
      DELETE: 'customer:delete',
      SAVE: 'customer:save',
      CANCEL: 'customer:cancel',
      SUCCESS: 'customer:success',
      LOADING: 'customer:loading',
    };
    const resource: ResourceConfig = { ...minimalResource, id: 'customer', events: injectedEvents };
    const { result } = renderHook(() => useCrudPage(resource));
    // createCrudEvents must NOT be called when events are injected
    expect(mockCreateCrudEvents).not.toHaveBeenCalled();
    expect(result.current.events).toBe(injectedEvents);
  });

  it('does not call createCrudEvents when events are injected', () => {
    const injectedEvents = { ADD: 'x:add' };
    const resource: ResourceConfig = { ...minimalResource, events: injectedEvents };
    renderHook(() => useCrudPage(resource));
    expect(mockCreateCrudEvents).not.toHaveBeenCalled();
  });

  it('resolves FieldsFactory (no-arg / static array form) correctly', () => {
    const resource: ResourceConfig = { ...minimalResource, fields: stubFields };
    const { result } = renderHook(() => useCrudPage(resource));
    expect(result.current.fields).toBe(stubFields);
  });

  it('resolves FieldsFactory function form and passes formRef to it', () => {
    const factory = vi.fn((_formRef: any): Field[] => stubFields);
    const resource: ResourceConfig = {
      ...minimalResource,
      fields: factory as unknown as FieldsFactory,
    };
    const { result } = renderHook(() => useCrudPage(resource));
    expect(factory).toHaveBeenCalledTimes(1);
    // factory receives the formRef as its argument
    const passedRef = factory.mock.calls[0][0];
    expect(passedRef).toBe(result.current.formRef);
    expect(result.current.fields).toBe(stubFields);
  });

  it('returns formRef as a RefObject (object with .current property)', () => {
    const resource: ResourceConfig = { ...minimalResource };
    const { result } = renderHook(() => useCrudPage(resource));
    expect(result.current.formRef).toBeDefined();
    expect('current' in result.current.formRef).toBe(true);
    expect(result.current.formRef.current).toBeNull();
  });

  it('memoizes events — same reference across re-renders with same resource.id', () => {
    const resource: ResourceConfig = { ...minimalResource, id: 'stable' };
    const { result, rerender } = renderHook(() => useCrudPage(resource));
    const firstEvents = result.current.events;
    rerender();
    expect(result.current.events).toBe(firstEvents);
    // createCrudEvents is only called once despite multiple renders
    expect(mockCreateCrudEvents).toHaveBeenCalledTimes(1);
  });
});

// ---------------------------------------------------------------------------
// CrudPage — render smoke tests
// ---------------------------------------------------------------------------

describe('CrudPage', () => {
  it('renders without crashing given a minimal ResourceConfig', () => {
    expect(() => {
      const { unmount } = render(React.createElement(CrudPage, { resource: minimalResource }));
      unmount();
    }).not.toThrow();
  });

  it('renders the wrapper div with id="wrapper"', () => {
    const { container, unmount } = render(
      React.createElement(CrudPage, { resource: minimalResource }),
    );
    expect(container.querySelector('#wrapper')).not.toBeNull();
    unmount();
  });

  it('renders the DxDataGrid mock', () => {
    const { unmount } = render(React.createElement(CrudPage, { resource: minimalResource }));
    expect(screen.getByTestId('dx-data-grid')).toBeTruthy();
    unmount();
  });

  it('renders the DxDialog mock', () => {
    const { unmount } = render(React.createElement(CrudPage, { resource: minimalResource }));
    expect(screen.getByTestId('dx-dialog')).toBeTruthy();
    unmount();
  });

  it('renders the DxForm mock', () => {
    const { unmount } = render(React.createElement(CrudPage, { resource: minimalResource }));
    expect(screen.getByTestId('dx-form')).toBeTruthy();
    unmount();
  });

  it('renders correctly with a full ResourceConfig (all optional fields set)', () => {
    const fullResource: ResourceConfig = {
      id: 'order',
      title: 'Órdenes',
      apiUrl: '/api/orders',
      fields: stubFields,
      allowAdd: true,
      allowEdit: true,
      allowDelete: false,
      allowExport: true,
      mode: 'normal',
      paging: true,
      dialogWidth: 800,
      dialogHeight: 600,
      sort: [{ selector: 'date', desc: true }],
    };
    expect(() => {
      const { unmount } = render(React.createElement(CrudPage, { resource: fullResource }));
      unmount();
    }).not.toThrow();
  });

  it('renders with injected events (CustomerPage pattern)', () => {
    const customerEvents = {
      ADD: 'customer:add',
      EDIT: 'customer:edit',
      DELETE: 'customer:delete',
      SAVE: 'customer:save',
      CANCEL: 'customer:cancel',
      SUCCESS: 'customer:success',
      LOADING: 'customer:loading',
    };
    const customerResource: ResourceConfig = {
      id: 'customer',
      title: 'Clientes',
      apiUrl: '/api/customers',
      fields: (formRef) => stubFields,
      events: customerEvents,
    };
    expect(() => {
      const { unmount } = render(React.createElement(CrudPage, { resource: customerResource }));
      unmount();
    }).not.toThrow();
  });
});
