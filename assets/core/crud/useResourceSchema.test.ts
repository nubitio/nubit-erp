/**
 * Unit tests for useResourceSchema (REFAC-007 Improvement 4)
 *
 * Covers:
 *  - mergeOverrides: `{ key: 'name', label: 'Custom Label' }` overrides the label
 *    while keeping all other inferred properties intact
 *  - mergeOverrides: only the matched field is changed; others remain untouched
 *  - mergeOverrides: override with an unrecognised key is silently ignored
 *  - mergeOverrides: multiple overrides applied independently
 */

import { describe, it, expect } from 'vitest';
import { mergeOverrides } from './hydra/useResourceSchema';
import type { Field } from '../field/Field';
import type { FieldOverride } from './hydra/useResourceSchema';
import { FieldType } from '../field/FieldType';

// ---------------------------------------------------------------------------
// Fixture helpers
// ---------------------------------------------------------------------------

/** Minimal valid Field stub factory */
function makeField(name: string, overrides: Partial<Field> = {}): Field {
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
    col: undefined,
    width: undefined,
    height: null,
    minWidth: undefined,
    minHeight: undefined,
    align: undefined,
    validators: [],
    mode: 'remote',
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
    onChange: undefined,
    onSelect: undefined,
    onClick: undefined,
    onKeyPress: undefined,
    precision: 2,
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

// ---------------------------------------------------------------------------
// mergeOverrides
// ---------------------------------------------------------------------------

describe('mergeOverrides — REFAC-007 Improvement 4', () => {
  it('overrides the label for the matched field (key === field.name)', () => {
    const inferred: Field[] = [makeField('name'), makeField('email')];
    const overrides: FieldOverride[] = [{ key: 'name', label: 'Custom Label' }];

    const result = mergeOverrides(inferred, overrides);
    const nameField = result.find((f) => f.name === 'name');
    expect(nameField).toBeDefined();
    expect(nameField!.label).toBe('Custom Label');
  });

  it('preserves all other properties on the overridden field', () => {
    const inferred: Field[] = [makeField('name', { required: true, type: FieldType.TEXT })];
    const overrides: FieldOverride[] = [{ key: 'name', label: 'Custom Label' }];

    const result = mergeOverrides(inferred, overrides);
    const nameField = result.find((f) => f.name === 'name')!;

    expect(nameField.required).toBe(true);
    expect(nameField.type).toBe(FieldType.TEXT);
    expect(nameField.name).toBe('name');
  });

  it('does not affect fields that do not match the override key', () => {
    const inferred: Field[] = [makeField('name'), makeField('email')];
    const overrides: FieldOverride[] = [{ key: 'name', label: 'Custom Label' }];

    const result = mergeOverrides(inferred, overrides);
    const emailField = result.find((f) => f.name === 'email')!;
    expect(emailField.label).toBe('Email'); // unchanged
  });

  it('applies multiple overrides independently', () => {
    const inferred: Field[] = [makeField('name'), makeField('price', { type: FieldType.NUMBER })];
    const overrides: FieldOverride[] = [
      { key: 'name', label: 'Product Name' },
      { key: 'price', label: 'Unit Price', required: true },
    ];

    const result = mergeOverrides(inferred, overrides);
    expect(result.find((f) => f.name === 'name')!.label).toBe('Product Name');
    const price = result.find((f) => f.name === 'price')!;
    expect(price.label).toBe('Unit Price');
    expect(price.required).toBe(true);
    expect(price.type).toBe(FieldType.NUMBER); // preserved
  });

  it('silently ignores overrides with a key that matches no field', () => {
    const inferred: Field[] = [makeField('name')];
    const overrides: FieldOverride[] = [{ key: 'nonexistent', label: 'Ghost' }];

    const result = mergeOverrides(inferred, overrides);
    expect(result).toHaveLength(1);
    expect(result[0].name).toBe('name');
    expect(result[0].label).toBe('Name'); // unchanged
  });

  it('does not add a "key" property to the merged field', () => {
    const inferred: Field[] = [makeField('name')];
    const overrides: FieldOverride[] = [{ key: 'name', label: 'New Label' }];

    const result = mergeOverrides(inferred, overrides);
    const nameField = result.find((f) => f.name === 'name')!;
    // The `key` discriminator must be stripped and not appear on the output Field
    expect('key' in nameField).toBe(false);
  });

  it('returns an empty array when given empty inputs', () => {
    expect(mergeOverrides([], [])).toEqual([]);
  });

  it('returns fields unchanged when overrides array is empty', () => {
    const inferred: Field[] = [makeField('name'), makeField('email')];
    const result = mergeOverrides(inferred, []);
    expect(result).toHaveLength(2);
    expect(result[0].label).toBe('Name');
    expect(result[1].label).toBe('Email');
  });

  it('override can change field type', () => {
    const inferred: Field[] = [makeField('amount', { type: FieldType.TEXT })];
    const overrides: FieldOverride[] = [{ key: 'amount', type: FieldType.NUMBER }];

    const result = mergeOverrides(inferred, overrides);
    expect(result[0].type).toBe(FieldType.NUMBER);
  });
});
