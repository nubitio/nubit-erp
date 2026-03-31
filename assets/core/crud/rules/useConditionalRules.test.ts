// @vitest-environment jsdom
import { describe, it, expect } from 'vitest';
import { renderHook } from '@testing-library/react';
import { act } from 'react';
import { useState } from 'react';
import type { Field } from '../../field/Field';
import { FieldType } from '../../field/FieldType';
import { useConditionalRules } from './useConditionalRules';

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

describe('useConditionalRules', () => {
  it('returns visible=true and disabled=false when no rules are set', () => {
    const fields: Field[] = [makeField({ name: 'foo' })];
    const { result } = renderHook(() => useConditionalRules(fields, {}));

    expect(result.current).toHaveLength(1);
    expect(result.current[0].name).toBe('foo');
    expect(result.current[0].visible).toBe(true);
    expect(result.current[0].disabled).toBe(false);
    expect(result.current[0].computedValue).toBeUndefined();
  });

  it('defers rule evaluation while form data has not hydrated yet', () => {
    const fields: Field[] = [
      makeField({
        name: 'vatNumber',
        visibleWhen: () => false,
        disabledWhen: () => true,
        computed: () => 'derived',
      }),
    ];

    const { result } = renderHook(() => useConditionalRules(fields, null));

    expect(result.current[0].visible).toBe(true);
    expect(result.current[0].disabled).toBe(false);
    expect(result.current[0].computedValue).toBeUndefined();
  });

  it('evaluates rules against an empty create-form snapshot', () => {
    const fields: Field[] = [
      makeField({
        name: 'vatNumber',
        visibleWhen: (data) => data.customerType === 'business',
        disabledWhen: (data) => data.locked !== false,
        computed: (data) => String(data.code ?? 'AUTO'),
      }),
    ];

    const { result } = renderHook(() => useConditionalRules(fields, {}));

    expect(result.current[0].visible).toBe(false);
    expect(result.current[0].disabled).toBe(true);
    expect(result.current[0].computedValue).toBe('AUTO');
  });

  it('applies visibleWhen — field hidden when condition false', () => {
    const fields: Field[] = [
      makeField({
        name: 'vatNumber',
        visibleWhen: (data) => data.taxRegime === 'vat',
      }),
    ];
    const formData = { taxRegime: 'simplified' };
    const { result } = renderHook(() => useConditionalRules(fields, formData));

    expect(result.current[0].visible).toBe(false);
  });

  it('applies visibleWhen — field visible when condition true', () => {
    const fields: Field[] = [
      makeField({
        name: 'vatNumber',
        visibleWhen: (data) => data.taxRegime === 'vat',
      }),
    ];
    const formData = { taxRegime: 'vat' };
    const { result } = renderHook(() => useConditionalRules(fields, formData));

    expect(result.current[0].visible).toBe(true);
  });

  it('preserves form-hidden fields as hidden by default', () => {
    const fields: Field[] = [
      makeField({
        name: 'type',
        visibleOnForm: false,
      }),
    ];

    const { result } = renderHook(() => useConditionalRules(fields, {}));

    expect(result.current[0].visible).toBe(false);
  });

  it('does not re-show form-hidden fields when visibleWhen becomes true', () => {
    const fields: Field[] = [
      makeField({
        name: 'type',
        visibleOnForm: false,
        visibleWhen: (data) => data.productKind === 'GOOD',
      }),
    ];

    const { result } = renderHook(() => useConditionalRules(fields, { productKind: 'GOOD' }));

    expect(result.current[0].visible).toBe(false);
  });

  it('applies disabledWhen — field disabled when condition true', () => {
    const fields: Field[] = [
      makeField({
        name: 'status',
        disabledWhen: (data) => data.locked === true,
      }),
    ];
    const formData = { locked: true };
    const { result } = renderHook(() => useConditionalRules(fields, formData));

    expect(result.current[0].disabled).toBe(true);
  });

  it('applies disabledWhen — field enabled when condition false', () => {
    const fields: Field[] = [
      makeField({
        name: 'status',
        disabledWhen: (data) => data.locked === true,
      }),
    ];
    const formData = { locked: false };
    const { result } = renderHook(() => useConditionalRules(fields, formData));

    expect(result.current[0].disabled).toBe(false);
  });

  it('evaluates computed value from formData', () => {
    const fields: Field[] = [
      makeField({
        name: 'totalWithTax',
        computed: (data) => ((data.price as number) ?? 0) * 1.18,
      }),
    ];
    const formData = { price: 100 };
    const { result } = renderHook(() => useConditionalRules(fields, formData));

    expect(result.current[0].computedValue).toBeCloseTo(118);
  });

  it('re-evaluates when formData changes', () => {
    const fields: Field[] = [
      makeField({
        name: 'discount',
        visibleWhen: (data) => data.hasDiscount === true,
      }),
    ];

    const { result } = renderHook(() => {
      const [formData, setFormData] = useState<Record<string, unknown>>({ hasDiscount: false });
      const states = useConditionalRules(fields, formData);
      return { states, setFormData };
    });

    expect(result.current.states[0].visible).toBe(false);

    act(() => {
      result.current.setFormData({ hasDiscount: true });
    });

    expect(result.current.states[0].visible).toBe(true);
  });

  it('handles multiple fields with mixed rules independently', () => {
    const fields: Field[] = [
      makeField({
        name: 'field1',
        visibleWhen: (data) => data.showField1 === true,
      }),
      makeField({
        name: 'field2',
        disabledWhen: (data) => data.locked === true,
      }),
      makeField({
        name: 'field3',
        computed: (data) => String(data.value ?? ''),
      }),
      makeField({ name: 'field4' }), // no rules
    ];

    const formData = { showField1: false, locked: true, value: 'hello' };
    const { result } = renderHook(() => useConditionalRules(fields, formData));

    const states = result.current;
    expect(states).toHaveLength(4);

    // field1: hidden
    expect(states[0].name).toBe('field1');
    expect(states[0].visible).toBe(false);

    // field2: disabled
    expect(states[1].name).toBe('field2');
    expect(states[1].disabled).toBe(true);

    // field3: computed
    expect(states[2].name).toBe('field3');
    expect(states[2].computedValue).toBe('hello');

    // field4: defaults
    expect(states[3].name).toBe('field4');
    expect(states[3].visible).toBe(true);
    expect(states[3].disabled).toBe(false);
    expect(states[3].computedValue).toBeUndefined();
  });
});
