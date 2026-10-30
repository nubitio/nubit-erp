import { expect, it } from 'vitest';
import { primaryPaymentMethods, paymentMethodIcon } from './paymentMethodPresentation';
it('offers one primary method per supported family without changing API IDs', () => {
  const methods = [
    { id: 9, name: 'Crédito', isActive: true, settlementBehavior: 'RECEIVABLE' as const },
    { id: 1, name: 'Efectivo', isActive: true },
    { id: 8, name: 'Plin', isActive: true },
    { id: 7, name: 'Yape', isActive: true },
    { id: 4, name: 'Tarjeta débito', isActive: true },
    { id: 6, name: 'Transferencia', isActive: true },
  ];
  expect(primaryPaymentMethods(methods, 4).map((item) => item.id)).toEqual([1, 8, 4, 6]);
  expect(paymentMethodIcon(methods[0])).toBe('ph-hand-coins');
  expect(primaryPaymentMethods(methods.slice(0, 2), 4)).toHaveLength(2);
});
