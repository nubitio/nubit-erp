import { describe, expect, it } from 'vitest';

import {
  formatPosReceiptLabel,
  formatStockCount,
  isInternalDocumentCode,
  linePieceCount,
  productNameInitials,
  publicSaleCode,
  withLinePieceCount,
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
    expect(formatPosReceiptLabel('B001-000048', '03')).toBe('Boleta B001-00000048');
    expect(formatPosReceiptLabel('F001-000012', '01')).toBe('Factura F001-00000012');
    expect(formatPosReceiptLabel('B001-8')).toBe('Boleta B001-00000008');
  });
});

describe('formatStockCount', () => {
  it('uses Spanish units instead of English left', () => {
    expect(formatStockCount(3)).toBe('3 und');
    expect(formatStockCount(1.5)).toBe('1.5 und');
  });
});

describe('line piece count', () => {
  it('reads the leading integer of a custom description', () => {
    expect(linePieceCount('2 Pechuga de pollo')).toBe(2);
    expect(linePieceCount('Pechuga de pollo')).toBe(0);
    expect(linePieceCount('')).toBe(0);
    expect(linePieceCount('1kg pechuga')).toBe(0);
  });

  it('adds, updates and removes the prefix', () => {
    expect(withLinePieceCount('', 'Pechuga de pollo', 1)).toBe('1 Pechuga de pollo');
    expect(withLinePieceCount('1 Pechuga de pollo', 'Pechuga de pollo', 2)).toBe(
      '2 Pechuga de pollo',
    );
    expect(withLinePieceCount('1 Pechuga de pollo', 'Pechuga de pollo', 0)).toBe('');
  });

  it('keeps manual text after the prefix', () => {
    expect(withLinePieceCount('3 Pechuga sin piel', 'Pechuga de pollo', 4)).toBe(
      '4 Pechuga sin piel',
    );
    expect(withLinePieceCount('Pechuga sin piel', 'Pechuga de pollo', 1)).toBe(
      '1 Pechuga sin piel',
    );
    expect(withLinePieceCount('2 Pechuga sin piel', 'Pechuga de pollo', 0)).toBe(
      'Pechuga sin piel',
    );
  });

  it('reads a custom description that starts with a number as a piece count (the encoding is text, so the counter only reads it on weighted lines)', () => {
    expect(linePieceCount('7 Up helada')).toBe(7);
  });

  it('never exceeds the 255 characters the line description column holds', () => {
    expect(withLinePieceCount('', 'x'.repeat(300), 2).length).toBe(255);
  });

  it('treats non-finite or fractional counts safely', () => {
    expect(withLinePieceCount('', 'Pechuga', 2.9)).toBe('2 Pechuga');
    expect(withLinePieceCount('2 Pechuga', 'Pechuga', -3)).toBe('');
  });
});
