import { describe, expect, it } from 'vitest';
import type { ReceivableInvoice } from '../../sales/services/receivableService';
import { openBalance } from './openBalance';

const invoice = (balanceDue: string, currency = 'PEN'): ReceivableInvoice => ({
  id: balanceDue,
  invoiceFullNumber: null,
  issuedDate: null,
  dueDate: null,
  currency,
  grandTotal: balanceDue,
  paidTotal: '0',
  balanceDue,
  status: 'PENDING',
  payments: [],
});

describe('openBalance', () => {
  it('is zero in soles when there is nothing open', () => {
    expect(openBalance([])).toEqual({ total: '0.00', currency: 'PEN' });
  });

  it('sums in cents, so 0.1 + 0.2 is 0.30', () => {
    expect(openBalance([invoice('0.10'), invoice('0.20')]).total).toBe('0.30');
  });

  it('takes the currency of the first invoice', () => {
    expect(openBalance([invoice('10.00', 'USD'), invoice('5.50', 'USD')])).toEqual({
      total: '15.50',
      currency: 'USD',
    });
  });

  it('ignores a balance that is not a number', () => {
    expect(openBalance([invoice('abc'), invoice('7.25')]).total).toBe('7.25');
  });
});
