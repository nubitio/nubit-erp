import { isCashPaymentMethod } from '@shared/pos/helpers';
import type { PaymentMethodOption } from '@shared/pos/types';
const name = (method: PaymentMethodOption) =>
  method.name
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
export function paymentMethodIcon(method: PaymentMethodOption): string {
  if (isCashPaymentMethod(method)) return 'ph-money';
  if (/yape|plin|qr/.test(name(method))) return 'ph-qr-code';
  if (/tarjeta/.test(name(method))) return 'ph-credit-card';
  if (/transfer|deposit/.test(name(method))) return 'ph-arrows-left-right';
  if (method.settlementBehavior === 'RECEIVABLE' || /credito/.test(name(method)))
    return 'ph-hand-coins';
  return 'ph-wallet';
}
/** Compact presentation only: IDs and settlement rules always remain those of the API. */
export function primaryPaymentMethods(methods: PaymentMethodOption[], limit: number) {
  const selected: PaymentMethodOption[] = [];
  for (const icon of ['ph-money', 'ph-qr-code', 'ph-credit-card', 'ph-arrows-left-right']) {
    const match = methods.find((method) => paymentMethodIcon(method) === icon);
    if (match && selected.length < limit) selected.push(match);
  }
  for (const method of methods)
    if (selected.length < limit && !selected.some((item) => item.id === method.id))
      selected.push(method);
  return selected;
}

export function compactPaymentMethodName(method: PaymentMethodOption) {
  const normalized = name(method);
  if (normalized === 'tarjeta debito' || normalized === 'tarjeta de debito') return 'Débito';
  if (normalized === 'tarjeta credito' || normalized === 'tarjeta de credito') return 'T. crédito';
  if (normalized === 'transferencia') return 'Transfer.';
  return method.name;
}
