import { describe, expect, it } from 'vitest';
import {
  formatDate,
  formatInvoiceNumber,
  formatMoney,
  formatMonthPeriod,
  payableStatus,
  receivableStatus,
} from './displayFormat';

describe('formatMoney', () => {
  it('formats PEN with thousands separator', () => {
    expect(formatMoney(12838.4).replace(/\s/g, ' ')).toBe('S/ 12,838.40');
  });

  it('treats empty as a dash', () => {
    expect(formatMoney(null)).toBe('—');
  });
});

describe('formatDate', () => {
  it('keeps the calendar day of an ISO datetime instead of shifting to 7:00 p. m.', () => {
    expect(formatDate('2026-07-31T00:00:00+00:00')).toBe('31/07/2026');
    expect(formatDate('2026-07-31T07:00:00.000Z')).toBe('31/07/2026');
  });

  it('formats a date-only string', () => {
    expect(formatDate('2026-08-18')).toBe('18/08/2026');
  });
});

describe('formatMonthPeriod', () => {
  it('renders payroll periods as month + year', () => {
    expect(formatMonthPeriod('2026-07-31T00:00:00+00:00')).toBe('Julio 2026');
    expect(formatMonthPeriod('202608')).toBe('Agosto 2026');
  });
});

describe('formatInvoiceNumber', () => {
  it('pads the correlative to 8 digits', () => {
    expect(formatInvoiceNumber('F001-1045')).toBe('F001-00001045');
    expect(formatInvoiceNumber('B001-00002004')).toBe('B001-00002004');
  });
});

describe('receivableStatus', () => {
  it('never reports a sale COMPLETED flag — only the open balance', () => {
    expect(
      receivableStatus({
        balanceDue: '8.90',
        paidTotal: '0',
        dueDate: '2099-01-01',
      }),
    ).toBe('pending');
  });

  it('marks overdue when the due date is in the past', () => {
    expect(
      receivableStatus({
        balanceDue: 10,
        paidTotal: 0,
        dueDate: '2020-01-01',
      }),
    ).toBe('overdue');
  });
});

describe('payableStatus', () => {
  it('prefers remaining balance over a stale Pagada flag', () => {
    expect(payableStatus({ balanceDue: '12838.40', paidTotal: '0' })).toBe('pending');
    expect(payableStatus({ balanceDue: '0', paidTotal: '100' })).toBe('paid');
  });
});
