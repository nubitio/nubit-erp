import { describe, expect, it } from 'vitest';
import { filterPriceEditorRows } from './filterPriceEditorRows';
import type { PriceEditorRow } from '../components/PriceEditorGrid';

const rows: PriceEditorRow[] = [
  {
    id: 1,
    name: 'Router Mi 4C',
    sku: 'R4CM',
    code: 'NET-001',
    price: '95.00',
    priceSource: 'base',
  },
  { id: 2, name: 'Audifonos JBL', sku: 'JBL-500', code: null, price: '99.00', priceSource: 'base' },
  {
    id: 3,
    name: 'Cable HDMI',
    sku: null,
    code: 'CAB-HDMI',
    price: '12.00',
    priceSource: 'override',
  },
];

describe('filterPriceEditorRows', () => {
  it('returns all rows when query is empty', () => {
    expect(filterPriceEditorRows(rows, '')).toHaveLength(3);
  });

  it('filters by product name', () => {
    expect(filterPriceEditorRows(rows, 'jbl')).toEqual([rows[1]]);
  });

  it('filters by sku', () => {
    expect(filterPriceEditorRows(rows, 'r4cm')).toEqual([rows[0]]);
  });

  it('filters by internal code', () => {
    expect(filterPriceEditorRows(rows, 'cab-hdmi')).toEqual([rows[2]]);
  });
});
