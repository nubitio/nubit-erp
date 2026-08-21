import { describe, expect, it } from 'vitest';

import {
  formatPosReceiptLabel,
  formatStockCount,
  isInternalDocumentCode,
  productNameInitials,
  publicSaleCode,
} from './posPresentation';

describe('productNameInitials', () => {
  it('ignores numbers and hash suffixes so demo names do not become L#', () => {
    expect(productNameInitials('Laptop #1')).toBe('LA');
    expect(productNameInitials('Mouse Inalámbrico #2')).toBe('MI');
    expect(productNameInitials('Gaseosa 500ml #11')).toBe('GA');
  });

  it('uses the first letters of the first two real words', () => {
    expect(productNameInitials('Café Premium')).toBe('CP');
    expect(productNameInitials('HP OMNIBOOK FLIP')).toBe('HO');
  });
});

describe('formatPosReceiptLabel', () => {
  it('hides UUIDs so cashiers never see an internal id as a comprobante', () => {
    expect(formatPosReceiptLabel('01a02201-059a-7cb3-98a4-27887ea52411')).toBeNull();
    expect(isInternalDocumentCode('01a02201-059a-7cb3-98a4-27887ea52411')).toBe(true);
    expect(publicSaleCode('01a02201-059a-7cb3-98a4-27887ea52411')).toBeNull();
  });

  it('labels SUNAT series as boleta or factura', () => {
    expect(formatPosReceiptLabel('B001-000048', '03')).toBe('Boleta B001-000048');
    expect(formatPosReceiptLabel('F001-000012', '01')).toBe('Factura F001-000012');
    expect(formatPosReceiptLabel('B001-8')).toBe('Boleta B001-8');
  });
});

describe('formatStockCount', () => {
  it('uses Spanish units instead of English left', () => {
    expect(formatStockCount(3)).toBe('3 und');
    expect(formatStockCount(1.5)).toBe('1.5 und');
  });
});
