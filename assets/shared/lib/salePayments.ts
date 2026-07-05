import { isCashPaymentMethod } from '@shared/pos/helpers';
import type { PaymentMethodOption } from '@shared/pos/types';

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

const MONEY_TOLERANCE = 0.01;

export const roundMoney = (value: number): number => Number(value.toFixed(2));

export const parseMoneyInput = (value: string | number | null | undefined): number => {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return roundMoney(value);
  }

  const parsed = Number.parseFloat(String(value ?? '').replace(',', '.'));
  return Number.isFinite(parsed) ? roundMoney(parsed) : 0;
};

export const sumPaymentAmounts = (lines: SalePaymentLineDraft[]): number =>
  roundMoney(lines.reduce((sum, line) => sum + parseMoneyInput(line.amount), 0));

export const paymentsMatchTotal = (lines: SalePaymentLineDraft[], total: number): boolean => {
  const diff = Math.abs(sumPaymentAmounts(lines) - roundMoney(total));
  return diff <= MONEY_TOLERANCE;
};

export const remainingPaymentAmount = (lines: SalePaymentLineDraft[], total: number): number => {
  const remaining = roundMoney(total) - sumPaymentAmounts(lines);
  return remaining > MONEY_TOLERANCE ? remaining : 0;
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
    .filter((line) => line.methodId != null && parseMoneyInput(line.amount) > 0)
    .map((line) => {
      const name = methodById.get(line.methodId as number) ?? 'Pago';
      return `${name} S/ ${parseMoneyInput(line.amount).toFixed(2)}`;
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
  amount: roundMoney(amount).toFixed(2),
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
  const normalizedPrimary = roundMoney(Math.min(Math.max(primaryAmount, 0), total));
  const secondaryAmount = roundMoney(total - normalizedPrimary);

  return [
    createPaymentLine(primaryMethodId, normalizedPrimary),
    createPaymentLine(secondaryMethodId, secondaryAmount),
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

  return createSplitPaymentLines(
    primaryMethodId,
    secondaryMethodId,
    primaryAmount,
    total,
  );
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
  const headSum = sumPaymentAmounts(head);
  const tailAmount = Math.max(0, roundMoney(total - headSum));

  return [
    ...head,
    {
      ...tail,
      amount: tailAmount.toFixed(2),
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
  lines.map((line, lineIndex) =>
    lineIndex === index ? { ...line, methodId } : line,
  );

export const addPaymentLine = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
): SalePaymentLineDraft[] => {
  const usedMethodIds = new Set(
    lines.map((line) => line.methodId).filter((id): id is number => id != null),
  );
  const nextMethodId =
    paymentMethods.find((method) => !usedMethodIds.has(method.id))?.id ?? null;
  const remaining = remainingPaymentAmount(lines, total);

  return [
    ...lines,
    createPaymentLine(nextMethodId, remaining > 0 ? remaining : 0),
  ];
};

export const removePaymentLine = (
  lines: SalePaymentLineDraft[],
  index: number,
  total: number,
): SalePaymentLineDraft[] => {
  if (lines.length <= 1) {
    return lines;
  }

  const removedAmount = parseMoneyInput(lines[index]?.amount);
  const next = lines.filter((_, lineIndex) => lineIndex !== index);

  if (next.length === 1) {
    return [createSinglePaymentLine(next[0].methodId, total)];
  }

  const lastIndex = next.length - 1;
  const lastLine = next[lastIndex];
  next[lastIndex] = createPaymentLine(
    lastLine.methodId,
    parseMoneyInput(lastLine.amount) + removedAmount,
  );

  return rebalancePaymentLines(next, total);
};

export const buildPaymentPayload = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  _total: number,
): SalePaymentPayloadLine[] => {
  const methodById = new Map(paymentMethods.map((method) => [method.id, method]));

  return lines
    .filter((line) => line.methodId != null && parseMoneyInput(line.amount) > 0)
    .map((line) => {
      const method = methodById.get(line.methodId as number);
      const amount = parseMoneyInput(line.amount);
      const isCash = isCashPaymentMethod(method);
      const tendered = isCash
        ? parseMoneyInput(line.tenderedAmount ?? line.amount)
        : amount;
      const change = isCash ? Math.max(0, roundMoney(tendered - amount)) : 0;

      return {
        payment_method_id: line.methodId as number,
        amount: amount.toFixed(2),
        ...(isCash
          ? {
              tendered_amount: tendered.toFixed(2),
              change_amount: change.toFixed(2),
            }
          : {}),
      };
    });
};

export const totalCashChangeAmount = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
): number => {
  const payload = buildPaymentPayload(lines, paymentMethods, total);
  return roundMoney(
    payload.reduce((sum, payment) => sum + parseMoneyInput(payment.change_amount), 0),
  );
};

export type PaymentValidationResult =
  | { valid: true }
  | { valid: false; message: string };

export const validatePaymentLines = (
  lines: SalePaymentLineDraft[],
  paymentMethods: PaymentMethodOption[],
  total: number,
): PaymentValidationResult => {
  if (total <= 0) {
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

    const amount = parseMoneyInput(line.amount);
    if (amount <= 0) {
      return { valid: false, message: `El monto del pago ${index + 1} debe ser mayor a cero.` };
    }

    const method = methodById.get(line.methodId);
    if (method && isCashPaymentMethod(method)) {
      const tendered = parseMoneyInput(line.tenderedAmount ?? line.amount);
      if (tendered + MONEY_TOLERANCE < amount) {
        return {
          valid: false,
          message: `El efectivo recibido en el pago ${index + 1} es menor al monto asignado.`,
        };
      }
    }
  }

  if (!paymentsMatchTotal(lines, total)) {
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