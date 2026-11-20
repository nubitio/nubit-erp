import { describe, expect, it } from 'vitest';
import { closeConfirmation } from './closeConfirmation';

const money = (n: number) => `S/ ${n.toFixed(2)}`;
const rows = [
  { paymentMethodId: 1, paymentMethod: 'Efectivo' },
  { paymentMethodId: 2, paymentMethod: 'Yape' },
];

describe('closeConfirmation', () => {
  it('says the count matches when every difference is zero', () => {
    const result = closeConfirmation(
      rows,
      [
        { paymentMethodId: 1, difference: 0 },
        { paymentMethodId: 2, difference: 0.001 },
      ],
      money,
    );
    expect(result.hasDifferences).toBe(false);
    expect(result.message).toContain('coincide');
  });

  it('names each method that is off, with sign, and the total', () => {
    const result = closeConfirmation(
      rows,
      [
        { paymentMethodId: 1, difference: -5 },
        { paymentMethodId: 2, difference: 2 },
      ],
      money,
    );
    expect(result.hasDifferences).toBe(true);
    expect(result.message).toContain('Efectivo −S/ 5.00');
    expect(result.message).toContain('Yape +S/ 2.00');
    expect(result.message).toContain('Total −S/ 3.00');
  });

  it('falls back to the method id when the row has no name', () => {
    const result = closeConfirmation([], [{ paymentMethodId: 7, difference: 1 }], money);
    expect(result.message).toContain('Método 7 +S/ 1.00');
  });
});
