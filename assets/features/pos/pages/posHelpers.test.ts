import { describe, expect, it } from 'vitest';
import {
  buildCustomersUrl,
  extractCollection,
  isCashPaymentMethod,
  nowUtcIso,
  toUtcIso,
  normalizeProduct,
  parseBooleanSetting,
  toNumber,
  toOptionalNumber,
} from './posHelpers';

describe('posHelpers', () => {
  it('normalizes product data from mixed API fields', () => {
    const product = normalizeProduct({
      id: 15,
      name: 'Cafe',
      precio_unitario: '12.50',
      type: 1,
      category: { id: 3, name: 'Bebidas' },
      barcode: 9988,
    });

    expect(product.id).toBe('15');
    expect(product.salePrice).toBe(12.5);
    expect(product.productType).toBe('STOCKABLE');
    expect(product.trackInventory).toBe(true);
    expect(product.category.name).toBe('Bebidas');
    expect(product.barcode).toBe('9988');
  });

  it('extracts collection from hydra and items payloads', () => {
    expect(extractCollection({ 'hydra:member': [1, 2] })).toEqual([1, 2]);
    expect(extractCollection({ items: [3] })).toEqual([3]);
    expect(extractCollection({ data: [4] })).toEqual([4]);
    expect(extractCollection(null)).toEqual([]);
  });

  it('parses number and optional number values safely', () => {
    expect(toNumber(5.5)).toBe(5.5);
    expect(toNumber('10.2')).toBe(10.2);
    expect(toNumber('invalid', 7)).toBe(7);
    expect(toOptionalNumber('12.8')).toBe(12.8);
    expect(toOptionalNumber('')).toBeUndefined();
  });

  it('returns raw collections when payload is already an array', () => {
    expect(extractCollection([1, 2, 3])).toEqual([1, 2, 3]);
  });

  it('detects cash payment methods by id and name', () => {
    expect(isCashPaymentMethod(null)).toBe(false);
    expect(isCashPaymentMethod({ id: 1, name: 'Tarjeta' })).toBe(true);
    expect(isCashPaymentMethod({ id: 9, name: 'Efectivo' })).toBe(true);
    expect(isCashPaymentMethod({ id: 9, name: 'Transferencia' })).toBe(false);
  });

  it('builds customer URL and trims search query', () => {
    const url = buildCustomersUrl('  acme  ', 2);

    expect(url).toContain('/customers?');
    expect(url).toContain('page=2');
    expect(url).toContain('itemsPerPage=50');
    expect(url).toContain('searchValue=acme');
  });

  it('parses boolean settings with fallback', () => {
    expect(parseBooleanSetting(true, false)).toBe(true);
    expect(parseBooleanSetting('true', false)).toBe(true);
    expect(parseBooleanSetting('0', true)).toBe(false);
    expect(parseBooleanSetting('unknown', true)).toBe(true);
  });

  it('nowUtcIso returns a UTC ISO-8601 string with Z suffix', () => {
    const value = nowUtcIso();
    expect(value).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/);
  });

  it('toUtcIso converts a Lima-zone Date to a UTC ISO string with Z suffix', () => {
    const value = toUtcIso(new Date('2024-01-01T10:00:00.000Z'));
    expect(value).toContain('T');
    expect(value).toMatch(/Z$/);
  });
});
