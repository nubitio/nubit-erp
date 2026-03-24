// @vitest-environment jsdom

import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import { defineFields } from './fieldContract';
import { resolveSmartCrudFields } from './resolveSmartCrudFields';

const { mapHydraSchemaToFieldsMock, parseHydraDocMock, useSchemaContextMock } = vi.hoisted(() => ({
  mapHydraSchemaToFieldsMock: vi.fn(),
  parseHydraDocMock: vi.fn(),
  useSchemaContextMock: vi.fn(),
}));

vi.mock('./hydra/HydraToFieldMapper', () => ({
  mapHydraSchemaToFields: (...args: unknown[]) => mapHydraSchemaToFieldsMock(...args),
}));

vi.mock('./hydra/openApiParser', () => ({
  parseHydraDoc: (...args: unknown[]) => parseHydraDocMock(...args),
  parseOpenApiDoc: vi.fn(),
}));

vi.mock('./hydra/SchemaContext', () => ({
  useSchemaContext: () => useSchemaContextMock(),
}));

import { useResourceSchema } from './hydra/useResourceSchema';

function makeField(name: string, overrides: Partial<Field> & { operation?: unknown } = {}): Field {
  return {
    name,
    label: name.charAt(0).toUpperCase() + name.slice(1),
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

describe('smartCrud field contract', () => {
  beforeEach(() => {
    useSchemaContextMock.mockReset();
    parseHydraDocMock.mockReset();
    mapHydraSchemaToFieldsMock.mockReset();
  });

  it('resolves canonical hydra precedence deterministically and preserves synthetic operation metadata', () => {
    const baseline = [makeField('id', { isIdentity: true }), makeField('email'), makeField('role')];
    const plainPassword = makeField('plainPassword', {
      type: FieldType.PASSWORD,
      operation: {
        only: 'create',
        required: { create: true },
      },
    });

    const contract = defineFields<{ id: string; email: string; role: string }>({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        { kind: 'prepend', field: makeField('tenantCode', { label: 'Tenant code' }) },
        { kind: 'override', key: 'email', patch: { label: 'Correo', readonly: true } },
        { kind: 'remove', key: 'role' },
        { kind: 'append', field: plainPassword },
      ],
    });

    const resolved = resolveSmartCrudFields({ baselineFields: baseline, contract });

    expect(resolved.map((field) => field.name)).toEqual([
      'tenantCode',
      'id',
      'email',
      'plainPassword',
    ]);
    expect(resolved.find((field) => field.name === 'email')).toMatchObject({
      label: 'Correo',
      readonly: true,
    });
    expect(resolved.find((field) => field.name === 'plainPassword')).toMatchObject({
      operation: {
        only: 'create',
        required: { create: true },
      },
    });
    expect(resolved[1]).not.toBe(baseline[0]);
    expect(resolved[3]).not.toBe(plainPassword);
  });

  it('rejects duplicate targets, duplicate synthetic names, and contradictory operation states explicitly', () => {
    expect(() =>
      defineFields<{ email: string }>({
        source: 'hydra',
        strategy: 'augment',
        directives: [
          { kind: 'override', key: 'email', patch: { label: 'Correo' } },
          { kind: 'remove', key: 'email' },
          { kind: 'append', field: makeField('plainPassword') },
          { kind: 'prepend', field: makeField('plainPassword') },
          {
            kind: 'append',
            field: makeField('createOnlySecret', {
              operation: {
                only: 'create',
                edit: { visible: true },
              },
            }),
          },
        ],
      }),
    ).toThrowErrorMatchingInlineSnapshot(`
      [SmartCrudFieldContractError: Invalid SmartCrud field contract.]
    `);

    try {
      defineFields<{ email: string }>({
        source: 'hydra',
        strategy: 'augment',
        directives: [
          { kind: 'override', key: 'email', patch: { label: 'Correo' } },
          { kind: 'remove', key: 'email' },
          { kind: 'append', field: makeField('plainPassword') },
          { kind: 'prepend', field: makeField('plainPassword') },
          {
            kind: 'append',
            field: makeField('createOnlySecret', {
              operation: {
                only: 'create',
                edit: { visible: true },
              },
            }),
          },
        ],
      });
    } catch (error) {
      expect((error as Error & { issues: string[] }).issues).toEqual(
        expect.arrayContaining([
          "Field 'email' is targeted more than once in hydra directives (override + remove).",
          "Synthetic field 'plainPassword' is declared more than once.",
          expect.stringContaining('createOnlySecret'),
        ]),
      );
    }
  });

  it('resolves hydra contracts through useResourceSchema and keeps supported operations', () => {
    const inferred = [makeField('id', { isIdentity: true }), makeField('email'), makeField('role')];
    const contract = defineFields<{ id: string; email: string; role: string }>({
      source: 'hydra',
      strategy: 'augment',
      directives: [
        { kind: 'override', key: 'email', patch: { label: 'Correo' } },
        { kind: 'remove', key: 'role' },
        {
          kind: 'append',
          field: makeField('plainPassword', {
            type: FieldType.PASSWORD,
            operation: {
              only: 'create',
              required: { create: true },
            },
          }),
        },
      ],
    });

    useSchemaContextMock.mockReturnValue({
      data: { format: 'hydra', doc: { '@id': '/api/docs.jsonld' } },
      isLoading: false,
      error: undefined,
    });
    parseHydraDocMock.mockReturnValue({
      User: {
        className: 'User',
        apiUrl: '/api/users',
        fields: [],
        supportedOperations: ['GET', 'POST', 'PATCH'],
      },
    });
    mapHydraSchemaToFieldsMock.mockReturnValue(inferred);

    const { result } = renderHook(() =>
      useResourceSchema('/api/users', undefined, undefined, contract),
    );

    expect(parseHydraDocMock).toHaveBeenCalledWith({ '@id': '/api/docs.jsonld' });
    expect(mapHydraSchemaToFieldsMock).toHaveBeenCalledTimes(1);
    expect(result.current.supportedOperations).toEqual(['GET', 'POST', 'PATCH']);
    expect(result.current.error).toBeUndefined();
    expect(result.current.fields.map((field) => field.name)).toEqual([
      'id',
      'email',
      'plainPassword',
    ]);
    expect(result.current.fields.find((field) => field.name === 'plainPassword')).toMatchObject({
      operation: {
        only: 'create',
        required: { create: true },
      },
    });
  });

  it('surfaces runtime contract integration failures from useResourceSchema with actionable issues', () => {
    const contract = defineFields<{ email: string }>({
      source: 'hydra',
      strategy: 'augment',
      directives: [{ kind: 'override', key: 'email', patch: { label: 'Correo' } }],
    });

    useSchemaContextMock.mockReturnValue({
      data: { format: 'hydra', doc: { '@id': '/api/docs.jsonld' } },
      isLoading: false,
      error: undefined,
    });
    parseHydraDocMock.mockReturnValue({
      User: {
        className: 'User',
        apiUrl: '/api/users',
        fields: [],
        supportedOperations: ['GET'],
      },
    });
    mapHydraSchemaToFieldsMock.mockReturnValue([makeField('name')]);

    const { result } = renderHook(() =>
      useResourceSchema('/api/users', undefined, undefined, contract),
    );

    expect(result.current.fields).toEqual([]);
    expect(result.current.error?.message).toBe('Invalid SmartCrud hydra field resolution.');
    expect(result.current.error).toMatchObject({
      issues: ["Override targets unknown Hydra field 'email'."],
    });
  });
});
