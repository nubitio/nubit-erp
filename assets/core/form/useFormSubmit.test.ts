import { describe, it, expect, vi } from 'vitest';
import { useFormSubmit, type UseFormSubmitOptions, type UseFormSubmitRefs } from './useFormSubmit';
import { FieldType } from '../field/FieldType';
import { FieldBuilder } from '../field/FieldBuilder';
import type { Field } from '../field/Field';

vi.mock('devextreme-react/data-grid', () => ({ DataGridRef: class {} }));
vi.mock('devextreme-react/form', () => ({ FormRef: class {} }));
vi.mock('devextreme/data/array_store', () => ({ default: class {} }));
vi.mock('devextreme/ui/dialog', () => ({
  confirm: vi.fn(() => Promise.resolve(false)),
}));
vi.mock('../i18n/useCoreTranslation', () => ({
  useCoreTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock('../../shared/api/HttpClient', () => ({
  httpClient: {
    post: vi.fn(() => ({ subscribe: vi.fn(() => ({ add: vi.fn() })) })),
    patch: vi.fn(() => ({ subscribe: vi.fn(() => ({ add: vi.fn() })) })),
    delete: vi.fn(() => ({ subscribe: vi.fn(() => ({ add: vi.fn() })) })),
  },
}));

const useMakeSubmitter = (fields: Field[] = [], overrides: Partial<UseFormSubmitOptions> = {}) => {
  const refs: UseFormSubmitRefs = {
    formRef: { current: null },
    gridRef: { current: null },
    uploadedFiles: { current: [] },
    isEdit: { current: false },
  };
  const emit = vi.fn();
  const getFieldValue = vi.fn().mockReturnValue([]);
  const validate = vi.fn().mockReturnValue(true);

  return useFormSubmit(
    {
      url: '/api/test',
      fields,
      detailPropertyName: 'items',
      ...overrides,
    },
    refs,
    emit,
    getFieldValue,
    validate,
  );
};

const entityField = () =>
  new FieldBuilder()
    .type(FieldType.ENTITY)
    .name('customer')
    .url('/api/customers')
    .valueField('id')
    .label('Cliente')
    .build();

const dateField = () =>
  new FieldBuilder().type(FieldType.DATE).name('issuedAt').label('Fecha').build();

const numberField = (sendAsString = false, precision = 2) => {
  const b = new FieldBuilder().type(FieldType.NUMBER).name('qty').label('Cantidad');
  if (sendAsString) b.sendAsString(true).precision(precision);
  return b.build();
};

const noneField = (isIdentity = false) => {
  const b = new FieldBuilder().type(FieldType.NONE).name('status').label('Estado');
  if (isIdentity) b.isIdentity(true);
  return b.build();
};

describe('useFormSubmit › serializeFields', () => {
  it('ENTITY field with object value serializes to IRI string', () => {
    const field = entityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ customer: { id: 5, businessName: 'Acme' } });

    expect(result.customer).toBe('/api/customers/5');
  });

  it('ENTITY field with number value serializes to IRI string', () => {
    const field = entityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ customer: 3 });

    expect(result.customer).toBe('/api/customers/3');
  });

  it('ENTITY field with null removes the field from payload', () => {
    const field = entityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ customer: null });

    expect('customer' in result).toBe(false);
  });

  it('ENTITY field with sentinel -999 removes the field from payload', () => {
    const field = entityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ customer: -999 });

    expect('customer' in result).toBe(false);
  });

  it('ENTITY field multiple:true with array of objects serializes each item to IRI', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('tags')
      .url('/api/tags')
      .valueField('id')
      .label('Tags')
      .multiple(true)
      .build();

    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({
      tags: [
        { id: 1, name: 'A' },
        { id: 2, name: 'B' },
      ],
    });

    expect(result.tags).toEqual(['/api/tags/1', '/api/tags/2']);
  });

  it('DATE field with valid date returns UTC ISO-8601 string ending with Z', () => {
    const field = dateField();
    const { serializeFields } = useMakeSubmitter([field]);

    // Use a fixed date string that Luxon can parse
    const result = serializeFields({ issuedAt: '2024-06-15T00:00:00.000' });

    expect(typeof result.issuedAt).toBe('string');
    expect(result.issuedAt).toMatch(/Z$/);
    expect(result.issuedAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  });

  it('DATE field with null removes the field from payload', () => {
    const field = dateField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ issuedAt: null });

    expect('issuedAt' in result).toBe(false);
  });

  it('NUMBER field with sendAsString:true returns string with 2 decimal places', () => {
    const field = numberField(true, 2);
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ qty: 12.5 });

    expect(result.qty).toBe('12.50');
  });

  it('NUMBER field without sendAsString returns a JS number', () => {
    const field = numberField(false);
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ qty: '7' });

    expect(result.qty).toBe(7);
    expect(typeof result.qty).toBe('number');
  });

  it('NONE field that is not identity removes the field from payload', () => {
    const field = noneField(false);
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ status: 'active' });

    expect('status' in result).toBe(false);
  });

  it('NONE field that is identity keeps the field in payload', () => {
    const field = noneField(true);
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ status: 42 });

    expect(result.status).toBe(42);
  });
});

// ---------------------------------------------------------------------------
// BUG-014: entity relation fields must always send the JSON-LD IRI in the body
// ---------------------------------------------------------------------------

describe('useFormSubmit › serializeFields › BUG-014 IRI serialization', () => {
  const parentEntityField = () =>
    new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('parent')
      .url('/api/categories')
      .valueField('id')
      .label('Categoría Padre')
      .build();

  it('ENTITY field with IRI string value preserves the IRI in the PATCH body', () => {
    const field = parentEntityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({
      parent: '/api/categories/550e8400-e29b-41d4-a716-446655440000',
    });

    expect(result.parent).toBe('/api/categories/550e8400-e29b-41d4-a716-446655440000');
  });

  it('ENTITY field with full Hydra object extracts @id and sends as IRI', () => {
    const field = parentEntityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({
      parent: {
        '@id': '/api/categories/550e8400-e29b-41d4-a716-446655440000',
        id: 1,
        name: 'Root',
      },
    });

    expect(result.parent).toBe('/api/categories/550e8400-e29b-41d4-a716-446655440000');
    // Must NOT contain the raw integer id
    expect(result.parent).not.toBe('/api/categories/1');
    expect(result.parent).not.toBe(1);
  });

  it('ENTITY field with raw integer value is converted to IRI path, not sent as bare integer', () => {
    const field = parentEntityField();
    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({ parent: 1 });

    // Must be a string (IRI path), never the bare integer 1
    expect(typeof result.parent).toBe('string');
    expect(result.parent).not.toBe(1);
    expect(result.parent).toMatch(/^\/api\/categories\//);
  });

  it('ENTITY multiple field with Hydra objects extracts @id from each item', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('tags')
      .url('/api/tags')
      .valueField('id')
      .label('Tags')
      .multiple(true)
      .build();

    const { serializeFields } = useMakeSubmitter([field]);

    const result = serializeFields({
      tags: [
        { '@id': '/api/tags/uuid-aaa', id: 1, name: 'A' },
        { '@id': '/api/tags/uuid-bbb', id: 2, name: 'B' },
      ],
    });

    expect(result.tags).toEqual(['/api/tags/uuid-aaa', '/api/tags/uuid-bbb']);
  });
});
