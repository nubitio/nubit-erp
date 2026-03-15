import { describe, it, expect, vi } from 'vitest';
import React from 'react';

// ---------------------------------------------------------------------------
// Module mocks — DevExtreme components are not available in the test env
// ---------------------------------------------------------------------------

vi.mock('devextreme-react/data-grid', () => ({
  Column: 'Column',
  CustomRule: 'CustomRule',
  EmailRule: 'EmailRule',
  Item: 'Item',
  Lookup: 'Lookup',
  PatternRule: 'PatternRule',
  RangeRule: 'RangeRule',
  RequiredRule: 'RequiredRule',
  StringLengthRule: 'StringLengthRule',
  TotalItem: 'TotalItem',
}));

vi.mock('devextreme-react', () => ({
  Button: 'Button',
}));

vi.mock('../../shared/data/DxUtils', () => ({
  dxUtils: {
    getDataStore: vi.fn(() => ({})),
  },
}));

import { buildColumns, buildSummary, buildToolbar, getIdField } from './DxDataGridUtils';
import { FieldBuilder } from '../field/FieldBuilder';
import { FieldType } from '../field/FieldType';
import type { Field } from '../field/Field';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const makeField = (overrides: Partial<Field> = {}): Field =>
  Object.assign(
    new FieldBuilder().type(FieldType.TEXT).name('name').label('Name').build(),
    overrides,
  );

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DxDataGridUtils › getIdField', () => {
  it('returns the name of the field with isIdentity:true', () => {
    const fields: Field[] = [
      makeField({ name: 'name', isIdentity: false }),
      makeField({ name: 'id', isIdentity: true }),
      makeField({ name: 'code', isIdentity: false }),
    ];

    expect(getIdField(fields)).toBe('id');
  });

  it('returns empty string when no field has isIdentity:true', () => {
    const fields: Field[] = [
      makeField({ name: 'name', isIdentity: false }),
      makeField({ name: 'code', isIdentity: false }),
    ];

    expect(getIdField(fields)).toBe('');
  });
});

describe('DxDataGridUtils › buildToolbar', () => {
  it('returns an empty array when called with an empty array', () => {
    const result = buildToolbar([]);

    expect(result).toEqual([]);
  });

  it('returns an array with the same number of elements as the input', () => {
    const items = [
      { text: 'Add', icon: 'add', onClick: vi.fn() },
      { text: 'Export', icon: 'export', onClick: vi.fn() },
    ];

    const result = buildToolbar(items);

    expect(result).toHaveLength(2);
  });
});

describe('DxDataGridUtils › buildColumns', () => {
  it('returns null for a field with visible:false', () => {
    const fields: Field[] = [makeField({ name: 'hidden', visible: false })];

    const result = buildColumns(fields);

    expect(result[0]).toBeNull();
  });

  it('returns null for a NONE field when forceLookup is true', () => {
    const fields: Field[] = [makeField({ name: 'status', type: FieldType.NONE, visible: true })];

    const result = buildColumns(fields, true);

    expect(result[0]).toBeNull();
  });

  it('returns a React element for a visible TEXT field', () => {
    const fields: Field[] = [makeField({ name: 'name', visible: true })];

    const result = buildColumns(fields);

    // Should be a React element (not null)
    expect(result[0]).not.toBeNull();
    expect(React.isValidElement(result[0])).toBe(true);
  });

  it('returns an array with the same length as the input fields array', () => {
    const fields: Field[] = [
      makeField({ name: 'a', visible: true }),
      makeField({ name: 'b', visible: false }),
      makeField({ name: 'c', visible: true }),
    ];

    const result = buildColumns(fields);

    expect(result).toHaveLength(3);
  });
});

describe('DxDataGridUtils › buildSummary', () => {
  it('returns undefined when called with undefined', () => {
    const result = buildSummary(undefined);

    expect(result).toBeUndefined();
  });

  it('returns an array with the same number of elements as the input', () => {
    const items = [
      { column: 'total', summaryType: 'sum' as const },
      { column: 'qty', summaryType: 'count' as const },
    ];

    const result = buildSummary(items);

    expect(result).toHaveLength(2);
  });
});
