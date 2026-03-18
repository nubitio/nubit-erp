import { describe, it, expect, vi } from 'vitest';
import React from 'react';

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

import {
  buildColumns,
  buildSummary,
  buildToolbar,
  buildEntityDisplayValue,
  getIdField,
} from './DxDataGridUtils';
import { FieldBuilder } from '../field/FieldBuilder';
import { FieldType } from '../field/FieldType';
import type { Field } from '../field/Field';

const makeField = (overrides: Partial<Field> = {}): Field =>
  Object.assign(
    new FieldBuilder().type(FieldType.TEXT).name('name').label('Name').build(),
    overrides,
  );

const makeEntityField = (overrides: Partial<Field> = {}): Field =>
  Object.assign(
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('parent')
      .label('Parent')
      .url('/api/categories')
      .valueField('id')
      .textField('name')
      .build(),
    overrides,
  );

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

// ---------------------------------------------------------------------------
// BUG-015: Entity column display value
// ---------------------------------------------------------------------------

describe('DxDataGridUtils › buildEntityDisplayValue', () => {
  it('extracts textField from a nested object (the primary bug fix)', () => {
    const field = makeEntityField();
    const fn = buildEntityDisplayValue(field);

    const rowData = { parent: { id: '2f1f2a69-...', name: 'OTROS' } };
    expect(fn(rowData)).toBe('OTROS');
  });

  it('returns an empty string for null entity value', () => {
    const field = makeEntityField();
    const fn = buildEntityDisplayValue(field);

    expect(fn({ parent: null })).toBe('');
  });

  it('returns an empty string for undefined entity value', () => {
    const field = makeEntityField();
    const fn = buildEntityDisplayValue(field);

    expect(fn({ parent: undefined })).toBe('');
  });

  it('returns the string as-is when the value is already a plain string (IRI)', () => {
    const field = makeEntityField();
    const fn = buildEntityDisplayValue(field);

    expect(fn({ parent: '/api/categories/2f1f2a69-...' })).toBe('/api/categories/2f1f2a69-...');
  });

  it('falls back to "name" when textField is not present but name is', () => {
    const field = makeEntityField({ textField: 'title' });
    const fn = buildEntityDisplayValue(field);

    // Object has no "title" but does have "name" — falls back to "name"
    const rowData = { parent: { id: '123', name: 'Fallback Name' } };
    expect(fn(rowData)).toBe('Fallback Name');
  });

  it('uses textField over name fallback when both are present', () => {
    const field = makeEntityField({ textField: 'code' });
    const fn = buildEntityDisplayValue(field);

    const rowData = { parent: { id: '123', code: 'CAT-01', name: 'Category' } };
    expect(fn(rowData)).toBe('CAT-01');
  });

  it('does not apply calculateDisplayValue when the field has a custom formatter', () => {
    // buildColumns should NOT attach calculateDisplayValue when formatter is set,
    // so the custom formatter takes full control.
    const customFormatter = vi.fn();
    const fields: Field[] = [makeEntityField({ visible: true, formatter: customFormatter })];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    // The column's calculateDisplayValue prop should be undefined when a formatter is set
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(props['calculateDisplayValue']).toBeUndefined();
  });

  it('attaches calculateDisplayValue when the entity field has no formatter', () => {
    const fields: Field[] = [makeEntityField({ visible: true, formatter: undefined })];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(typeof props['calculateDisplayValue']).toBe('function');
  });

  // BUG-009: IRI-valued entity fields (@id valueField) must NOT use calculateDisplayValue
  // so the DevExtreme <Lookup> can resolve the IRI → display name via its data source.
  it('does NOT attach calculateDisplayValue when valueField is "@id" (IRI-based lookup)', () => {
    const fields: Field[] = [
      makeEntityField({ visible: true, formatter: undefined, valueField: '@id' }),
    ];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(props['calculateDisplayValue']).toBeUndefined();
  });

  // BUG-010: IRI-mode lookup (_iri valueField) must NOT use calculateDisplayValue.
  // The <Lookup> resolves the display name via its iriMode dataSource (which maps
  // "@id" → "_iri" on every loaded item). calculateDisplayValue would return the
  // raw IRI string instead of the human-readable name.
  it('does NOT attach calculateDisplayValue when valueField is "_iri" (iriMode lookup)', () => {
    const fields: Field[] = [
      makeEntityField({ visible: true, formatter: undefined, valueField: '_iri' }),
    ];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(props['calculateDisplayValue']).toBeUndefined();
  });
});
