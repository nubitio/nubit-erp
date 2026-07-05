import { describe, expect, it } from 'vitest';
import {
  addPaymentLine,
  buildPaymentPayload,
  createSplitPaymentLines,
  enableSplitPaymentLines,
  paymentsMatchTotal,
  rebalancePaymentLines,
  summarizePaymentLines,
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
      [
        { methodId: 1, amount: '30.00' },
        { methodId: 6, amount: '20.00' },
        withPlin[2],
      ],
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
});