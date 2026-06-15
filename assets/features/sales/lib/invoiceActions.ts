import { taxDocumentService } from '../services/taxDocumentService';

export type InvoiceRow = {
  id: string;
  createdAt?: string;
  sunatStatus?: string;
  publicReceiptUrl?: string;
  documentType?: string;
  series?: string;
  number?: string | number;
  customer?: string | { businessName?: string };
  total?: number;
  currency?: string;
};

const resolvePublicReceiptUrl = (url: string): string =>
  url.startsWith('http') ? url : `${window.location.origin}${url}`;

export const buildInvoiceWhatsAppShareUrl = (row: InvoiceRow): string | null => {
  if (!row.publicReceiptUrl) {
    return null;
  }

  const receiptUrl = resolvePublicReceiptUrl(row.publicReceiptUrl);
  const docLabel = row.documentType === '03' ? 'Boleta' : 'Factura';
  const customerName =
    typeof row.customer === 'string'
      ? row.customer
      : row.customer?.businessName;
  const amount =
    row.total != null
      ? `${row.currency === 'USD' ? '$' : row.currency === 'EUR' ? '€' : 'S/'} ${Number(row.total).toFixed(2)}`
      : null;

  const parts = [
    `Comprobante electrónico ${docLabel} ${row.series ?? ''}-${row.number ?? ''}`.trim(),
    customerName ? `para ${customerName}` : null,
    amount ? `por ${amount}` : null,
    receiptUrl,
  ].filter(Boolean);

  return `https://wa.me/?text=${encodeURIComponent(parts.join(' '))}`;
};

export type InvoiceActionHandlers = {
  showLoading: (message?: string) => void;
  hideLoading: () => void;
  refreshGrid: () => void;
  notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
};

export const openInvoiceReceipt = (
  row: InvoiceRow,
  notify: InvoiceActionHandlers['notify'],
) => {
  if (!row.publicReceiptUrl) {
    notify('El comprobante no tiene URL pública disponible', 'warning');
    return;
  }
  window.open(resolvePublicReceiptUrl(row.publicReceiptUrl), '_blank');
};

export const shareInvoiceWhatsApp = (
  row: InvoiceRow,
  notify: InvoiceActionHandlers['notify'],
) => {
  const shareUrl = buildInvoiceWhatsAppShareUrl(row);
  if (!shareUrl) {
    notify('El comprobante no tiene URL pública disponible', 'warning');
    return;
  }
  window.open(shareUrl, '_blank', 'noopener,noreferrer');
};

export const sendInvoiceToSunat = (row: InvoiceRow, handlers: InvoiceActionHandlers) => {
  if (row.sunatStatus === '0') {
    handlers.notify('El comprobante ya fue enviado a SUNAT', 'warning');
    return;
  }

  handlers.showLoading('Enviando comprobante a SUNAT...');
  taxDocumentService
    .sendDocument(row.id)
    .then((response) => {
      handlers.notify(response.data.message, response.data.success ? 'success' : 'error');
      handlers.refreshGrid();
    })
    .finally(() => handlers.hideLoading());
};

export const sendInvoiceEmail = (row: InvoiceRow, handlers: InvoiceActionHandlers) => {
  handlers.showLoading('Enviando comprobante por correo...');
  taxDocumentService
    .sendEmail(row.id)
    .then(() => {
      handlers.notify('El comprobante fue enviado por correo', 'success');
      handlers.refreshGrid();
    })
    .finally(() => handlers.hideLoading());
};

export const consultInvoiceCdr = (row: InvoiceRow, handlers: InvoiceActionHandlers) => {
  handlers.showLoading('Consultando CDR...');
  taxDocumentService
    .consultDocument(row.id)
    .then((response) => {
      handlers.notify(response.data.message, response.data.success ? 'success' : 'error');
      handlers.refreshGrid();
    })
    .finally(() => handlers.hideLoading());
};

export const consultInvoiceCpe = (row: InvoiceRow, handlers: InvoiceActionHandlers) => {
  handlers.showLoading('Consultando CPE...');
  taxDocumentService
    .validateDocument(row.id)
    .then((response) => {
      handlers.notify(response.data.message, response.data.success ? 'success' : 'error');
      handlers.refreshGrid();
    })
    .finally(() => handlers.hideLoading());
};