import { computeCartTotals, computeLineAmount } from '@shared/lib/saleCalculations';
import { buildSettlementPayload } from '@shared/lib/salePayments';
import type { PaymentMethodOption } from '@shared/pos/types';
import { getIRI } from '@shared/lib/SharedUtils';

export type VoucherChoice = 'AUTO' | 'FACTURA' | 'BOLETA';
export type PaymentCondition = 'CASH' | 'CREDIT';
export type InvoiceCurrency = 'PEN' | 'USD';

export interface InvoiceLineDraft {
  key: string;
  productId: string | null;
  description: string;
  quantity: string;
  unitPrice: string;
  /** Catalog price, kept so a customer's price list can be removed again. */
  basePrice: string;
}

export interface InvoiceDraft {
  customerId: string | null;
  warehouseId: string | null;
  voucher: VoucherChoice;
  currency: InvoiceCurrency;
  exchangeRate: string;
  issuedDate: string;
  condition: PaymentCondition;
  paymentMethodId: string | null;
  termDays: string;
  note: string;
  lines: InvoiceLineDraft[];
}

export const newLine = (): InvoiceLineDraft => ({
  key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
  productId: null,
  description: '',
  quantity: '1',
  unitPrice: '',
  basePrice: '',
});

const toNumber = (value: string): number => {
  const parsed = Number.parseFloat(value.replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : 0;
};

/** Price of each product under the customer's price list (empty map = catalog prices). */
export type PriceListPrices = ReadonlyMap<string, string>;

export const pickPrice = (productId: string, basePrice: string, prices: PriceListPrices): string =>
  prices.get(productId) ?? basePrice;

/** Reprices the lines already picked when the customer, and so the price list, changes. */
export const applyPriceList = (
  lines: InvoiceLineDraft[],
  prices: PriceListPrices,
): InvoiceLineDraft[] =>
  lines.map((line) =>
    line.productId === null
      ? line
      : { ...line, unitPrice: pickPrice(line.productId, line.basePrice, prices) },
  );

export const isLineFilled = (line: InvoiceLineDraft): boolean =>
  line.productId !== null && toNumber(line.quantity) > 0;

export const lineTotal = (line: InvoiceLineDraft): number =>
  computeLineAmount(toNumber(line.quantity), line.unitPrice || '0', 0).netAmount;

export const draftTotals = (lines: InvoiceLineDraft[], igvRate: number) =>
  computeCartTotals(
    lines
      .filter(isLineFilled)
      .map((line) => ({ price: line.unitPrice || '0', quantity: toNumber(line.quantity) })),
    0,
    igvRate,
  );

export const addDays = (isoDate: string, days: number): string => {
  const date = new Date(`${isoDate}T00:00:00`);
  date.setDate(date.getDate() + days);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
};

/** Returns the first problem that blocks emission, or null when the draft is ready. */
export const validateDraft = (
  draft: InvoiceDraft,
  total: number,
  methods: PaymentMethodOption[],
): string | null => {
  if (!draft.customerId) return 'Selecciona un cliente.';
  if (!draft.warehouseId) return 'Selecciona un almacén.';
  if (draft.lines.filter(isLineFilled).length === 0) return 'Agrega al menos una línea.';
  if (draft.lines.some((line) => isLineFilled(line) && toNumber(line.unitPrice) <= 0)) {
    return 'Todas las líneas necesitan un precio mayor a cero.';
  }
  if (total <= 0) return 'El total debe ser mayor a cero.';
  if (draft.currency === 'USD' && toNumber(draft.exchangeRate) <= 0) {
    return 'Indica el tipo de cambio.';
  }
  if (draft.condition === 'CASH' && !methods.some((m) => String(m.id) === draft.paymentMethodId)) {
    return 'Selecciona el medio de pago.';
  }
  if (draft.condition === 'CREDIT' && toNumber(draft.termDays) < 1) {
    return 'Indica los días de crédito.';
  }
  return null;
};

export const buildSalePayload = (
  api: string,
  draft: InvoiceDraft,
  total: number,
  methods: PaymentMethodOption[],
  creditMethodId: number | null,
  emitInvoice: boolean,
  nowIso: string,
) => {
  const methodId =
    draft.condition === 'CREDIT' ? creditMethodId : Number(draft.paymentMethodId ?? 0) || null;
  const settlements = buildSettlementPayload(
    [{ methodId, amount: total.toFixed(2) }],
    methods,
    total,
  );

  return {
    issuedAt: nowIso,
    issuedDate: draft.issuedDate,
    ...(draft.condition === 'CREDIT'
      ? { dueDate: addDays(draft.issuedDate, Math.floor(toNumber(draft.termDays))) }
      : {}),
    warehouse: getIRI(`${api}warehouses`, draft.warehouseId ?? ''),
    customer: getIRI(`${api}customers`, draft.customerId ?? ''),
    paymentMethod: methodId ? getIRI(`${api}payment-methods`, String(methodId)) : null,
    settlements,
    note: draft.note.trim() || null,
    discountPercent: '0.00',
    cashRoundingAdjustment: '0.00',
    emitInvoice,
    requestedVoucherType: draft.voucher,
    currency: draft.currency,
    exchangeRate: draft.currency === 'USD' ? draft.exchangeRate : '1.000000',
    grandTotal: total.toFixed(2),
    writeItems: draft.lines.filter(isLineFilled).map((line) => ({
      product: getIRI(`${api}products`, line.productId ?? ''),
      qty: toNumber(line.quantity),
      unitPrice: line.unitPrice,
      description: line.description.trim(),
      discountAmount: '0.00',
      netAmount: lineTotal(line).toFixed(2),
    })),
  };
};
