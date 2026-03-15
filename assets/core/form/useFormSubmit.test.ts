import { describe, it, expect, vi } from 'vitest';
import { useFormSubmit } from './useFormSubmit';
import { FieldType } from '../field/FieldType';
import { FieldBuilder } from '../field/FieldBuilder';
import type { Field } from '../field/Field';

// ---------------------------------------------------------------------------
// Module mocks — must be declared before any imports that pull them in
// ---------------------------------------------------------------------------

vi.mock('devextreme-react/data-grid', () => ({ DataGridRef: class {} }));
vi.mock('devextreme-react/form', () => ({ FormRef: class {} }));
vi.mock('devextreme/data/array_store', () => ({ default: class {} }));
vi.mock('devextreme/ui/dialog', () => ({
  confirm: vi.fn(() => Promise.resolve(false)),
}));
vi.mock('../../shared/api/HttpClient', () => ({
  httpClient: {
    post: vi.fn(() => ({ subscribe: vi.fn(() => ({ add: vi.fn() })) })),
    patch: vi.fn(() => ({ subscribe: vi.fn(() => ({ add: vi.fn() })) })),
    delete: vi.fn(() => ({ subscribe: vi.fn(() => ({ add: vi.fn() })) })),
  },
}));

// ---------------------------------------------------------------------------
// Helper: instantiate the hook with minimal refs and no real HTTP
// ---------------------------------------------------------------------------

const makeSubmitter = (fields: Field[] = [], overrides: Record<string, any> = {}) => {
  const refs = {
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
    refs as any,
    emit,
    getFieldValue,
    validate,
  );
};

// ---------------------------------------------------------------------------
// Helpers to build minimal Field objects
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useFormSubmit › serializeFields', () => {
  // 1. ENTITY field with object value → serializes to IRI string
  it('ENTITY field with object value serializes to IRI string', () => {
    const field = entityField();
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ customer: { id: 5, businessName: 'Acme' } });

    expect(result.customer).toBe('/api/customers/5');
  });

  // 2. ENTITY field with number value → serializes to IRI string
  it('ENTITY field with number value serializes to IRI string', () => {
    const field = entityField();
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ customer: 3 });

    expect(result.customer).toBe('/api/customers/3');
  });

  // 3. ENTITY field with null → removes field from payload
  it('ENTITY field with null removes the field from payload', () => {
    const field = entityField();
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ customer: null });

    expect('customer' in result).toBe(false);
  });

  // 4. ENTITY field with sentinel -999 → removes field from payload
  it('ENTITY field with sentinel -999 removes the field from payload', () => {
    const field = entityField();
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ customer: -999 });

    expect('customer' in result).toBe(false);
  });

  // 5. ENTITY field multiple:true with array of objects → serializes each to IRI
  it('ENTITY field multiple:true with array of objects serializes each item to IRI', () => {
    const field = new FieldBuilder()
      .type(FieldType.ENTITY)
      .name('tags')
      .url('/api/tags')
      .valueField('id')
      .label('Tags')
      .multiple(true)
      .build();

    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({
      tags: [
        { id: 1, name: 'A' },
        { id: 2, name: 'B' },
      ],
    });

    expect(result.tags).toEqual(['/api/tags/1', '/api/tags/2']);
  });

  // 6. DATE field with valid date → returns ISO-8601 string with Z suffix
  it('DATE field with valid date returns UTC ISO-8601 string ending with Z', () => {
    const field = dateField();
    const { serializeFields } = makeSubmitter([field]);

    // Use a fixed date string that Luxon can parse
    const result = serializeFields({ issuedAt: '2024-06-15T00:00:00.000' });

    expect(typeof result.issuedAt).toBe('string');
    expect(result.issuedAt).toMatch(/Z$/);
    expect(result.issuedAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  });

  // 7. DATE field with null → removes field from payload
  it('DATE field with null removes the field from payload', () => {
    const field = dateField();
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ issuedAt: null });

    expect('issuedAt' in result).toBe(false);
  });

  // 8. NUMBER field with sendAsString:true → returns string with 2 decimals
  it('NUMBER field with sendAsString:true returns string with 2 decimal places', () => {
    const field = numberField(true, 2);
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ qty: 12.5 });

    expect(result.qty).toBe('12.50');
  });

  // 9. NUMBER field normal → returns JS number
  it('NUMBER field without sendAsString returns a JS number', () => {
    const field = numberField(false);
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ qty: '7' });

    expect(result.qty).toBe(7);
    expect(typeof result.qty).toBe('number');
  });

  // 10. NONE field non-identity → removes field from payload
  it('NONE field that is not identity removes the field from payload', () => {
    const field = noneField(false);
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ status: 'active' });

    expect('status' in result).toBe(false);
  });

  // 11. NONE field identity → keeps the field in payload
  it('NONE field that is identity keeps the field in payload', () => {
    const field = noneField(true);
    const { serializeFields } = makeSubmitter([field]);

    const result = serializeFields({ status: 42 });

    expect(result.status).toBe(42);
  });
});
