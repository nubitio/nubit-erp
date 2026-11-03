import { beforeEach, describe, expect, it } from 'vitest';
import {
  formatTicketNumber,
  nextTicketNumber,
  readHeldSales,
  writeHeldSales,
  type HeldSale,
} from './heldSales';

const sale = (id: string): HeldSale => ({
  id,
  number: 1,
  cart: [],
  discountPercent: 0,
  note: '',
  customerId: null,
  customerName: null,
  total: 10,
  heldAt: '2026-11-02T10:00:00.000Z',
});

describe('heldSales', () => {
  beforeEach(() => localStorage.clear());

  it('round-trips parked sales per branch and clears storage when empty', () => {
    writeHeldSales('a', [sale('x')]);
    expect(readHeldSales('a')).toHaveLength(1);
    expect(readHeldSales('b')).toEqual([]);
    writeHeldSales('a', []);
    expect(localStorage.getItem('pos_held_a')).toBeNull();
  });

  it('ignores corrupted storage', () => {
    localStorage.setItem('pos_held_a', '{oops');
    expect(readHeldSales('a')).toEqual([]);
  });

  it('numbers orders sequentially per branch', () => {
    expect(nextTicketNumber('a')).toBe(1);
    expect(nextTicketNumber('a')).toBe(2);
    expect(nextTicketNumber('b')).toBe(1);
    expect(formatTicketNumber(483)).toBe('#00483');
  });
});
