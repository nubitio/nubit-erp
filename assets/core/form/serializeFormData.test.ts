import { describe, it, expect } from 'vitest';
import { FieldBuilder } from '../field/FieldBuilder';
import { FieldType } from '../field/FieldType';
import { serializeFormFields, serializeDetailRows } from './serializeFormData';

const entityField = () =>
  new FieldBuilder()
    .type(FieldType.ENTITY)
    .name('customer')
    .url('/api/customers')
    .valueField('id')
    .label('Customer')
    .build();

const dateField = () =>
  new FieldBuilder().type(FieldType.DATE).name('issuedAt').label('Date').build();

const numberField = (sendAsString = false, precision = 2) => {
  const b = new FieldBuilder().type(FieldType.NUMBER).name('qty').label('Qty');
  if (sendAsString) b.sendAsString(true).precision(precision);
  return b.build();
};

const ctx = { uploadedFiles: [] };

describe('serializeFormFields', () => {
  it('serializes ENTITY object value to IRI', () => {
    const result = serializeFormFields(
      { customer: { id: 5, name: 'Acme' } },
      [entityField()],
      ctx,
    );
    expect(result.customer).toBe('/api/customers/5');
  });

  it('serializes ENTITY number value to IRI', () => {
    const result = serializeFormFields({ customer: 3 }, [entityField()], ctx);
    expect(result.customer).toBe('/api/customers/3');
  });

  it('removes ENTITY field with null value', () => {
    const result = serializeFormFields({ customer: null }, [entityField()], ctx);
    expect('customer' in result).toBe(false);
  });

  it('removes ENTITY field with sentinel -999', () => {
    const result = serializeFormFields({ customer: -999 }, [entityField()], ctx);
    expect('customer' in result).toBe(false);
  });

  it('serializes ENTITY with @id (Hydra) to full IRI', () => {
    const result = serializeFormFields(
      { customer: { '@id': '/api/customers/uuid-123', id: 1 } },
      [entityField()],
      ctx,
    );
    expect(result.customer).toBe('/api/customers/uuid-123');
  });

  it('serializes ENTITY with _iri valueField and no @id using id fallback (plain JSON response)', () => {
    // Hydra mapper always sets valueField to '_iri', but the grid loads data as
    // plain JSON (no @id on nested objects). The serializer must fall back to
    // entityValue['id'] to avoid sending "/api/categories/undefined".
    const iriField = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('category')
      .url('/api/categories')
      .valueField('_iri')
      .label('Category')
      .build();
    const result = serializeFormFields(
      { category: { id: 7, name: 'Electronics' } },
      [iriField],
      ctx,
    );
    expect(result.category).toBe('/api/categories/7');
  });

  it('serializes DATE field to UTC ISO-8601', () => {
    const result = serializeFormFields(
      { issuedAt: '2024-06-15T00:00:00.000' },
      [dateField()],
      ctx,
    );
    expect(typeof result.issuedAt).toBe('string');
    expect(result.issuedAt).toMatch(/Z$/);
  });

  it('removes DATE field with null', () => {
    const result = serializeFormFields({ issuedAt: null }, [dateField()], ctx);
    expect('issuedAt' in result).toBe(false);
  });

  it('coerces NUMBER field to JS number', () => {
    const result = serializeFormFields({ qty: '7' }, [numberField()], ctx);
    expect(result.qty).toBe(7);
  });

  it('formats NUMBER sendAsString with precision', () => {
    const result = serializeFormFields({ qty: 12.5 }, [numberField(true, 2)], ctx);
    expect(result.qty).toBe('12.50');
  });

  it('removes NONE fields that are not identity', () => {
    const field = new FieldBuilder().type(FieldType.NONE).name('internal').label('X').build();
    const result = serializeFormFields({ internal: 'val' }, [field], ctx);
    expect('internal' in result).toBe(false);
  });

  it('applies uploaded file IRIs', () => {
    const field = new FieldBuilder().type(FieldType.TEXT).name('avatar').label('Avatar').build();
    const result = serializeFormFields(
      { avatar: 'old.png' },
      [field],
      { uploadedFiles: [{ name: 'avatar', iri: '/api/media/123' }] },
    );
    expect(result.avatar).toBe('/api/media/123');
  });

  it('applies computed fields', () => {
    const field = {
      ...new FieldBuilder().type(FieldType.NUMBER).name('total').label('Total').build(),
      computed: (data: Record<string, unknown>) => Number(data.qty ?? 0) * Number(data.price ?? 0),
    };
    const result = serializeFormFields(
      { qty: 5, price: 10, total: 0 },
      [field],
      ctx,
    );
    expect(result.total).toBe(50);
  });
});

describe('serializeDetailRows', () => {
  it('serializes entity object fields in detail rows to IRIs', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('product')
      .url('/api/products')
      .valueField('id')
      .label('Product')
      .build();

    const rows = [{ product: { id: 1, name: 'Item A' }, qty: 2 }];
    const result = serializeDetailRows(rows, [field], 'id', false);

    expect(result[0].product).toBe('/api/products/1');
  });

  it('serializes primitive entity IDs in detail rows to IRIs', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('product')
      .url('/api/products')
      .valueField('id')
      .label('Product')
      .build();

    const rows = [{ product: 'product-uuid', qty: 2 }];
    const result = serializeDetailRows(rows, [field], 'id', false);

    expect(result[0].product).toBe('/api/products/product-uuid');
  });

  it('removes detail identity field for new rows', () => {
    const field = new FieldBuilder().type(FieldType.TEXT).name('name').label('Name').build();
    const rows = [{ id: 'temp-uuid', name: 'Row 1' }];
    const result = serializeDetailRows(rows, [field], 'id', false);

    expect('id' in result[0]).toBe(false);
  });

  it('keeps numeric detail identity field for edit mode', () => {
    const field = new FieldBuilder().type(FieldType.TEXT).name('name').label('Name').build();
    const rows = [{ id: 42, name: 'Row 1' }];
    const result = serializeDetailRows(rows, [field], 'id', true);

    expect(result[0].id).toBe(42);
  });

  it('removes string detail identity field even in edit mode', () => {
    const field = new FieldBuilder().type(FieldType.TEXT).name('name').label('Name').build();
    const rows = [{ id: 'temp-uuid', name: 'Row 1' }];
    const result = serializeDetailRows(rows, [field], 'id', true);

    expect('id' in result[0]).toBe(false);
  });
});
