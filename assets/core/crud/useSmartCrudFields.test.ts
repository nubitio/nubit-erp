// @vitest-environment jsdom

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import type { Field } from '../field/Field';
import { FieldType } from '../field/FieldType';
import type { FieldState } from './rules/useConditionalRules';

const { useConditionalRulesMock, useFieldPermissionsMock } = vi.hoisted(() => ({
  useConditionalRulesMock: vi.fn<
    (fields: Field[], formData: Record<string, unknown> | null) => FieldState[]
  >(() => []),
  useFieldPermissionsMock: vi.fn((fields: Field[], _roles: string[]) => fields),
}));

vi.mock('./rules/useFieldPermissions', () => ({
  useFieldPermissions: (fields: Field[], roles: string[]) =>
    useFieldPermissionsMock(fields, roles),
}));

vi.mock('./rules/useConditionalRules', () => ({
  useConditionalRules: (fields: Field[], formData: Record<string, unknown> | null) =>
    useConditionalRulesMock(fields, formData),
}));

vi.mock('./rules/useDependsOn', () => ({
  useDependsOn: () => undefined,
}));

import { useSmartCrudFields } from './useSmartCrudFields';

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

describe('useSmartCrudFields', () => {
  beforeEach(() => {
    useConditionalRulesMock.mockReset();
    useFieldPermissionsMock.mockReset();
    useConditionalRulesMock.mockReturnValue([]);
    useFieldPermissionsMock.mockImplementation((fields: Field[]) => fields);
  });

  it('returns fields unchanged when no operation is active and no rules fire', () => {
    const fields = [makeField('name'), makeField('email')];

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, null, null, []),
    );

    expect(result.current.processedFields).toHaveLength(2);
    expect(result.current.processedFields[0].name).toBe('name');
    expect(result.current.processedFields[1].name).toBe('email');
    expect(result.current.computedValues).toEqual({});
  });

  it('applies operation semantics for create-only fields', () => {
    const fields = [
      makeField('plainPassword', {
        type: FieldType.PASSWORD,
        operation: { only: 'create', required: { create: true } },
      } as Partial<Field> & { operation: unknown }),
    ];

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, 'create', {}, []),
    );

    const field = result.current.processedFields[0];
    expect(field.visibleOnForm).toBe(true);
    expect(field.required).toBe(true);
  });

  it('hides create-only fields during edit operation', () => {
    const fields = [
      makeField('plainPassword', {
        type: FieldType.PASSWORD,
        operation: { only: 'create' },
      } as Partial<Field> & { operation: unknown }),
    ];

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, 'edit', null, []),
    );

    const field = result.current.processedFields[0];
    expect(field.visibleOnForm).toBe(false);
    expect(field.hidden).toBe(true);
  });

  it('applies conditional visibility from useConditionalRules', () => {
    const fields = [makeField('status')];
    useConditionalRulesMock.mockReturnValue([
      { name: 'status', visible: false, disabled: false, computedValue: undefined },
    ]);

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, null, { type: 'service' }, []),
    );

    const field = result.current.processedFields[0];
    expect(field.visibleOnForm).toBe(false);
    expect(field.hidden).toBe(true);
  });

  it('applies disabled state from conditional rules', () => {
    const fields = [makeField('price')];
    useConditionalRulesMock.mockReturnValue([
      { name: 'price', visible: true, disabled: true, computedValue: undefined },
    ]);

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, null, {}, []),
    );

    expect(result.current.processedFields[0].disabled).toBe(true);
  });

  it('marks computed fields as readonly and collects computed values', () => {
    const fields = [makeField('total')];
    useConditionalRulesMock.mockReturnValue([
      { name: 'total', visible: true, disabled: false, computedValue: 250 },
    ]);

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, null, { qty: 5, unitPrice: 50 }, []),
    );

    expect(result.current.processedFields[0].readonly).toBe(true);
    expect(result.current.computedValues).toEqual({ total: 250 });
  });

  it('preserves identity fields without applying rules', () => {
    const fields = [makeField('id', { isIdentity: true }), makeField('name')];
    useConditionalRulesMock.mockReturnValue([
      { name: 'id', visible: false, disabled: true, computedValue: undefined },
      { name: 'name', visible: false, disabled: false, computedValue: undefined },
    ]);

    const { result } = renderHook(() =>
      useSmartCrudFields(fields, null, {}, []),
    );

    // Identity field is untouched
    const idField = result.current.processedFields.find((f) => f.name === 'id')!;
    expect(idField.hidden).toBe(false);
    expect(idField.disabled).toBe(false);

    // Non-identity field has rules applied
    const nameField = result.current.processedFields.find((f) => f.name === 'name')!;
    expect(nameField.hidden).toBe(true);
  });

  it('passes roles through to useFieldPermissions', () => {
    const fields = [makeField('name')];
    const roles = ['admin', 'editor'];

    renderHook(() => useSmartCrudFields(fields, null, null, roles));

    expect(useFieldPermissionsMock).toHaveBeenCalledWith(expect.any(Array), roles);
  });
});
