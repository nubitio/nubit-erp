import { describe, it, expect, vi } from 'vitest';
import React from 'react';

vi.mock('devextreme-react/data-grid', () => ({
  Column: 'Column',
  CustomRule: 'CustomRule',
  EmailRule: 'EmailRule',
  HeaderFilter: 'HeaderFilter',
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
  buildEntityCellValue,
  buildEntityCellRender,
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

    expect(result[0]).not.toBeNull();
    expect(React.isValidElement(result[0])).toBe(true);
  });

  it('does not make non-filterable fields read-only', () => {
    const fields: Field[] = [makeField({ name: 'discountPercent', filterable: false })];

    const result = buildColumns(fields);

    expect(React.isValidElement(result[0])).toBe(true);
    expect(
      (result[0] as React.ReactElement<{ editorOptions: { readOnly: boolean } }>).props
        .editorOptions.readOnly,
    ).toBe(false);
  });

  it('preserves explicit read-only fields', () => {
    const fields: Field[] = [makeField({ name: 'total', filterable: false, readonly: true })];

    const result = buildColumns(fields);

    expect(React.isValidElement(result[0])).toBe(true);
    expect(
      (result[0] as React.ReactElement<{ editorOptions: { readOnly: boolean } }>).props
        .editorOptions.readOnly,
    ).toBe(true);
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

  it('attaches calculateCellValue (not calculateDisplayValue) when entity field uses valueField="id"', () => {
    // BUG-011: UUID-mode lookup ('id' valueField) must use calculateCellValue to
    // extract the UUID from IRI strings, allowing the Lookup to resolve the display name.
    const fields: Field[] = [makeEntityField({ visible: true, formatter: undefined })];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    // calculateDisplayValue must NOT be set for 'id' valueField — calculateCellValue handles it
    expect(props['calculateDisplayValue']).toBeUndefined();
    // calculateCellValue MUST be set to handle IRI → UUID extraction
    expect(typeof props['calculateCellValue']).toBe('function');
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

  it('attaches calculateCellValue and cellRender for entity fields with non-IRI, non-id valueField', () => {
    // For 'code'-keyed fields: calculateCellValue extracts the primitive for the Lookup
    // (filtering/editing), and cellRender reads the label directly from the embedded object
    // because DevExtreme ignores calculateDisplayValue when a <Lookup> child is present.
    const fields: Field[] = [
      makeEntityField({ visible: true, formatter: undefined, valueField: 'code' }),
    ];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(typeof props['cellRender']).toBe('function');
    expect(typeof props['calculateCellValue']).toBe('function');
    expect(props['calculateDisplayValue']).toBeUndefined();
  });
});

// ---------------------------------------------------------------------------
// BUG-011: buildEntityCellValue — IRI → UUID extraction for id-based lookups
// ---------------------------------------------------------------------------

describe('DxDataGridUtils › buildEntityCellValue', () => {
  it('extracts UUID from an IRI string', () => {
    const field = makeEntityField();
    const fn = buildEntityCellValue(field);

    expect(fn({ parent: '/api/categories/abc-uuid-123' })).toBe('abc-uuid-123');
  });

  it('returns a plain UUID string as-is (no double-extraction)', () => {
    const field = makeEntityField();
    const fn = buildEntityCellValue(field);

    expect(fn({ parent: 'abc-uuid-123' })).toBe('abc-uuid-123');
  });

  it('extracts id from a full entity object', () => {
    const field = makeEntityField();
    const fn = buildEntityCellValue(field);

    const result = fn({ parent: { id: 'abc-uuid-123', name: 'Category' } });
    expect(result).toBe('abc-uuid-123');
  });

  it('returns null for a null value', () => {
    const field = makeEntityField();
    const fn = buildEntityCellValue(field);

    expect(fn({ parent: null })).toBeNull();
  });

  it('returns undefined for a missing value', () => {
    const field = makeEntityField();
    const fn = buildEntityCellValue(field);

    expect(fn({ parent: undefined })).toBeUndefined();
  });

  it('handles nested IRI with multiple path segments correctly', () => {
    const field = makeEntityField();
    const fn = buildEntityCellValue(field);

    expect(fn({ parent: '/api/warehouses/550e8400-e29b-41d4-a716-446655440000' })).toBe(
      '550e8400-e29b-41d4-a716-446655440000',
    );
  });

  it('attaches calculateCellValue to column when valueField is "id" and no formatter', () => {
    const fields: Field[] = [makeEntityField({ visible: true })];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(typeof props['calculateCellValue']).toBe('function');
  });

  it('does NOT attach calculateCellValue when a formatter is set', () => {
    const customFormatter = vi.fn();
    const fields: Field[] = [makeEntityField({ visible: true, formatter: customFormatter })];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(props['calculateCellValue']).toBeUndefined();
  });

  it('does NOT attach calculateCellValue when valueField is "_iri"', () => {
    const fields: Field[] = [
      makeEntityField({ visible: true, formatter: undefined, valueField: '_iri' }),
    ];

    const result = buildColumns(fields);
    const element = result[0];

    expect(React.isValidElement(element)).toBe(true);
    const props = (element as React.ReactElement<Record<string, unknown>>).props;
    expect(props['calculateCellValue']).toBeUndefined();
  });

  it('extracts code from an object when valueField is "code" (VoucherType fix)', () => {
    const field = makeEntityField({ valueField: 'code' });
    const fn = buildEntityCellValue(field);

    // buildEntityCellValue still works for object extraction (legacy use)
    expect(fn({ parent: { code: '01', name: 'Factura' } })).toBe('01');
  });

  it('returns plain string as-is when valueField is "code"', () => {
    const field = makeEntityField({ valueField: 'code' });
    const fn = buildEntityCellValue(field);

    expect(fn({ parent: '01' })).toBe('01');
  });
});

// ---------------------------------------------------------------------------
// buildEntityCellRender — direct label extraction for code-keyed entity columns
// ---------------------------------------------------------------------------

describe('DxDataGridUtils › buildEntityCellRender', () => {
  it('reads textField from an embedded object (VoucherType fix)', () => {
    const field = makeEntityField({ name: 'voucherType', valueField: 'code', textField: 'name' });
    const fn = buildEntityCellRender(field);

    expect(fn({ data: { voucherType: { code: '01', name: 'Factura' } } })).toBe('Factura');
  });

  it('reads textField from an id-keyed embedded object', () => {
    const field = makeEntityField({ name: 'warehouse', valueField: 'id', textField: 'name' });
    const fn = buildEntityCellRender(field);

    expect(fn({ data: { warehouse: { id: 'uuid-1', name: 'Almacen principal' } } })).toBe(
      'Almacen principal',
    );
  });

  it('falls back to obj.name when textField is not present', () => {
    const field = makeEntityField({ name: 'voucherType', valueField: 'code', textField: 'label' });
    const fn = buildEntityCellRender(field);

    expect(fn({ data: { voucherType: { code: '01', name: 'Factura' } } })).toBe('Factura');
  });

  it('falls back to obj.businessName when textField and name are not present', () => {
    const field = makeEntityField({ name: 'supplier', valueField: 'id', textField: 'label' });
    const fn = buildEntityCellRender(field);

    expect(
      fn({ data: { supplier: { id: 'uuid-1', businessName: 'GINA LIZBETH CORI' } } }),
    ).toBe('GINA LIZBETH CORI');
  });

  it('returns the plain string value when field is a primitive', () => {
    const field = makeEntityField({ name: 'voucherType', valueField: 'code', textField: 'name' });
    const fn = buildEntityCellRender(field);

    expect(fn({ data: { voucherType: '01' } })).toBe('01');
  });

  it('returns null for primitive id values so Lookup can resolve display', () => {
    const field = makeEntityField({ name: 'warehouse', valueField: 'id', textField: 'name' });
    const fn = buildEntityCellRender(field);

    expect(fn({ data: { warehouse: 'uuid-1' }, value: 'uuid-1' })).toBeNull();
  });

  it('returns null when value is null or missing', () => {
    const field = makeEntityField({ name: 'voucherType', valueField: 'code', textField: 'name' });
    const fn = buildEntityCellRender(field);

    expect(fn({ data: {} })).toBeNull();
    expect(fn({ data: { voucherType: null } })).toBeNull();
  });

  it('reads textField from data even when value is the computed primitive (calculateCellValue scenario)', () => {
    // DevExtreme passes { data: <original row>, value: <calculateCellValue result> }.
    // The original row still has the full embedded object; value is already "01".
    // cellRender must read the name from data, NOT from value.
    const field = makeEntityField({ name: 'voucherType', valueField: 'code', textField: 'name' });
    const fn = buildEntityCellRender(field);

    const result = fn({ data: { voucherType: { code: '01', name: 'Factura' } }, value: '01' });
    expect(result).toBe('Factura');
  });

  it('falls back to value when data field has been replaced by primitive (worst-case mutation)', () => {
    // If somehow rowData[field.name] is already the primitive "01", fall back to value.
    const field = makeEntityField({ name: 'voucherType', valueField: 'code', textField: 'name' });
    const fn = buildEntityCellRender(field);

    // rowData has primitive, value also primitive — returns String(val) = "01"
    expect(fn({ data: { voucherType: '01' }, value: '01' })).toBe('01');
  });
});
