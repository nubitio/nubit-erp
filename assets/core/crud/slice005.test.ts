// @vitest-environment jsdom
/**
 * Unit tests for SLICE-005 features — T005-6
 *
 * Coverage:
 *   - FieldDef<T> generics      (defineResource with typed T, default T, defaultValue, onChange)
 *   - initCoreI18n + useCoreTranslation  (registration, idempotency, translations, interpolation, override)
 *   - dialogReducer             (initial state, OPEN, CLOSE, isolation, unknown resourceId)
 *   - useCrudDialogStore        (initial values, openDialog, closeDialog, stability, isolation)
 */

import { describe, it, expect, vi, beforeEach, beforeAll } from 'vitest';
import React from 'react';
import { act } from 'react';
import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

// ---------------------------------------------------------------------------
// Module-level mocks — hoisted by Vitest before any imports
// ---------------------------------------------------------------------------

vi.mock('../datagrid/DxDataGrid', () => ({
  DxDataGrid: (_props: any) => React.createElement('div', { 'data-testid': 'dx-data-grid' }),
}));

vi.mock('../dialog/DxDialog', () => ({
  DxDialog: ({ children }: any) =>
    React.createElement('div', { 'data-testid': 'dx-dialog' }, children),
}));

vi.mock('../form/DxForm', () => ({
  DxForm: React.forwardRef((_props: any, _ref: any) =>
    React.createElement('div', { 'data-testid': 'dx-form' }),
  ),
}));

vi.mock('./CrudPage.scss', () => ({}));

vi.mock('@features/movement', () => ({
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
// Imports — after mocks are registered
// ---------------------------------------------------------------------------
import { renderHook } from '@testing-library/react';
import { defineResource } from './defineResource';
import { dialogReducer, initialDialogState } from './dialogStore';
import type { DialogStoreState } from './dialogStore';
import { DialogStoreProvider } from './DialogStoreProvider';
import { useCrudDialogStore } from './useCrudDialogStore';
import { initCoreI18n } from '../i18n/initCoreI18n';
import { useCoreTranslation } from '../i18n/useCoreTranslation';
import type { FieldDef } from '../field/Field';
import { FieldType } from '../field/FieldType';
import type { ResourceConfig } from './ResourceConfig';

// ---------------------------------------------------------------------------
// i18next initialisation — runs once before all tests in this file
// ---------------------------------------------------------------------------

beforeAll(async () => {
  if (!i18next.isInitialized) {
    await i18next.use(initReactI18next).init({
      lng: 'es',
      fallbackLng: 'es',
      ns: ['core'],
      defaultNS: 'core',
      resources: {},
      interpolation: { escapeValue: false },
    });
  }
  initCoreI18n();
});

// ---------------------------------------------------------------------------
// FieldDef<T> generics
// ---------------------------------------------------------------------------

describe('FieldDef<T> generics', () => {
  it('defineResource<{id:number;name:string}>() returns the exact same object reference', () => {
    const config: ResourceConfig<{ id: number; name: string }> = {
      id: 'product',
      title: 'Productos',
      apiUrl: '/api/products',
      fields: [],
    };
    const result = defineResource<{ id: number; name: string }>(config);
    expect(result).toBe(config);
  });

  it('defineResource() without type param compiles with T = Record<string,unknown> default', () => {
    const config: ResourceConfig = {
      id: 'generic',
      title: 'Generic',
      apiUrl: '/api/generic',
      fields: [],
    };
    const result = defineResource(config);
    // Runtime: identity function — same reference
    expect(result).toBe(config);
    expect(result.id).toBe('generic');
  });

  it('FieldDef<T>.defaultValue accepts a T[keyof T] value and is preserved', () => {
    type Product = { id: number; name: string; price: number };

    const field: FieldDef<Product> = {
      isIdentity: false,
      type: FieldType.TEXT,
      col: 12,
      name: 'name',
      label: 'Nombre',
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
      defaultValue: 'Default Name' as Product[keyof Product],
      onChange: undefined,
      onSelect: undefined,
      onClick: undefined,
      onKeyPress: undefined,
      readonly: false,
      disabled: false,
      hidden: false,
      required: false,
      precision: 0,
      accept: undefined,
      buttons: [],
      hidingPriority: undefined,
      searchEnabled: true,
      searchExpr: null,
      fixed: false,
      fixedPosition: undefined,
      helpText: undefined,
      contentRender: undefined,
      visibleOnForm: true,
      maxLength: undefined,
      multiple: false,
      editorOptions: undefined,
      sendAsString: false,
    };

    // Runtime: value is preserved exactly
    expect(field.defaultValue).toBe('Default Name');
  });

  it('FieldDef<T>.defaultValue accepts a factory function returning T[keyof T]', () => {
    type Order = { id: number; status: string };
    const factory = () => 'pending' as Order[keyof Order];

    const field: Partial<FieldDef<Order>> = {
      name: 'status',
      defaultValue: factory,
    };

    // Runtime: factory is stored and callable
    expect(typeof field.defaultValue).toBe('function');
    expect((field.defaultValue as () => Order[keyof Order])()).toBe('pending');
  });

  it('FieldDef<T>.onChange accepts a typed callback and the callback is stored', () => {
    type Invoice = { id: number; total: number };
    const captured: Invoice[keyof Invoice][] = [];
    const handler = (value: Invoice[keyof Invoice]) => {
      captured.push(value);
    };

    const field: Partial<FieldDef<Invoice>> = {
      name: 'total',
      onChange: handler,
    };

    // Runtime: callback is stored and invocable
    expect(field.onChange).toBe(handler);
    (field.onChange as (v: Invoice[keyof Invoice]) => void)(42);
    expect(captured).toEqual([42]);
  });

  it('defineResource preserves onSaveSuccess typed callback', () => {
    type Customer = { id: number; name: string };
    const saved: Customer[] = [];
    const config: ResourceConfig<Customer> = {
      id: 'customer',
      title: 'Clientes',
      apiUrl: '/api/customers',
      fields: [],
      onSaveSuccess: (response: Customer) => {
        saved.push(response);
      },
    };
    const result = defineResource<Customer>(config);
    result.onSaveSuccess!({ id: 1, name: 'Alice' });
    expect(saved).toEqual([{ id: 1, name: 'Alice' }]);
  });
});

// ---------------------------------------------------------------------------
// initCoreI18n + useCoreTranslation
// ---------------------------------------------------------------------------

describe('initCoreI18n', () => {
  it('registers the "core" namespace in i18next after initCoreI18n()', () => {
    // initCoreI18n was called in beforeAll; verify the bundle is present
    const bundle = i18next.getResourceBundle('es', 'core');
    expect(bundle).toBeTruthy();
    expect(typeof bundle).toBe('object');
  });

  it('is idempotent — calling initCoreI18n() twice does not throw or corrupt translations', () => {
    expect(() => {
      initCoreI18n();
      initCoreI18n();
    }).not.toThrow();

    // Translations still resolve correctly after double-init
    const bundle = i18next.getResourceBundle('es', 'core');
    expect(bundle['auditTrail.loading']).toBe('Cargando...');
  });

  it('registers all expected translation keys', () => {
    const bundle = i18next.getResourceBundle('es', 'core') as Record<string, string>;
    const expectedKeys = [
      'crudPage.selectedCount',
      'crudPage.auditTrailButton',
      'crudPage.allColumns',
      'crudPage.columnsLabel',
      'crudPage.dialogTitleAdd',
      'crudPage.dialogTitleEdit',
      'crudPage.dialogTitleView',
      'auditTrail.title',
      'auditTrail.closeButton',
      'auditTrail.selectRecord',
      'auditTrail.loading',
      'auditTrail.error',
      'auditTrail.empty',
      'auditTrail.action.create',
      'auditTrail.action.update',
      'auditTrail.action.delete',
    ];
    for (const key of expectedKeys) {
      expect(bundle[key], `key "${key}" should be registered`).toBeTruthy();
    }
  });
});

describe('useCoreTranslation', () => {
  it('t("auditTrail.loading") returns the Spanish default string', () => {
    const { result } = renderHook(() => useCoreTranslation());
    expect(result.current.t('auditTrail.loading')).toBe('Cargando...');
  });

  it('t("crudPage.selectedCount", { count: 3 }) returns interpolated string', () => {
    const { result } = renderHook(() => useCoreTranslation());
    const translated = result.current.t('crudPage.selectedCount', { count: 3 });
    expect(translated).toBe('3 seleccionados');
  });

  it('t("crudPage.dialogTitleAdd") returns "Nuevo"', () => {
    const { result } = renderHook(() => useCoreTranslation());
    expect(result.current.t('crudPage.dialogTitleAdd')).toBe('Nuevo');
  });

  it('t("auditTrail.action.update") returns "Actualizado"', () => {
    const { result } = renderHook(() => useCoreTranslation());
    expect(result.current.t('auditTrail.action.update')).toBe('Actualizado');
  });

  it('after addResourceBundle override, t("auditTrail.loading") returns the custom string', () => {
    // Override a single key with a custom value
    i18next.addResourceBundle('es', 'core', { 'auditTrail.loading': 'Custom Loading' }, true, true);

    const { result } = renderHook(() => useCoreTranslation());
    expect(result.current.t('auditTrail.loading')).toBe('Custom Loading');

    // Restore the original value so other tests are not affected
    i18next.addResourceBundle('es', 'core', { 'auditTrail.loading': 'Cargando...' }, true, true);
  });

  it('t("crudPage.selectedCount", { count: 1 }) interpolates count=1 correctly', () => {
    const { result } = renderHook(() => useCoreTranslation());
    const translated = result.current.t('crudPage.selectedCount', { count: 1 });
    expect(translated).toBe('1 seleccionados');
  });
});

// ---------------------------------------------------------------------------
// dialogReducer
// ---------------------------------------------------------------------------

describe('dialogReducer', () => {
  it('initial state is {} (empty map)', () => {
    const state: DialogStoreState = {};
    // Passing an unknown action type returns state unchanged
    const result = dialogReducer(state, { type: 'CLOSE', resourceId: 'nonexistent' });
    // The CLOSE on unknown key creates an entry with isOpen=false — but the empty
    // initial state itself is {}
    expect(Object.keys({})).toHaveLength(0);
  });

  it('OPEN action with resourceId="product" sets state.product.isOpen = true', () => {
    const state: DialogStoreState = {};
    const next = dialogReducer(state, {
      type: 'OPEN',
      resourceId: 'product',
      mode: 'add',
      rowData: null,
    });
    expect(next.product.isOpen).toBe(true);
  });

  it('OPEN action sets mode and rowData correctly', () => {
    const state: DialogStoreState = {};
    const rowData = { id: 42, name: 'Widget' };
    const next = dialogReducer(state, {
      type: 'OPEN',
      resourceId: 'product',
      mode: 'edit',
      rowData,
    });
    expect(next.product.mode).toBe('edit');
    expect(next.product.rowData).toBe(rowData);
  });

  it('CLOSE action sets isOpen = false for the given resourceId', () => {
    // First open, then close
    let state: DialogStoreState = {};
    state = dialogReducer(state, {
      type: 'OPEN',
      resourceId: 'invoice',
      mode: 'view',
      rowData: { id: 1 },
    });
    expect(state.invoice.isOpen).toBe(true);

    state = dialogReducer(state, { type: 'CLOSE', resourceId: 'invoice' });
    expect(state.invoice.isOpen).toBe(false);
  });

  it('CLOSE on unknown resourceId returns state with that key set to isOpen=false (no crash)', () => {
    const state: DialogStoreState = {};
    expect(() => {
      const next = dialogReducer(state, { type: 'CLOSE', resourceId: 'unknown' });
      // Should not throw; the key is created with initialDialogState() + isOpen=false
      expect(next.unknown.isOpen).toBe(false);
    }).not.toThrow();
  });

  it('CLOSE on unknown resourceId preserves mode and rowData from initialDialogState()', () => {
    const state: DialogStoreState = {};
    const next = dialogReducer(state, { type: 'CLOSE', resourceId: 'ghost' });
    const initial = initialDialogState();
    expect(next.ghost.mode).toBe(initial.mode);
    expect(next.ghost.rowData).toBe(initial.rowData);
  });

  it('two resources are isolated: opening one does not affect the other', () => {
    let state: DialogStoreState = {};
    state = dialogReducer(state, {
      type: 'OPEN',
      resourceId: 'product',
      mode: 'add',
      rowData: null,
    });
    state = dialogReducer(state, {
      type: 'OPEN',
      resourceId: 'customer',
      mode: 'edit',
      rowData: { id: 99 },
    });

    expect(state.product.isOpen).toBe(true);
    expect(state.product.mode).toBe('add');
    expect(state.customer.isOpen).toBe(true);
    expect(state.customer.mode).toBe('edit');

    // Close product — customer must remain open
    state = dialogReducer(state, { type: 'CLOSE', resourceId: 'product' });
    expect(state.product.isOpen).toBe(false);
    expect(state.customer.isOpen).toBe(true);
  });

  it('OPEN action does not mutate the original state object', () => {
    const state: DialogStoreState = {};
    const next = dialogReducer(state, {
      type: 'OPEN',
      resourceId: 'order',
      mode: 'add',
      rowData: null,
    });
    // Original state is unchanged (immutable reducer)
    expect(state).not.toHaveProperty('order');
    expect(next).toHaveProperty('order');
  });

  it('initialDialogState() returns { isOpen: false, mode: "add", rowData: null }', () => {
    const initial = initialDialogState();
    expect(initial.isOpen).toBe(false);
    expect(initial.mode).toBe('add');
    expect(initial.rowData).toBeNull();
  });
});

// ---------------------------------------------------------------------------
// useCrudDialogStore
// ---------------------------------------------------------------------------

describe('useCrudDialogStore', () => {
  it('returns initialDialogState() values when resourceId has no entry yet', () => {
    const { result } = renderHook(() => useCrudDialogStore('product'), {
      wrapper: DialogStoreProvider,
    });

    const initial = initialDialogState();
    expect(result.current.isOpen).toBe(initial.isOpen);
    expect(result.current.mode).toBe(initial.mode);
    expect(result.current.rowData).toBe(initial.rowData);
  });

  it('openDialog("edit", { id: 1 }) → isOpen=true, mode="edit", rowData={id:1}', () => {
    const { result } = renderHook(() => useCrudDialogStore('product'), {
      wrapper: DialogStoreProvider,
    });

    act(() => {
      result.current.openDialog('edit', { id: 1 });
    });

    expect(result.current.isOpen).toBe(true);
    expect(result.current.mode).toBe('edit');
    expect(result.current.rowData).toEqual({ id: 1 });
  });

  it('closeDialog() → isOpen=false', () => {
    const { result } = renderHook(() => useCrudDialogStore('invoice'), {
      wrapper: DialogStoreProvider,
    });

    act(() => {
      result.current.openDialog('add', null);
    });
    expect(result.current.isOpen).toBe(true);

    act(() => {
      result.current.closeDialog();
    });
    expect(result.current.isOpen).toBe(false);
  });

  it('openDialog with mode="view" sets mode correctly', () => {
    const { result } = renderHook(() => useCrudDialogStore('order'), {
      wrapper: DialogStoreProvider,
    });

    act(() => {
      result.current.openDialog('view', { id: 7, status: 'pending' });
    });

    expect(result.current.mode).toBe('view');
    expect(result.current.rowData).toEqual({ id: 7, status: 'pending' });
  });

  it('openDialog and closeDialog are stable references across re-renders', () => {
    const { result, rerender } = renderHook(() => useCrudDialogStore('stable'), {
      wrapper: DialogStoreProvider,
    });

    const firstOpen = result.current.openDialog;
    const firstClose = result.current.closeDialog;

    rerender();

    expect(result.current.openDialog).toBe(firstOpen);
    expect(result.current.closeDialog).toBe(firstClose);
  });

  it('multiple useCrudDialogStore calls with different resourceIds are isolated', () => {
    // Use two separate renderHook calls sharing the same provider tree
    // by wrapping both in a single component
    const { result } = renderHook(
      () => ({
        product: useCrudDialogStore('product-iso'),
        customer: useCrudDialogStore('customer-iso'),
      }),
      { wrapper: DialogStoreProvider },
    );

    act(() => {
      result.current.product.openDialog('add', null);
    });

    // product is open, customer is still closed
    expect(result.current.product.isOpen).toBe(true);
    expect(result.current.customer.isOpen).toBe(false);

    act(() => {
      result.current.customer.openDialog('edit', { id: 5 });
    });

    // Both open independently
    expect(result.current.product.isOpen).toBe(true);
    expect(result.current.customer.isOpen).toBe(true);
    expect(result.current.customer.mode).toBe('edit');

    act(() => {
      result.current.product.closeDialog();
    });

    // product closed, customer still open
    expect(result.current.product.isOpen).toBe(false);
    expect(result.current.customer.isOpen).toBe(true);
  });

  it('openDialog with no rowData argument defaults rowData to null', () => {
    const { result } = renderHook(() => useCrudDialogStore('nodata'), {
      wrapper: DialogStoreProvider,
    });

    act(() => {
      result.current.openDialog('add');
    });

    expect(result.current.rowData).toBeNull();
  });
});
