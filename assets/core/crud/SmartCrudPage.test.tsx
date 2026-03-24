// @vitest-environment jsdom

import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import type { ResourceConfig } from './ResourceConfig';
import { SmartCrudFieldContractError } from './fieldContract';
import { dispatch } from '../event/EventHook';

const { capturedResources, useResourceSchemaMock } = vi.hoisted(() => ({
  capturedResources: [] as Array<ResourceConfig<Record<string, unknown>>>,
  useResourceSchemaMock: vi.fn(),
}));

vi.mock('./CrudPage', () => ({
  CrudPage: ({ resource }: { resource: ResourceConfig<Record<string, unknown>> }) => {
    capturedResources.push(resource);
    return React.createElement('div', { 'data-testid': 'crud-page' });
  },
}));

vi.mock('./hydra/useResourceSchema', () => ({
  useResourceSchema: (...args: unknown[]) => useResourceSchemaMock(...args),
}));

vi.mock('@app/contexts/auth', () => ({
  useAuth: () => ({ user: null }),
}));

vi.mock('./rules/useFieldPermissions', () => ({
  useFieldPermissions: (fields: Field[]) => fields,
}));

vi.mock('./rules/useConditionalRules', () => ({
  useConditionalRules: () => [],
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
    useResourceSchemaMock.mockReset();
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
});
