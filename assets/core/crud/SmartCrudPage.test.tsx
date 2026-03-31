// @vitest-environment jsdom

import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import type { ResourceConfig } from './ResourceConfig';
import { SmartCrudFieldContractError } from './fieldContract';
import { dispatch } from '../event/EventHook';
import type { FieldState } from './rules/useConditionalRules';

const {
  capturedResources,
  capturedCrudProps,
  useResourceSchemaMock,
  useConditionalRulesMock,
  useFieldPermissionsMock,
} = vi.hoisted(() => ({
  capturedResources: [] as Array<ResourceConfig<Record<string, unknown>>>,
  capturedCrudProps: [] as Array<Record<string, unknown>>,
  useResourceSchemaMock: vi.fn(),
  useConditionalRulesMock: vi.fn<
    (fields: Field[], formData: Record<string, unknown> | null) => FieldState[]
  >(() => []),
  useFieldPermissionsMock: vi.fn((fields: Field[], _roles: string[]) => fields),
}));

vi.mock('./CrudPage', () => ({
  CrudPage: (
    props: { resource: ResourceConfig<Record<string, unknown>> } & Record<string, unknown>,
  ) => {
    capturedCrudProps.push(props);
    const { resource } = props;
    capturedResources.push(resource);
    return React.createElement('div', { 'data-testid': 'crud-page' });
  },
}));

vi.mock('./hydra/useResourceSchema', () => ({
  useResourceSchema: (...args: unknown[]) => useResourceSchemaMock(...args),
}));

vi.mock('./rules/useFieldPermissions', () => ({
  useFieldPermissions: (fields: Field[], roles: string[]) => useFieldPermissionsMock(fields, roles),
}));

vi.mock('./rules/useConditionalRules', () => ({
  useConditionalRules: (fields: Field[], formData: Record<string, unknown> | null) =>
    useConditionalRulesMock(fields, formData),
}));

vi.mock('./rules/useDependsOn', () => ({
  useDependsOn: () => undefined,
}));

vi.mock('./routing/useRouting', () => ({
  useRouting: () => ({
    initialRecordId: null,
    initialIsNew: false,
    initialFilters: {},
    syncFilters: () => undefined,
  }),
}));

vi.mock('@tanstack/react-query', () => ({
  useQueryClient: () => ({ invalidateQueries: vi.fn() }),
}));

vi.mock('@core/mercure', () => ({
  useMercureSubscription: () => undefined,
}));

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

import { SmartCrudPage } from './SmartCrudPage';
import { SmartCrudRolesProvider } from './SmartCrudRolesContext';

function makeField(name: string, overrides: Partial<Field> & { operation?: unknown } = {}): Field {
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
  } as Field;
}

describe('SmartCrudPage', () => {
  beforeEach(() => {
    capturedResources.length = 0;
    capturedCrudProps.length = 0;
    useResourceSchemaMock.mockReset();
    useConditionalRulesMock.mockReset();
    useFieldPermissionsMock.mockReset();
    useConditionalRulesMock.mockReturnValue([]);
    useFieldPermissionsMock.mockImplementation((fields: Field[], _roles: string[]) => fields);
  });

  it('applies canonical operation semantics at runtime without hiding grid visibility', () => {
    useResourceSchemaMock.mockReturnValue({
      fields: [
        makeField('plainPassword', {
          type: FieldType.PASSWORD,
          operation: {
            only: 'create',
            required: { create: true },
          },
        }),
      ],
      isLoading: false,
      error: undefined,
      supportedOperations: ['GET', 'POST', 'PATCH'],
    });

    render(
      React.createElement(SmartCrudPage, {
        resource: {
          id: 'users',
          title: 'Users',
          apiUrl: '/api/users',
          fields: [],
        },
      }),
    );

    const getLatestResolvedFields = () => capturedResources.at(-1)!.fields as Field[];
    const initialResource = capturedResources.at(-1)!;
    const initialField = getLatestResolvedFields()[0];
    expect(initialField.visible).toBe(true);
    expect(initialField.visibleOnForm).toBe(true);
    expect(initialField.required).toBe(false);

    act(() => {
      dispatch(initialResource.events!.ADD!);
    });

    const createField = getLatestResolvedFields()[0];
    expect(createField.visible).toBe(true);
    expect(createField.visibleOnForm).toBe(true);
    expect(createField.required).toBe(true);

    act(() => {
      dispatch(initialResource.events!.EDIT!, { row: { id: 1 } });
    });

    const editField = getLatestResolvedFields()[0];
    expect(editField.visible).toBe(true);
    expect(editField.visibleOnForm).toBe(false);
    expect(editField.hidden).toBe(true);
    expect(editField.required).toBe(false);
  });

  it('renders explicit deterministic field-contract errors', () => {
    useResourceSchemaMock.mockReturnValue({
      fields: [],
      isLoading: false,
      error: new SmartCrudFieldContractError('Invalid SmartCrud field contract.', [
        "Override targets unknown Hydra field 'missing'.",
        "Synthetic field 'email' collides with an inferred Hydra field. Use 'override' instead.",
      ]),
      supportedOperations: [],
    });

    render(
      React.createElement(SmartCrudPage, {
        resource: {
          id: 'users',
          title: 'Users',
          apiUrl: '/api/users',
          fields: [],
        },
      }),
    );

    expect(screen.getByText(/Invalid SmartCrud field contract\./)).toBeTruthy();
    expect(screen.getByText(/Override targets unknown Hydra field 'missing'\./)).toBeTruthy();
    expect(
      screen.getByText(/Synthetic field 'email' collides with an inferred Hydra field\./),
    ).toBeTruthy();
  });

  it('applies conditional visibility to the form without hiding grid columns', () => {
    useResourceSchemaMock.mockReturnValue({
      fields: [makeField('inventoryBehavior')],
      isLoading: false,
      error: undefined,
      supportedOperations: ['GET', 'POST', 'PATCH'],
    });
    useConditionalRulesMock.mockReturnValue([
      { name: 'inventoryBehavior', visible: false, disabled: false, computedValue: undefined },
    ]);

    render(
      React.createElement(SmartCrudPage, {
        resource: {
          id: 'products',
          title: 'Products',
          apiUrl: '/api/products',
          fields: [],
        },
      }),
    );

    const field = (capturedResources.at(-1)!.fields as Field[])[0];
    expect(field.visible).toBe(true);
    expect(field.visibleOnForm).toBe(false);
    expect(field.hidden).toBe(true);
  });

  it('reads roles from the SmartCrud roles provider instead of app auth', () => {
    useResourceSchemaMock.mockReturnValue({
      fields: [makeField('inventoryBehavior')],
      isLoading: false,
      error: undefined,
      supportedOperations: ['GET'],
    });

    render(
      React.createElement(
        SmartCrudRolesProvider,
        { roles: ['admin', 'manager'] },
        React.createElement(SmartCrudPage, {
          resource: {
            id: 'products',
            title: 'Products',
            apiUrl: '/api/products',
            fields: [],
          },
        }),
      ),
    );

    expect(useFieldPermissionsMock).toHaveBeenCalled();
    expect(useFieldPermissionsMock.mock.calls.at(-1)?.[1]).toEqual(['admin', 'manager']);
  });

  it('passes computed values down to CrudPage for live form synchronization', () => {
    useResourceSchemaMock.mockReturnValue({
      fields: [makeField('total')],
      isLoading: false,
      error: undefined,
      supportedOperations: ['GET'],
    });
    useConditionalRulesMock.mockReturnValue([
      { name: 'total', visible: true, disabled: false, computedValue: 118 },
    ]);

    render(
      React.createElement(SmartCrudPage, {
        resource: {
          id: 'products',
          title: 'Products',
          apiUrl: '/api/products',
          fields: [],
        },
      }),
    );

    expect(capturedCrudProps.at(-1)?.computedValues).toEqual({ total: 118 });
  });
});
