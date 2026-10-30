import { expect, test } from 'vitest';
import { supplierPaymentAmount } from './paymentAmount';

test('validates supplier payments against the exact outstanding decimal', () => {
  expect(supplierPaymentAmount('0.10', '0.30', 'PEN')).toBe('0.10');
  expect(supplierPaymentAmount('9999999999.99', '9999999999.99', 'PEN')).toBe('9999999999.99');
  expect(supplierPaymentAmount('1', '1.00', 'PEN')).toBe('1.00');
  for (const amount of ['', '0', '-1', '0.001', '1e2', '0.31']) {
    expect(supplierPaymentAmount(amount, '0.30', 'PEN')).toBeNull();
  }
});
