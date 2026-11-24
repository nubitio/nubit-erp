import { describe, expect, it } from 'vitest';
import type { PaymentMethodOption } from '@shared/pos/types';
import {
  addDays,
  applyPriceList,
  pickPrice,
  buildSalePayload,
  draftTotals,
  newLine,
  validateDraft,
  type InvoiceDraft,
} from './invoiceDraft';

const methods: PaymentMethodOption[] = [
  { id: 1, name: 'Efectivo', isActive: true },
  { id: 2, name: 'Crédito', isActive: true, settlementBehavior: 'RECEIVABLE' },
];

const draft = (patch: Partial<InvoiceDraft> = {}): InvoiceDraft => ({
  customerId: '7',
  warehouseId: '3',
  voucher: 'FACTURA',
  currency: 'PEN',
  exchangeRate: '3.800000',
  issuedDate: '2026-11-23',
  condition: 'CREDIT',
  paymentMethodId: null,
  termDays: '30',
  note: '',
  lines: [
    {
      ...newLine(),
      productId: '9',
      description: 'Hosting',
      quantity: '2',
      unitPrice: '59',
      basePrice: '59',
    },
  ],
  ...patch,
});

describe('invoiceDraft', () => {
  it('splits the IGV out of tax-inclusive prices', () => {
    const totals = draftTotals(draft().lines, 0.18);
    expect(totals.total).toBe(118);
    expect(totals.includedTax).toBe(18);
  });

  it('adds days across month boundaries', () => {
    expect(addDays('2026-11-23', 30)).toBe('2026-12-23');
    expect(addDays('2026-11-23', -3)).toBe('2026-11-20');
  });

  it('blocks emission until customer, lines and payment data are complete', () => {
    expect(validateDraft(draft({ customerId: null }), 118, methods)).toMatch(/cliente/i);
    expect(validateDraft(draft({ lines: [newLine()] }), 0, methods)).toMatch(/línea/i);
    expect(validateDraft(draft({ condition: 'CASH' }), 118, methods)).toMatch(/medio de pago/i);
    expect(validateDraft(draft({ termDays: '0' }), 118, methods)).toMatch(/crédito/i);
    expect(validateDraft(draft({ currency: 'USD', exchangeRate: '' }), 118, methods)).toMatch(
      /cambio/i,
    );
    expect(validateDraft(draft(), 118, methods)).toBeNull();
  });

  it('posts a credit invoice as a receivable with its own due date', () => {
    const payload = buildSalePayload(
      '/api/',
      draft(),
      118,
      methods,
      2,
      true,
      '2026-11-23T10:00:00Z',
    );
    expect(payload.settlements).toEqual([{ type: 'RECEIVABLE', amount: '118.00' }]);
    expect(payload.dueDate).toBe('2026-12-23');
    expect(payload.emitInvoice).toBe(true);
    expect(payload.requestedVoucherType).toBe('FACTURA');
    expect(payload.writeItems).toHaveLength(1);
    expect(payload.writeItems[0]).toMatchObject({ qty: 2, unitPrice: '59', netAmount: '118.00' });
  });

  it('posts a cash invoice with the chosen payment method and no due date', () => {
    const payload = buildSalePayload(
      '/api/',
      draft({ condition: 'CASH', paymentMethodId: '1' }),
      118,
      methods,
      2,
      false,
      '2026-11-23T10:00:00Z',
    );
    expect(payload.settlements[0]).toMatchObject({ type: 'PAYMENT', payment_method_id: 1 });
    expect(payload).not.toHaveProperty('dueDate');
    expect(payload.emitInvoice).toBe(false);
  });

  it('reprices picked lines with the price list and restores catalog prices without one', () => {
    const lines = draft().lines;
    const prices = new Map([['9', '29.50']]);

    expect(pickPrice('9', '59', prices)).toBe('29.50');
    expect(pickPrice('10', '80', prices)).toBe('80');
    expect(applyPriceList(lines, prices)[0].unitPrice).toBe('29.50');
    expect(applyPriceList(applyPriceList(lines, prices), new Map())[0].unitPrice).toBe('59');
    expect(applyPriceList([newLine()], prices)[0].unitPrice).toBe('');
  });
});
