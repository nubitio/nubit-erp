import { describe, it, expect } from 'vitest';
import { FieldBuilder } from '../field/FieldBuilder';
import { FieldType } from '../field/FieldType';
import { buildEmptyRow, normalizeFormData } from './DxFormDataTransform';

describe('buildEmptyRow', () => {
  it('sets undefined for fields without defaultValue', () => {
    const fields = [new FieldBuilder().name('name').label('Name').build()];
    const row = buildEmptyRow(fields);
    expect(row.name).toBeUndefined();
  });

  it('applies static default values', () => {
    const fields = [
      new FieldBuilder().name('status').label('Status').defaultValue('draft').build(),
    ];
    const row = buildEmptyRow(fields);
    expect(row.status).toBe('draft');
  });

  it('calls function default values', () => {
    const fields = [
      new FieldBuilder()
        .name('ref')
        .label('Ref')
        .defaultValue(() => 'computed')
        .build(),
    ];
    const row = buildEmptyRow(fields);
    expect(row.ref).toBe('computed');
  });
});

describe('normalizeFormData', () => {
  it('strips FILE fields', () => {
    const fields = [new FieldBuilder().type(FieldType.FILE).name('avatar').label('Avatar').build()];
    const result = normalizeFormData({ avatar: 'something', other: 1 }, fields);
    expect('avatar' in result).toBe(false);
    expect(result.other).toBe(1);
  });

  it('blanks PASSWORD fields', () => {
    const fields = [
      new FieldBuilder().type(FieldType.PASSWORD).name('pw').label('Password').build(),
    ];
    const result = normalizeFormData({ pw: 'secret' }, fields);
    expect(result.pw).toBe('');
  });

  it('parses DATE string values', () => {
    const fields = [
      new FieldBuilder().type(FieldType.DATE).name('createdAt').label('Created').build(),
    ];
    const result = normalizeFormData({ createdAt: '2024-06-15' }, fields);
    expect(result.createdAt).toBeInstanceOf(Date);
  });

  it('sets null for non-string/non-object DATE values', () => {
    const fields = [
      new FieldBuilder().type(FieldType.DATE).name('createdAt').label('Created').build(),
    ];
    const result = normalizeFormData({ createdAt: undefined }, fields);
    expect(result.createdAt).toBeNull();
  });

  it('normalizes ENTITY IRI string to valueField object', () => {
    const fields = [
      new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('customer')
        .url('/api/customers')
        .valueField('id')
        .label('Customer')
        .build(),
    ];
    const result = normalizeFormData({ customer: '/api/customers/42' }, fields);
    expect(result.customer).toEqual({ id: '42' });
  });

  it('normalizes ENTITY Hydra object (@id) to valueField object', () => {
    const fields = [
      new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('customer')
        .url('/api/customers')
        .valueField('id')
        .label('Customer')
        .build(),
    ];
    const result = normalizeFormData(
      { customer: { '@id': '/api/customers/uuid-123', id: 1, name: 'Acme' } },
      fields,
    );
    expect(result.customer).toEqual({ id: 'uuid-123' });
  });

  it('normalizes plain ENTITY object to valueField object', () => {
    const fields = [
      new FieldBuilder()
        .type(FieldType.ENTITY)
        .name('category')
        .url('/api/categories')
        .valueField('id')
        .label('Category')
        .build(),
    ];
    const result = normalizeFormData({ category: { id: 'abc', name: 'Root' } }, fields);
    expect(result.category).toEqual({ id: 'abc' });
    expect(fields[0].loadOptions[0]?.prependData).toEqual([{ id: 'abc', name: 'Root' }]);
  });

  it('preserves hydra ENTITY objects in prependData for edit-time lookups', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('category')
      .url('/api/categories')
      .valueField('id')
      .label('Category')
      .build();

    normalizeFormData(
      { category: { '@id': '/api/categories/uuid-1', id: 'uuid-1', name: 'Root' } },
      [field],
    );

    expect(field.loadOptions[0]?.prependData).toEqual([
      { '@id': '/api/categories/uuid-1', id: 'uuid-1', name: 'Root' },
    ]);
  });

  it('applies loadOptions prependData fallback for null ENTITY', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('customer')
      .url('/api/customers')
      .valueField('id')
      .label('Customer')
      .build();
    // Manually set loadOptions with prependData for null fallback
    field.loadOptions = [{ url: '', prependData: [{ id: -999, name: '-- ninguno --' }] }];
    const result = normalizeFormData({ customer: null }, [field]);
    expect(result.customer).toEqual({ id: -999, name: '-- ninguno --' });
  });
});
