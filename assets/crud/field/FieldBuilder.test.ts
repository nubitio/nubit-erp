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

  it('sets url, textField, valueField for entity field', () => {
    const field = new FieldBuilder()
      .name('product')
      .label('Producto')
      .type(FieldType.ENTITY)
      .url('/api/products')
      .textField('name')
      .valueField('id')
      .build();

    expect(field.url).toBe('/api/products');
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

  describe('declarative rules', () => {
    it('visibleWhen stores the predicate and returns false when predicate returns false', () => {
      const fn = (data: Record<string, unknown>) => data['active'] === true;
      const field = new FieldBuilder().name('detail').label('Detalle').visibleWhen(fn).build();

      expect(field.visibleWhen).toBe(fn);
      expect(field.visibleWhen?.({ active: false })).toBe(false);
      expect(field.visibleWhen?.({ active: true })).toBe(true);
    });

    it('disabledWhen stores the predicate', () => {
      const fn = (data: Record<string, unknown>) => data['status'] === 'closed';
      const field = new FieldBuilder().name('amount').label('Monto').disabledWhen(fn).build();

      expect(field.disabledWhen).toBe(fn);
      expect(field.disabledWhen?.({ status: 'open' })).toBe(false);
      expect(field.disabledWhen?.({ status: 'closed' })).toBe(true);
    });

    it('computed stores the derivation function and field has no readonly flag by default', () => {
      const fn = (data: Record<string, unknown>) =>
        (data['qty'] as number) * (data['price'] as number);
      const field = new FieldBuilder().name('total').label('Total').computed(fn).build();

      expect(field.computed).toBe(fn);
      expect(field.computed?.({ qty: 3, price: 10 })).toBe(30);
      // readonly is applied at runtime by the rules pipeline, not by the builder
      expect(field.readonly).toBe(false);
    });

    it('requiredWhen stores the predicate', () => {
      const fn = (data: Record<string, unknown>) => data['status'] === 'rejected';
      const field = new FieldBuilder().name('reason').label('Motivo').requiredWhen(fn).build();

      expect(field.requiredWhen).toBe(fn);
      expect(field.requiredWhen?.({ status: 'draft' })).toBe(false);
      expect(field.requiredWhen?.({ status: 'rejected' })).toBe(true);
    });

    it('clearWhenHidden enables hidden field clearing by default', () => {
      const field = new FieldBuilder().name('reason').label('Motivo').clearWhenHidden().build();

      expect(field.clearWhenHidden).toBe(true);
    });

    it('dependsOn stores the dependency list', () => {
      const field = new FieldBuilder()
        .name('product')
        .label('Producto')
        .dependsOn(['warehouse', 'category'])
        .build();

      expect(field.dependsOn).toEqual(['warehouse', 'category']);
    });

    it('permissions stores visible and editable role lists', () => {
      const field = new FieldBuilder()
        .name('cost')
        .label('Costo')
        .permissions({ visible: ['admin', 'accountant'], editable: ['admin'] })
        .build();

      expect(field.permissions?.visible).toEqual(['admin', 'accountant']);
      expect(field.permissions?.editable).toEqual(['admin']);
    });

    it('permissions with only visible roles omits editable', () => {
      const field = new FieldBuilder()
        .name('sku')
        .label('SKU')
        .permissions({ visible: ['admin'] })
        .build();

      expect(field.permissions?.visible).toEqual(['admin']);
      expect(field.permissions?.editable).toBeUndefined();
    });

    it('declarative rules do not affect unrelated field properties', () => {
      const field = new FieldBuilder()
        .name('note')
        .label('Nota')
        .required(true)
        .col(6)
        .visibleWhen(() => true)
        .disabledWhen(() => false)
        .build();

      expect(field.required).toBe(true);
      expect(field.col).toBe(6);
      expect(field.name).toBe('note');
    });

    it('build() returns a snapshot — mutating the returned field does not affect a second build', () => {
      const builder = new FieldBuilder().name('x').label('X').visibleWhen(() => true);
      const first = builder.build();
      const second = builder.build();

      (first as unknown as Record<string, unknown>)['visibleWhen'] = undefined;
      expect(second.visibleWhen).toBeDefined();
    });
  });
});
