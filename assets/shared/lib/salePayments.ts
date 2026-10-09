import { isCashPaymentMethod, isCreditPaymentMethod } from '@shared/pos/helpers';
import type { PaymentMethodOption } from '@shared/pos/types';
import {
  formatMinorUnits,
  parseMoneyInput as parseCoreMoneyInput,
  type MoneyValue,
  toMinorUnits,
} from '@nubitio/core';

export interface SalePaymentLineDraft {
  methodId: number | null;
  amount: string;
  tenderedAmount?: string;
}

export interface SalePaymentPayloadLine {
  payment_method_id: number;
  amount: string;
  tendered_amount?: string;
  change_amount?: string;
}

export type SaleSettlementPayloadLine =
  | ({ type: 'PAYMENT' } & SalePaymentPayloadLine)
  | {
      type: 'RECEIVABLE';
      amount: string;
    };

const PAYMENT_CURRENCY = 'PEN';
const PAYMENT_SCALE = 2;

const zeroMoney = (): MoneyValue => ({
  amount: '0.00',
  currency: PAYMENT_CURRENCY,
  scale: PAYMENT_SCALE,
});

/**
 * Normalizes a value once at the legacy number/string boundary. All arithmetic
 * below this point uses exact minor units; no IEEE-754 values are accumulated.
 */
const moneyValue = (value: string | number | null | undefined): MoneyValue => {
  if (value === null || value === undefined || value === '') return zeroMoney();
  if (typeof value === 'number' && !Number.isFinite(value)) return zeroMoney();

  const raw =
    typeof value === 'number' ? value.toFixed(PAYMENT_SCALE) : value.trim().replace(',', '.');
  const parsed = parseCoreMoneyInput(raw, PAYMENT_CURRENCY, PAYMENT_SCALE, 'en-US');

  if (parsed.ok) return parsed.value;

  // Preserve the previous public helper's rounding behavior for legacy callers
  // that still hand us numbers/strings with more than two decimal places.
  const fallback = Number.parseFloat(raw);
  if (!Number.isFinite(fallback)) return zeroMoney();
  const rounded = parseCoreMoneyInput(
    fallback.toFixed(PAYMENT_SCALE),
    PAYMENT_CURRENCY,
    PAYMENT_SCALE,
    'en-US',
  );

  return rounded.ok ? rounded.value : zeroMoney();
};

const minorUnits = (value: string | number | null | undefined): bigint =>
  toMinorUnits(moneyValue(value));

const minorToString = (minor: bigint): string => formatMinorUnits(minor, PAYMENT_SCALE);

const minorToNumber = (minor: bigint): number => Number(minorToString(minor));

export const roundMoney = (value: number): number => minorToNumber(minorUnits(value));

export const parseMoneyInput = (value: string | number | null | undefined): number =>
  minorToNumber(minorUnits(value));

export const sumPaymentAmounts = (lines: SalePaymentLineDraft[]): number =>
  minorToNumber(lines.reduce((sum, line) => sum + minorUnits(line.amount), 0n));

export const creditPaymentAmount = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
): number => {
  const methodById = new Map(paymentMethods.map((method) => [method.id, method]));

  return minorToNumber(
    lines.reduce(
      (sum, line) =>
        isCreditPaymentMethod(methodById.get(line.methodId ?? -1))
          ? sum + minorUnits(line.amount)
          : sum,
      0n,
    ),
  );
};

export const hasCreditPayment = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
): boolean => creditPaymentAmount(lines, paymentMethods) > 0;

export const paymentsMatchTotal = (lines: SalePaymentLineDraft[], total: number): boolean =>
  lines.reduce((sum, line) => sum + minorUnits(line.amount), 0n) === minorUnits(total);

/** Like paymentsMatchTotal, but also accepts a sum below the total — the "venta a crédito" case. */
export const paymentsWithinTotal = (lines: SalePaymentLineDraft[], total: number): boolean =>
  lines.reduce((sum, line) => sum + minorUnits(line.amount), 0n) <= minorUnits(total);

export const remainingPaymentAmount = (lines: SalePaymentLineDraft[], total: number): number => {
  const remaining =
    minorUnits(total) - lines.reduce((sum, line) => sum + minorUnits(line.amount), 0n);
  return remaining > 0n ? minorToNumber(remaining) : 0;
};

export const summarizePaymentLines = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
): { label: string; isSplit: boolean } => {
  const methodById = new Map(paymentMethods.map((method) => [method.id, method.name]));

  if (lines.length === 0) {
    return { label: 'Configurar pago', isSplit: false };
  }

  if (lines.length === 1) {
    const line = lines[0];
    const methodName = line.methodId != null ? methodById.get(line.methodId) : null;
    return {
      label: methodName ?? 'Seleccionar método',
      isSplit: false,
    };
  }

  const parts = lines
    .filter((line) => line.methodId != null && minorUnits(line.amount) > 0n)
    .map((line) => {
      const name = methodById.get(line.methodId as number) ?? 'Pago';
      return `${name} S/ ${minorToString(minorUnits(line.amount))}`;
    });

  return {
    label: parts.length > 0 ? parts.join(' + ') : `${lines.length} pagos`,
    isSplit: true,
  };
};

export const createPaymentLine = (
  methodId: number | null,
  amount: number,
): SalePaymentLineDraft => ({
  methodId,
  amount: minorToString(minorUnits(amount)),
});

export const createSinglePaymentLine = (
  methodId: number | null,
  total: number,
): SalePaymentLineDraft => createPaymentLine(methodId, total);

export const createSplitPaymentLines = (
  primaryMethodId: number | null,
  secondaryMethodId: number | null,
  primaryAmount: number,
  total: number,
): SalePaymentLineDraft[] => {
  const totalMinor = minorUnits(total);
  const primaryMinor = minorUnits(primaryAmount);
  const normalizedPrimary =
    primaryMinor < 0n ? 0n : primaryMinor > totalMinor ? totalMinor : primaryMinor;
  const secondaryAmount = totalMinor - normalizedPrimary;

  return [
    { methodId: primaryMethodId, amount: minorToString(normalizedPrimary) },
    { methodId: secondaryMethodId, amount: minorToString(secondaryAmount) },
  ];
};

export const enableSplitPaymentLines = (
  primaryMethodId: number | null,
  paymentMethods: PaymentMethodOption[],
  total: number,
): SalePaymentLineDraft[] => {
  const secondaryMethodId =
    paymentMethods.find((method) => method.id !== primaryMethodId)?.id ?? null;
  const primaryAmount = roundMoney(total / 2);

  return createSplitPaymentLines(primaryMethodId, secondaryMethodId, primaryAmount, total);
};

export const rebalancePaymentLines = (
  lines: SalePaymentLineDraft[],
  total: number,
): SalePaymentLineDraft[] => {
  if (lines.length === 0) {
    return lines;
  }

  if (lines.length === 1) {
    return [createSinglePaymentLine(lines[0].methodId, total)];
  }

  const head = lines.slice(0, -1);
  const tail = lines[lines.length - 1];
  const headSum = head.reduce((sum, line) => sum + minorUnits(line.amount), 0n);
  const remainder = minorUnits(total) - headSum;
  const tailAmount = remainder > 0n ? remainder : 0n;

  return [
    ...head,
    {
      ...tail,
      amount: minorToString(tailAmount),
    },
  ];
};

export const updatePaymentLineAmount = (
  lines: SalePaymentLineDraft[],
  index: number,
  nextAmount: string,
  total: number,
): SalePaymentLineDraft[] => {
  const next = lines.map((line, lineIndex) =>
    lineIndex === index ? { ...line, amount: nextAmount } : line,
  );

  if (index < lines.length - 1) {
    return rebalancePaymentLines(next, total);
  }

  return next;
};

export const updatePaymentLineMethod = (
  lines: SalePaymentLineDraft[],
  index: number,
  methodId: number,
): SalePaymentLineDraft[] =>
  lines.map((line, lineIndex) => (lineIndex === index ? { ...line, methodId } : line));

export const addPaymentLine = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
): SalePaymentLineDraft[] => {
  const usedMethodIds = new Set(
    lines.map((line) => line.methodId).filter((id): id is number => id != null),
  );
  const nextMethodId = paymentMethods.find((method) => !usedMethodIds.has(method.id))?.id ?? null;
  const remaining = remainingPaymentAmount(lines, total);

  return [...lines, createPaymentLine(nextMethodId, remaining > 0 ? remaining : 0)];
};

export const removePaymentLine = (
  lines: SalePaymentLineDraft[],
  index: number,
  total: number,
): SalePaymentLineDraft[] => {
  if (lines.length <= 1) {
    return lines;
  }

  const removedAmount = minorUnits(lines[index]?.amount);
  const next = lines.filter((_, lineIndex) => lineIndex !== index);

  if (next.length === 1) {
    return [createSinglePaymentLine(next[0].methodId, total)];
  }

  const lastIndex = next.length - 1;
  const lastLine = next[lastIndex];
  next[lastIndex] = {
    ...lastLine,
    amount: minorToString(minorUnits(lastLine.amount) + removedAmount),
  };

  return rebalancePaymentLines(next, total);
};

export const buildSettlementPayload = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  _total: number,
): SaleSettlementPayloadLine[] => {
  const methodById = new Map(paymentMethods.map((method) => [method.id, method]));

  return lines
    .filter((line) => line.methodId != null && minorUnits(line.amount) > 0n)
    .map((line) => {
      const method = methodById.get(line.methodId as number);
      const amount = minorUnits(line.amount);
      if (isCreditPaymentMethod(method)) {
        return {
          type: 'RECEIVABLE' as const,
          amount: minorToString(amount),
        };
      }

      const isCash = isCashPaymentMethod(method);
      const tendered = isCash ? minorUnits(line.tenderedAmount ?? line.amount) : amount;
      const change = isCash && tendered > amount ? tendered - amount : 0n;

      return {
        type: 'PAYMENT' as const,
        payment_method_id: line.methodId as number,
        amount: minorToString(amount),
        ...(isCash
          ? {
              tendered_amount: minorToString(tendered),
              change_amount: minorToString(change),
            }
          : {}),
      };
    });
};

export const buildPaymentPayload = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
): SalePaymentPayloadLine[] =>
  buildSettlementPayload(lines, paymentMethods, total)
    .filter(
      (settlement): settlement is Extract<SaleSettlementPayloadLine, { type: 'PAYMENT' }> =>
        settlement.type === 'PAYMENT',
    )
    .map(({ type: _type, ...payment }) => payment);

export const totalCashChangeAmount = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
): number => {
  const payload = buildPaymentPayload(lines, paymentMethods, total);
  return minorToNumber(
    payload.reduce((sum, payment) => sum + minorUnits(payment.change_amount), 0n),
  );
};

export type PaymentValidationResult = { valid: true } | { valid: false; message: string };

export const validatePaymentLines = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
  allowPartial = false,
): PaymentValidationResult => {
  if (minorUnits(total) <= 0n) {
    return { valid: false, message: 'El total debe ser mayor a cero.' };
  }

  if (lines.length === 0) {
    return { valid: false, message: 'Agrega al menos un método de pago.' };
  }

  const methodById = new Map(paymentMethods.map((method) => [method.id, method]));

  for (const [index, line] of lines.entries()) {
    if (line.methodId == null) {
      return { valid: false, message: `Selecciona el método de pago ${index + 1}.` };
    }

    const amount = minorUnits(line.amount);
    if (amount <= 0n) {
      return { valid: false, message: `El monto del pago ${index + 1} debe ser mayor a cero.` };
    }

    const method = methodById.get(line.methodId);
    if (method && isCashPaymentMethod(method)) {
      const tendered = minorUnits(line.tenderedAmount ?? line.amount);
      if (tendered < amount) {
        return {
          valid: false,
          message: `El efectivo recibido en el pago ${index + 1} es menor al monto asignado.`,
        };
      }
    }
  }

  if (allowPartial) {
    if (!paymentsWithinTotal(lines, total)) {
      const paid = sumPaymentAmounts(lines);
      return {
        valid: false,
        message: `La suma de pagos (${paid.toFixed(2)}) no puede superar el total (${roundMoney(total).toFixed(2)}).`,
      };
    }
  } else if (!paymentsMatchTotal(lines, total)) {
    const paid = sumPaymentAmounts(lines);
    return {
      valid: false,
      message: `La suma de pagos (${paid.toFixed(2)}) no coincide con el total (${roundMoney(total).toFixed(2)}).`,
    };
  }

  const usedMethods = lines.map((line) => line.methodId).filter((id): id is number => id != null);
  if (new Set(usedMethods).size !== usedMethods.length) {
    return { valid: false, message: 'No repitas el mismo método de pago en pagos divididos.' };
  }

  return { valid: true };
};
