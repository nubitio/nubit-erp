import { formatMoney } from '@shared/lib/displayFormat';

export interface SalePayment {
  payment_method_id: number;
  method_name?: string | null;
  amount: string;
  paid_at?: string | null;
  reference?: string | null;
}

export interface SaleDetail {
  id: string;
  invoiceFullNumber?: string | null;
  series?: string | null;
  number?: string | null;
  documentType?: string | null;
  hasInvoice?: boolean;
  taxDocumentId?: string | null;
  publicReceiptUrl?: string | null;
  sunatStatus?: string | null;
  sunatState?: string;
  paymentState?: string;
  status: string;
  currency: string;
  subtotal: string;
  discountTotal: string;
  taxTotal: string;
  grandTotal: string;
  paidTotal: string;
  changeTotal: string;
  cashRoundingAdjustment?: string;
  balanceDue: string;
  issuedDate?: string | null;
  issuedAt?: string | null;
  dueDate?: string | null;
  createdAt?: string | null;
  updatedAt?: string | null;
  note?: string | null;
  customer?: {
    id?: number;
    businessName?: string;
    ruc?: string;
    fiscalAddress?: string | null;
    email?: string | null;
  } | null;
  warehouse?: { name?: string } | null;
  paymentMethod?: { name?: string } | null;
  payments?: SalePayment[];
}

export interface SaleDetailItem {
  id: number;
  lineNo: number;
  nameSnapshot: string;
  skuSnapshot?: string | null;
  qty: string;
  unitPrice: string;
  netAmount: string;
}

export const paymentStateLabels: Record<string, string> = {
  paid: 'Cobrada',
  due: 'Por cobrar',
  voided: 'Anulada',
};
export const sunatStateLabels: Record<string, string> = {
  accepted: 'Aceptada por SUNAT',
  pending: 'Pendiente de atención',
  not_applicable: 'No aplica',
};

export function saleDocument(
  sale: Pick<SaleDetail, 'invoiceFullNumber' | 'series' | 'number'>,
): string {
  return (
    sale.invoiceFullNumber ||
    [sale.series, sale.number].filter(Boolean).join('-') ||
    'Sin comprobante'
  );
}

export function saleTitle(sale: SaleDetail): string {
  const type =
    sale.documentType === '01' ? 'Factura' : sale.documentType === '03' ? 'Boleta' : 'Venta';
  const number = saleDocument(sale);
  return number === 'Sin comprobante' ? 'Detalle de venta' : `${type} ${number}`;
}

/** Date-only business values must not shift to the previous day in Peru. */
export function saleDate(value?: string | null, withTime = false): string {
  if (!value) return 'Sin fecha registrada';
  const dateOnly =
    /^\d{4}-\d{2}-\d{2}$/.test(value) || (!withTime && /^\d{4}-\d{2}-\d{2}T/.test(value));
  const date = new Date(dateOnly ? `${value.slice(0, 10)}T12:00:00-05:00` : value);
  if (Number.isNaN(date.getTime())) return 'Sin fecha registrada';
  return new Intl.DateTimeFormat('es-PE', {
    timeZone: 'America/Lima',
    dateStyle: 'short',
    ...(withTime && !dateOnly ? { timeStyle: 'short' as const } : {}),
  }).format(date);
}

export function saleMoney(value: string | number | null | undefined, currency: string): string {
  return value == null ? '—' : formatMoney(value, currency);
}

export function receiptLinks(publicUrl?: string | null): { xml: string; cdr: string } | null {
  if (!publicUrl || !/^\/receipt\/[^?#]+$/.test(publicUrl)) return null;
  return { xml: `${publicUrl}/xml`, cdr: `${publicUrl}/cdr` };
}
