// @vitest-environment jsdom

import React from 'react';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import type { ResourceConfig } from './ResourceConfig';

const { dxDataGridMock, dxFormMock, useCrudPageMock, openDialogMock, closeDialogMock } = vi.hoisted(
  () => ({
    dxDataGridMock: vi.fn(),
    dxFormMock: vi.fn(),
    useCrudPageMock: vi.fn(),
    openDialogMock: vi.fn(),
    closeDialogMock: vi.fn(),
  }),
);

vi.mock('../datagrid/DxDataGrid', () => ({
  DxDataGrid: (props: unknown) => {
    dxDataGridMock(props);
    return React.createElement('div', { 'data-testid': 'dx-datagrid' });
  },
}));

vi.mock('../dialog/DxDialog', () => ({
  DxDialog: ({ children }: { children: React.ReactNode }) =>
    React.createElement('div', { 'data-testid': 'dx-dialog' }, children),
}));

vi.mock('../form/DxForm', () => ({
  FORM_EVENTS: {
    ROW_ADDED: 'form:row-added',
    ROW_UPDATED: 'form:row-updated',
    ROW_REMOVED: 'form:row-removed',
  },
  DxForm: (props: unknown) => {
    dxFormMock(props);
    return React.createElement('div', { 'data-testid': 'dx-form' });
  },
}));

vi.mock('./useCrudPage', () => ({
  useCrudPage: (...args: unknown[]) => useCrudPageMock(...args),
}));

vi.mock('../event/EventHook', () => ({
  dispatch: vi.fn(),
  useEvents: () => [vi.fn(() => ({ unsubscribe: vi.fn() }))],
}));

vi.mock('../i18n/useCoreTranslation', () => ({
  useCoreTranslation: () => ({
    t: (_key: string, values?: { count?: number }) => {
      if (values?.count !== undefined) {
        return String(values.count);
      }

      return 'translated';
    },
  }),
}));

vi.mock('./DialogStoreProvider', () => ({
  DialogStoreProvider: ({ children }: { children: React.ReactNode }) =>
    React.createElement(React.Fragment, null, children),
}));

vi.mock('./useCrudDialogStore', () => ({
  useCrudDialogStore: () => ({
    isOpen: true,
    mode: 'add',
    openDialog: openDialogMock,
    closeDialog: closeDialogMock,
  }),
}));

vi.mock('../../shared/components/ConfirmDialog', () => ({
  ConfirmDialog: () => null,
}));

vi.mock('./AuditTrailPanel', () => ({
  AuditTrailPanel: () => null,
}));

import { CrudPage } from './CrudPage';

function makeField(name: string, overrides: Partial<Field> = {}): Field {
  return {
    name,
    label: name,
    type: FieldType.TEXT,
    required: false,
    readonly: false,
    disabled: false,
    hidden: false,
    filterable: false,
    sortable: true,
    hideable: true,
    visible: true,
    visibleOnForm: true,
    isIdentity: false,
    col: 12,
    width: undefined,
    height: null,
    minWidth: undefined,
    minHeight: undefined,
    align: undefined,
    validators: [],
    mode: 'none',
    url: undefined,
    loadOptions: [],
    filters: [],
    byKeyUrl: null,
    textField: 'name',
    valueField: 'id',
    valueType: 'string',
    format: '',
    selectedFilterOperation: undefined,
    filterValue: undefined,
    data: [],
    formatter: undefined,
    itemFormatter: undefined,
    defaultValue: undefined,
    autoSelectIfSingle: false,
    onChange: undefined,
    onSelect: undefined,
    onClick: undefined,
    onKeyPress: undefined,
    precision: 0,
    accept: undefined,
    buttons: [],
    hidingPriority: undefined,
    searchEnabled: false,
    searchExpr: null,
    fixed: false,
    fixedPosition: undefined,
    helpText: undefined,
    contentRender: undefined,
    maxLength: undefined,
    multiple: false,
    editorOptions: undefined,
    sendAsString: false,
    ...overrides,
  };
}

describe('CrudPage', () => {
  beforeEach(() => {
    dxDataGridMock.mockClear();
    dxFormMock.mockClear();
    useCrudPageMock.mockReset();
    openDialogMock.mockReset();
    closeDialogMock.mockReset();
  });

  it('excludes non-listable form-only fields from the datagrid while keeping them in the form', () => {
    const resource: ResourceConfig = {
      id: 'users',
      title: 'Users',
      apiUrl: '/api/users',
      fields: [],
    };

    const fields = [
      makeField('id', { isIdentity: true, visible: false, visibleOnForm: false }),
      makeField('username'),
      makeField('plainPassword', {
        type: FieldType.PASSWORD,
        visible: false,
        visibleOnForm: true,
      }),
    ];

    useCrudPageMock.mockReturnValue({
      events: {},
      resource,
      fields,
      formRef: { current: null },
      permissions: { canAdd: true, canEdit: true, canDelete: true, canExport: true },
      selectionState: {
        selectedIds: [],
        selectedCount: 0,
        hasSelection: false,
        clearSelection: vi.fn(),
        onSelectionChanged: vi.fn(),
      },
      presetState: {
        activePreset: null,
        setPreset: vi.fn(),
        visibleColumns: null,
      },
    });

    render(React.createElement(CrudPage, { resource }));

    expect(screen.getByTestId('dx-datagrid')).toBeTruthy();
    expect(screen.getByTestId('dx-form')).toBeTruthy();

    const datagridFields = dxDataGridMock.mock.calls.at(-1)?.[0].fields as Field[];
    const formFields = dxFormMock.mock.calls.at(-1)?.[0].fields as Field[];

    expect(datagridFields.map((field) => field.name)).toEqual(['id', 'username']);
    expect(formFields.map((field) => field.name)).toEqual(['id', 'username', 'plainPassword']);
  });

  it('reacts to route prop changes after mount', () => {
    const resource: ResourceConfig = {
      id: 'users',
      title: 'Users',
      apiUrl: '/api/users',
      fields: [],
    };

    const fields = [makeField('id', { isIdentity: true }), makeField('username')];

    useCrudPageMock.mockReturnValue({
      events: {},
      resource,
      fields,
      formRef: { current: null },
      permissions: { canAdd: true, canEdit: true, canDelete: true, canExport: true },
      selectionState: {
        selectedIds: [],
        selectedCount: 0,
        hasSelection: false,
        clearSelection: vi.fn(),
        onSelectionChanged: vi.fn(),
      },
      presetState: {
        activePreset: null,
        setPreset: vi.fn(),
        visibleColumns: null,
      },
    });

    const { rerender } = render(
      React.createElement(CrudPage, { resource, initialRecordId: '42', initialIsNew: false }),
    );

    expect(openDialogMock).toHaveBeenCalledWith('edit', { id: '42' });

    rerender(
      React.createElement(CrudPage, { resource, initialRecordId: null, initialIsNew: true }),
    );
    expect(openDialogMock).toHaveBeenCalledWith('add', null);

    rerender(
      React.createElement(CrudPage, { resource, initialRecordId: null, initialIsNew: false }),
    );
    expect(closeDialogMock).toHaveBeenCalled();
  });

  it('maps routing filters into datagrid field values and filter rules', () => {
    const resource: ResourceConfig = {
      id: 'users',
      title: 'Users',
      apiUrl: '/api/users',
      fields: [],
      filter: [{ field: 'status', operator: '=', value: 'enabled' }],
    };

    const fields = [
      makeField('id', { isIdentity: true, filterable: false }),
      makeField('name', { filterable: true }),
      makeField('isActive', { filterable: true, valueType: 'boolean' }),
    ];

    useCrudPageMock.mockReturnValue({
      events: {},
      resource,
      fields,
      formRef: { current: null },
      permissions: { canAdd: true, canEdit: true, canDelete: true, canExport: true },
      selectionState: {
        selectedIds: [],
        selectedCount: 0,
        hasSelection: false,
        clearSelection: vi.fn(),
        onSelectionChanged: vi.fn(),
      },
      presetState: {
        activePreset: null,
        setPreset: vi.fn(),
        visibleColumns: null,
      },
    });

    const onFiltersChange = vi.fn();

    render(
      React.createElement(CrudPage, {
        resource,
        initialFilters: { name: 'john', isActive: 'true', unknown: 'skip-me' },
        onFiltersChange,
      }),
    );

    const datagridProps = dxDataGridMock.mock.calls.at(-1)?.[0] as {
      fields: Field[];
      filter: Array<{ field: string; operator: string; value: string | number | boolean }>;
      onFilterChange?: (filters: Record<string, string>) => void;
    };

    expect(datagridProps.fields.find((field) => field.name === 'name')?.filterValue).toBe('john');
    expect(
      datagridProps.fields.find((field) => field.name === 'name')?.selectedFilterOperation,
    ).toBe('contains');
    expect(datagridProps.fields.find((field) => field.name === 'isActive')?.filterValue).toBe(
      'true',
    );
    expect(
      datagridProps.fields.find((field) => field.name === 'isActive')?.selectedFilterOperation,
    ).toBe('=');
    expect(datagridProps.filter).toEqual([
      { field: 'status', operator: '=', value: 'enabled' },
      { field: 'name', operator: 'contains', value: 'john' },
      { field: 'isActive', operator: '=', value: 'true' },
    ]);
    expect(datagridProps.onFilterChange).toBe(onFiltersChange);
  });

  it('passes computed values through to DxForm', () => {
    const resource: ResourceConfig = {
      id: 'users',
      title: 'Users',
      apiUrl: '/api/users',
      fields: [],
    };
    const fields = [makeField('id', { isIdentity: true }), makeField('total')];

    useCrudPageMock.mockReturnValue({
      events: {},
      resource,
      fields,
      formRef: { current: null },
      permissions: { canAdd: true, canEdit: true, canDelete: true, canExport: true },
      selectionState: {
        selectedIds: [],
        selectedCount: 0,
        hasSelection: false,
        clearSelection: vi.fn(),
        onSelectionChanged: vi.fn(),
      },
      presetState: {
        activePreset: null,
        setPreset: vi.fn(),
        visibleColumns: null,
      },
    });

    render(React.createElement(CrudPage, { resource, computedValues: { total: 118 } }));

    expect(dxFormMock.mock.calls.at(-1)?.[0].computedValues).toEqual({ total: 118 });
  });

  it('clears computed values when SmartCrud stops providing them', () => {
    const resource: ResourceConfig = {
      id: 'users',
      title: 'Users',
      apiUrl: '/api/users',
      fields: [],
    };
    const fields = [
      makeField('id', { isIdentity: true }),
      makeField('total', { readonly: true, defaultValue: 118, computed: () => 118 }),
    ];

    useCrudPageMock.mockReturnValue({
      events: {},
      resource,
      fields,
      formRef: { current: null },
      permissions: { canAdd: true, canEdit: true, canDelete: true, canExport: true },
      selectionState: {
        selectedIds: [],
        selectedCount: 0,
        hasSelection: false,
        clearSelection: vi.fn(),
        onSelectionChanged: vi.fn(),
      },
      presetState: {
        activePreset: null,
        setPreset: vi.fn(),
        visibleColumns: null,
      },
    });

    const { rerender } = render(
      React.createElement(CrudPage, { resource, computedValues: { total: 118 } }),
    );

    expect(dxFormMock.mock.calls.at(-1)?.[0].computedValues).toEqual({ total: 118 });

    rerender(React.createElement(CrudPage, { resource, computedValues: {} }));

    expect(dxFormMock.mock.calls.at(-1)?.[0].computedValues).toEqual({});
  });
});
