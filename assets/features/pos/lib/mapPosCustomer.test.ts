import { describe, expect, it } from 'vitest';
import { formatPosCustomerLabel, mapPosCustomer } from './mapPosCustomer';

describe('mapPosCustomer', () => {
  it('maps customer entity with price list', () => {
    const mapped = mapPosCustomer({
      id: 12,
      businessName: 'ACME SAC',
      ruc: '20123456789',
      price_list: { id: 3 },
    });

    expect(mapped).toEqual({
      id: 12,
      businessName: 'ACME SAC',
      doc: '20123456789',
      priceListId: 3,
    });
  });

  it('maps customer priceList IRI from API Platform', () => {
    const mapped = mapPosCustomer({
      id: 506,
      businessName: 'GUERREROS MONTOYA JOHAN',
      ruc: '46221041',
      priceList: '/api/price-lists/3',
    });

    expect(mapped?.priceListId).toBe(3);
  });

  it('formats customer label with document', () => {
    expect(
      formatPosCustomerLabel({
        businessName: 'Juan Perez',
        ruc: '12345678',
      }),
    ).toBe('Juan Perez - 12345678');
  });
});