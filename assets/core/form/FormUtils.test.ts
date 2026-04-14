import { describe, it, expect, vi } from 'vitest';
import { buildField, _getEditorType, _buildValidationRules } from './FormUtils';
import { FieldType } from '../field/FieldType';
import React from 'react';

// Mock DevExtreme form components
vi.mock('devextreme-react/form', () => ({
  SimpleItem: (props: any) => React.createElement('div', props),
  RequiredRule: (props: any) => React.createElement('div', { 'data-type': 'required', ...props }),
  PatternRule: (props: any) => React.createElement('div', { 'data-type': 'pattern', ...props }),
  EmailRule: (props: any) => React.createElement('div', { 'data-type': 'email', ...props }),
  CompareRule: (props: any) => React.createElement('div', { 'data-type': 'compare', ...props }),
  StringLengthRule: (props: any) =>
    React.createElement('div', { 'data-type': 'stringLength', ...props }),
  RangeRule: (props: any) => React.createElement('div', { 'data-type': 'range', ...props }),
  CustomRule: (props: any) => React.createElement('div', { 'data-type': 'custom', ...props }),
  GroupItem: (props: any) => React.createElement('div', props),
}));
vi.mock('devextreme-react/core/template', () => ({
  Template: (props: any) => React.createElement('div', props),
}));
vi.mock('../event/EventHook', () => ({ dispatch: vi.fn() }));
vi.mock('../../shared/data/DxUtils', () => ({
  dxUtils: { getDataStore: vi.fn(() => ({})) },
}));
vi.mock('../utils/SharedUtils', () => ({ getIRI: vi.fn((url, val) => `${url}/${val}`) }));
vi.mock('./DxForm', () => ({
  FORM_EVENTS: { FIELD_CHANGED: 'on:field:changed' },
}));

describe('getEditorType', () => {
  it('TEXT → dxTextBox', () => {
    expect(_getEditorType({ type: FieldType.TEXT } as any)).toBe('dxTextBox');
  });
  it('NUMBER → dxNumberBox', () => {
    expect(_getEditorType({ type: FieldType.NUMBER } as any)).toBe('dxNumberBox');
  });
  it('CURRENCY → dxNumberBox', () => {
    expect(_getEditorType({ type: FieldType.CURRENCY } as any)).toBe('dxNumberBox');
  });
  it('DATE → dxDateBox', () => {
    expect(_getEditorType({ type: FieldType.DATE } as any)).toBe('dxDateBox');
  });
  it('ENTITY → dxSelectBox', () => {
    expect(_getEditorType({ type: FieldType.ENTITY } as any)).toBe('dxSelectBox');
  });
  it('CHECKBOX → dxCheckBox', () => {
    expect(_getEditorType({ type: FieldType.CHECKBOX } as any)).toBe('dxCheckBox');
  });
  it('TAGS → dxTagBox', () => {
    expect(_getEditorType({ type: FieldType.TAGS } as any)).toBe('dxTagBox');
  });
  it('PASSWORD → dxTextBox', () => {
    expect(_getEditorType({ type: FieldType.PASSWORD } as any)).toBe('dxTextBox');
  });
  it('TEXTAREA → dxTextArea', () => {
    expect(_getEditorType({ type: FieldType.TEXTAREA } as any)).toBe('dxTextArea');
  });
  it('HTML → dxHtmlEditor', () => {
    expect(_getEditorType({ type: FieldType.HTML } as any)).toBe('dxHtmlEditor');
  });
});

describe('buildField', () => {
  it('injects formRef into field button onClick context', () => {
    const originalOnClick = vi.fn();
    const formRef = { current: { instance: vi.fn() } } as any;

    const element = buildField(
      {
        name: 'ruc',
        label: 'RUC/DNI',
        type: FieldType.TEXT,
        required: false,
        validators: [],
        visibleOnForm: true,
        buttons: [
          {
            name: 'search',
            location: 'after',
            options: {
              icon: 'search',
              onClick: originalOnClick,
            },
          },
        ],
      } as any,
      0,
      true,
      { current: [] } as any,
      formRef,
    ) as any;

    const wrappedOnClick = element.props.editorOptions.buttons[0].options.onClick;
    const event = { test: true };

    wrappedOnClick(event);

    expect(originalOnClick).toHaveBeenCalledTimes(1);
    expect(originalOnClick).toHaveBeenCalledWith(event, { formRef });
  });
});

describe('buildValidationRules', () => {
  const baseField = {
    name: 'test',
    label: 'Test',
    required: false,
    validators: [],
  };

  it('no rules → empty array', () => {
    const rules = _buildValidationRules(baseField as any);
    expect(rules).toHaveLength(0);
  });

  it('required field → RequiredRule element', () => {
    const rules = _buildValidationRules({ ...baseField, required: true } as any);
    expect(rules).toHaveLength(1);
    // The element type should be RequiredRule (mocked as a div with data-type)
    expect(rules[0]).not.toBeNull();
  });

  it('validator type email → EmailRule element', () => {
    const rules = _buildValidationRules({
      ...baseField,
      validators: [{ type: 'email', options: { message: 'Invalid email' } }],
    } as any);
    expect(rules).toHaveLength(1);
    expect(rules[0]).not.toBeNull();
  });

  it('validator type pattern → PatternRule element', () => {
    const rules = _buildValidationRules({
      ...baseField,
      validators: [{ type: 'pattern', options: { pattern: /^\d+$/, message: 'Numbers only' } }],
    } as any);
    expect(rules).toHaveLength(1);
    expect(rules[0]).not.toBeNull();
  });

  it('validator type range → RangeRule element', () => {
    const rules = _buildValidationRules({
      ...baseField,
      validators: [{ type: 'range', options: { min: 1, max: 100, message: 'Out of range' } }],
    } as any);
    expect(rules).toHaveLength(1);
    expect(rules[0]).not.toBeNull();
  });

  it('validator type stringLength → StringLengthRule element', () => {
    const rules = _buildValidationRules({
      ...baseField,
      validators: [{ type: 'stringLength', options: { min: 3, max: 50, message: 'Too short' } }],
    } as any);
    expect(rules).toHaveLength(1);
    expect(rules[0]).not.toBeNull();
  });

  it('required + custom validator → 2 rules', () => {
    const rules = _buildValidationRules({
      ...baseField,
      required: true,
      validators: [
        { type: 'custom', options: { validationCallback: () => true, message: 'Custom' } },
      ],
    } as any);
    expect(rules).toHaveLength(2);
  });

  it('unknown validator type → null', () => {
    const rules = _buildValidationRules({
      ...baseField,
      validators: [{ type: 'unknown', options: {} }],
    } as any);
    expect(rules[0]).toBeNull();
  });
});
