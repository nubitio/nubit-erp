import { describe, expect, it } from 'vitest';
import { FieldBuilder } from './FieldBuilder';
import { FieldType } from './FieldType';

describe('FieldBuilder', () => {
  it('builds a field with default values', () => {
    const field = new FieldBuilder().name('code').label('Código').build();

    expect(field.name).toBe('code');
    expect(field.label).toBe('Código');
    expect(field.type).toBe(FieldType.TEXT);
    expect(field.required).toBe(false);
    expect(field.visible).toBe(true);
    expect(field.visibleOnForm).toBe(true);
    expect(field.sortable).toBe(true);
    expect(field.filterable).toBe(true);
    expect(field.col).toBe(12);
  });

  it('chains fluent setters without mutating previous instances', () => {
    const base = new FieldBuilder().name('base').label('Base');
    const derived = base.required(true).col(6);

    const baseField = new FieldBuilder().name('base').label('Base').build();
    const derivedField = derived.build();

    expect(baseField.required).toBe(false);
    expect(derivedField.required).toBe(true);
    expect(derivedField.col).toBe(6);
  });

  it('sets field type correctly', () => {
    const numField = new FieldBuilder().name('qty').label('Cantidad').type(FieldType.NUMBER).build();
    expect(numField.type).toBe(FieldType.NUMBER);

    const dateField = new FieldBuilder().name('date').label('Fecha').type(FieldType.DATE).build();
    expect(dateField.type).toBe(FieldType.DATE);
  });

  it('marks field as required', () => {
    const field = new FieldBuilder().name('ruc').label('RUC').required(true).build();
    expect(field.required).toBe(true);
  });

  it('hides field from grid and form independently', () => {
    const gridHidden = new FieldBuilder().name('f').label('F').hidden(true).build();
    expect(gridHidden.hidden).toBe(true);
    expect(gridHidden.visibleOnForm).toBe(true);

    const formHidden = new FieldBuilder().name('f').label('F').visibleOnForm(false).build();
    expect(formHidden.hidden).toBe(false);
    expect(formHidden.visibleOnForm).toBe(false);
  });

  it('sets defaultValue', () => {
    const field = new FieldBuilder().name('status').label('Estado').defaultValue('active').build();
    expect(field.defaultValue).toBe('active');
  });

  it('sets url and mode for remote entity field', () => {
    const field = new FieldBuilder()
      .name('product')
      .label('Producto')
      .type(FieldType.ENTITY)
      .url('/api/products')
      .mode('remote')
      .textField('name')
      .valueField('id')
      .build();

    expect(field.url).toBe('/api/products');
    expect(field.mode).toBe('remote');
    expect(field.textField).toBe('name');
    expect(field.valueField).toBe('id');
  });

  it('sets precision for numeric fields', () => {
    const field = new FieldBuilder().name('price').label('Precio').precision(2).build();
    expect(field.precision).toBe(2);
  });

  it('marks field as readonly and disabled independently', () => {
    const ro = new FieldBuilder().name('f').label('F').readonly(true).build();
    expect(ro.readonly).toBe(true);
    expect(ro.disabled).toBe(false);

    const dis = new FieldBuilder().name('f').label('F').disabled(true).build();
    expect(dis.disabled).toBe(true);
    expect(dis.readonly).toBe(false);
  });

  it('isIdentity marks field as identity', () => {
    const field = new FieldBuilder().name('id').label('ID').isIdentity(true).build();
    expect(field.isIdentity).toBe(true);
  });
});
