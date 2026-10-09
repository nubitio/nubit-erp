import { describe, expect, it } from 'vitest';
import {
  addPaymentLine,
  buildPaymentPayload,
  buildSettlementPayload,
  creditPaymentAmount,
  createSplitPaymentLines,
  enableSplitPaymentLines,
  hasCreditPayment,
  paymentsMatchTotal,
  paymentsWithinTotal,
  rebalancePaymentLines,
  remainingPaymentAmount,
  summarizePaymentLines,
  totalCashChangeAmount,
  validatePaymentLines,
} from './salePayments';
import type { PaymentMethodOption } from '@shared/pos/types';

const methods: PaymentMethodOption[] = [
  { id: 1, name: 'Efectivo', isActive: true },
  { id: 6, name: 'Yape', isActive: true },
  { id: 7, name: 'Plin', isActive: true },
  { id: 8, name: 'Tarjeta', isActive: true },
];

describe('salePayments', () => {
  it('derives the financed balance from the Crédito allocation', () => {
    const creditMethods: PaymentMethodOption[] = [
      ...methods,
      { id: 2, name: 'Credito', isActive: true, settlementBehavior: 'RECEIVABLE' },
    ];
    const lines = [
      { methodId: 1, amount: '30.00' },
      { methodId: 2, amount: '70.00' },
    ];

    expect(hasCreditPayment(lines, creditMethods)).toBe(true);
    expect(creditPaymentAmount(lines, creditMethods)).toBe(70);
    expect(validatePaymentLines(lines, creditMethods, 100)).toEqual({ valid: true });
    expect(buildSettlementPayload(lines, creditMethods, 100)).toEqual([
      {
        type: 'PAYMENT',
        payment_method_id: 1,
        amount: '30.00',
        tendered_amount: '30.00',
        change_amount: '0.00',
      },
      {
        type: 'RECEIVABLE',
        amount: '70.00',
      },
    ]);
    expect(buildPaymentPayload(lines, creditMethods, 100)).toHaveLength(1);
  });

  it('builds mixed cash and yape payload', () => {
    const lines = createSplitPaymentLines(1, 6, 30, 100);
    const payload = buildPaymentPayload(lines, methods, 100);

    expect(payload).toHaveLength(2);
    expect(payload[0]).toMatchObject({
      payment_method_id: 1,
      amount: '30.00',
      tendered_amount: '30.00',
      change_amount: '0.00',
    });
    expect(payload[1]).toMatchObject({
      payment_method_id: 6,
      amount: '70.00',
    });
  });

  it('supports three-way split payments', () => {
    const lines = enableSplitPaymentLines(1, methods, 90);
    const withPlin = addPaymentLine(lines, methods, 90);
    const rebalanced = rebalancePaymentLines(
      [{ methodId: 1, amount: '30.00' }, { methodId: 6, amount: '20.00' }, withPlin[2]],
      90,
    );

    expect(rebalanced).toHaveLength(3);
    expect(paymentsMatchTotal(rebalanced, 90)).toBe(true);
    expect(validatePaymentLines(rebalanced, methods, 90)).toEqual({ valid: true });
  });

  it('validates that split payments match total', () => {
    const lines = createSplitPaymentLines(1, 6, 30, 100);

    expect(paymentsMatchTotal(lines, 100)).toBe(true);
    expect(validatePaymentLines(lines, methods, 100)).toEqual({ valid: true });
  });

  it('rejects payments that do not match total', () => {
    const lines = [
      { methodId: 1, amount: '20.00' },
      { methodId: 6, amount: '20.00' },
    ];
    const result = validatePaymentLines(lines, methods, 100);

    expect(result.valid).toBe(false);
    if (!result.valid) {
      expect(result.message).toContain('no coincide');
    }
  });

  it('summarizes split payment lines', () => {
    const summary = summarizePaymentLines(
      [
        { methodId: 1, amount: '30.00' },
        { methodId: 6, amount: '70.00' },
      ],
      methods,
    );

    expect(summary.isSplit).toBe(true);
    expect(summary.label).toContain('Efectivo');
    expect(summary.label).toContain('Yape');
  });

  it('rejects duplicate payment methods', () => {
    const lines = [
      { methodId: 6, amount: '40.00' },
      { methodId: 6, amount: '60.00' },
    ];
    const result = validatePaymentLines(lines, methods, 100);

    expect(result.valid).toBe(false);
    if (!result.valid) {
      expect(result.message).toContain('No repitas');
    }
  });

  it('allowPartial: accepts a sum below total ("venta a crédito")', () => {
    const lines = [{ methodId: 1, amount: '30.00' }];

    expect(paymentsWithinTotal(lines, 100)).toBe(true);
    expect(validatePaymentLines(lines, methods, 100, true)).toEqual({ valid: true });
  });

  it('allowPartial: still rejects a sum above total', () => {
    const lines = [{ methodId: 1, amount: '150.00' }];

    expect(paymentsWithinTotal(lines, 100)).toBe(false);
    const result = validatePaymentLines(lines, methods, 100, true);
    expect(result.valid).toBe(false);
    if (!result.valid) {
      expect(result.message).toContain('no puede superar');
    }
  });

  it('without allowPartial, a sum below total is still rejected (default POS behavior unchanged)', () => {
    const lines = [{ methodId: 1, amount: '30.00' }];
    const result = validatePaymentLines(lines, methods, 100);

    expect(result.valid).toBe(false);
    if (!result.valid) {
      expect(result.message).toContain('no coincide con el total');
    }
  });

  it('adds decimal amounts using exact cents instead of floating point', () => {
    const lines = [
      { methodId: 1, amount: '0.10' },
      { methodId: 6, amount: '0.20' },
    ];

    expect(paymentsMatchTotal(lines, 0.3)).toBe(true);
    expect(remainingPaymentAmount(lines, 0.3)).toBe(0);
  });

  it('does not hide a one-cent mismatch behind a tolerance', () => {
    const lines = [{ methodId: 1, amount: '9.99' }];

    expect(paymentsMatchTotal(lines, 10)).toBe(false);
    expect(validatePaymentLines(lines, methods, 10).valid).toBe(false);
  });

  it('calculates cash change exactly in minor units', () => {
    const lines = [{ methodId: 1, amount: '19.90', tenderedAmount: '20.00' }];

    expect(buildPaymentPayload(lines, methods, 19.9)[0]).toMatchObject({
      amount: '19.90',
      tendered_amount: '20.00',
      change_amount: '0.10',
    });
    expect(totalCashChangeAmount(lines, methods, 19.9)).toBe(0.1);
  });
});
