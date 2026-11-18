import { describe, expect, it } from 'vitest';
import {
  CUSTOMER_FIELDS,
  MESSAGES,
  emptyCustomerForm,
  priceListOf,
  toFormValues,
  toPayload,
  validateBusinessName,
  validateContact,
  validateDocument,
  validateEmail,
  validatePhone,
  type Customer,
} from './customerForm';

const customer: Customer = {
  id: 7,
  ruc: '20100000009',
  businessName: 'Andina Retail SAC',
  fiscalAddress: 'Av. Ejército 100',
  phone: '+51987111001',
  email: 'ventas@andina.pe',
  collectionContactName: 'Rosa',
  priceList: { '@id': '/api/price-lists/3', id: 3, name: 'Mayorista' },
  isActive: true,
};

describe('validateDocument', () => {
  it.each([
    ['a DNI (8 digits, no checksum)', '12345678'],
    ['a valid RUC', '20100000009'],
    ['a RUC with surrounding spaces', ' 20100000009 '],
  ])('accepts %s', (_l, value) => expect(validateDocument(value)).toBe(true));

  it('requires a value', () => {
    expect(validateDocument('')).toBe(MESSAGES.documentRequired);
    expect(validateDocument('   ')).toBe(MESSAGES.documentRequired);
  });

  it('accepts digits only', () => {
    expect(validateDocument('2010000000A')).toBe(MESSAGES.documentDigits);
    expect(validateDocument('1234-5678')).toBe(MESSAGES.documentDigits);
  });

  it.each(['1234567', '123456789012'])('requires 8 to 11 digits (%s)', (value) => {
    expect(validateDocument(value)).toBe(MESSAGES.documentLength);
  });

  it('rejects an 11-digit number whose checksum is wrong', () => {
    expect(validateDocument('20100000001')).toBe(MESSAGES.documentInvalid);
  });

  it('rejects a 9 or 10 digit number, which is neither a DNI nor a RUC', () => {
    expect(validateDocument('123456789')).toBe(MESSAGES.documentInvalid);
    expect(validateDocument('1234567890')).toBe(MESSAGES.documentInvalid);
  });
});

describe('optional fields', () => {
  it('requires a business name', () => {
    expect(validateBusinessName('')).toBe(MESSAGES.nameRequired);
    expect(validateBusinessName('  ')).toBe(MESSAGES.nameRequired);
    expect(validateBusinessName('Acme')).toBe(true);
  });

  it.each(['', '  ', '+51987111001', '987111001', '51 987111001', '+51-987111001'])(
    'accepts the phone %j',
    (v) => {
      expect(validatePhone(v)).toBe(true);
    },
  );

  it.each(['abc', '12', '0987111001', '+51 12'])('rejects the phone %j', (v) => {
    expect(validatePhone(v)).toBe(MESSAGES.phoneInvalid);
  });

  it.each(['', 'a@b.pe', 'cliente@example.test'])('accepts the e-mail %j', (v) => {
    expect(validateEmail(v)).toBe(true);
  });

  it.each(['correo-invalido', 'a@b', '@b.pe', 'a b@c.pe'])('rejects the e-mail %j', (v) => {
    expect(validateEmail(v)).toBe(MESSAGES.emailInvalid);
  });

  it('caps the collection contact at 120 characters, like the server', () => {
    expect(validateContact('a'.repeat(120))).toBe(true);
    expect(validateContact('a'.repeat(121))).toBe(MESSAGES.contactTooLong);
  });
});

describe('toPayload', () => {
  it('sends blank optional text as null, because the server validates non-empty values', () => {
    expect(
      toPayload({
        ...emptyCustomerForm(),
        ruc: '20100000009',
        businessName: 'Acme',
        phone: '  ',
        email: '',
      }),
    ).toMatchObject({ phone: null, email: null, fiscalAddress: null, collectionContactName: null });
  });

  it('trims what it sends', () => {
    expect(
      toPayload({ ...emptyCustomerForm(), ruc: ' 20100000009 ', businessName: '  Acme SAC ' }),
    ).toMatchObject({
      ruc: '20100000009',
      businessName: 'Acme SAC',
    });
  });

  it('carries the price list as its IRI and the active flag', () => {
    expect(
      toPayload({ ...emptyCustomerForm(), priceList: '/api/price-lists/3', isActive: false }),
    ).toMatchObject({
      priceList: '/api/price-lists/3',
      isActive: false,
    });
  });

  it('defaults a new customer to active', () => {
    expect(emptyCustomerForm().isActive).toBe(true);
    expect(toPayload(emptyCustomerForm()).isActive).toBe(true);
  });
});

describe('toFormValues', () => {
  it('round-trips a record through the form without losing anything', () => {
    expect(toPayload(toFormValues(customer))).toEqual({
      ruc: '20100000009',
      businessName: 'Andina Retail SAC',
      fiscalAddress: 'Av. Ejército 100',
      email: 'ventas@andina.pe',
      phone: '+51987111001',
      collectionContactName: 'Rosa',
      priceList: '/api/price-lists/3',
      isActive: true,
    });
  });

  it('turns missing optional data into empty strings so controlled inputs stay controlled', () => {
    const values = toFormValues({
      ...customer,
      fiscalAddress: null,
      phone: null,
      email: null,
      priceList: null,
    });

    expect(values).toMatchObject({ fiscalAddress: '', phone: '', email: '', priceList: null });
  });
});

describe('priceListOf', () => {
  it('reads an embedded object', () => {
    expect(priceListOf(customer)).toEqual({ iri: '/api/price-lists/3', label: 'Mayorista' });
  });

  it('builds the IRI from the id when the object has none', () => {
    expect(priceListOf({ priceList: { id: 5, name: 'Minorista' } })).toEqual({
      iri: '/api/price-lists/5',
      label: 'Minorista',
    });
  });

  it('reads a bare IRI', () => {
    expect(priceListOf({ priceList: '/api/price-lists/9' })).toEqual({
      iri: '/api/price-lists/9',
      label: '9',
    });
  });

  it.each([null, {}])('is null when there is none (%j)', (priceList) => {
    expect(priceListOf({ priceList })).toBeNull();
  });
});

describe('CUSTOMER_FIELDS', () => {
  it('lists the fields in the order the form renders them, so the first error is the first on screen', () => {
    expect([...CUSTOMER_FIELDS].slice(0, 4)).toEqual([
      'ruc',
      'businessName',
      'fiscalAddress',
      'email',
    ]);
    expect(CUSTOMER_FIELDS.indexOf('email')).toBeLessThan(CUSTOMER_FIELDS.indexOf('phone'));
  });
});
