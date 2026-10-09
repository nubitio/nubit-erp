import type { DataRecord } from '@nubitio/crud';
import type { InvoiceActionHandlers, InvoiceRow } from '../lib/invoiceActions';
import {
  consultInvoiceCdr,
  consultInvoiceCpe,
  openInvoiceReceipt,
  sendInvoiceEmail,
  sendInvoiceToSunat,
  shareInvoiceWhatsApp,
} from '../lib/invoiceActions';

export const createInvoiceRowActions = (handlers: InvoiceActionHandlers) => (row: DataRecord) => {
  const invoiceRow = row as InvoiceRow;
  if (!invoiceRow.id) {
    return [];
  }

  return [
    {
      text: 'Ver',
      icon: 'ph-eye',
      onClick: () => openInvoiceReceipt(invoiceRow, handlers.notify),
    },
    {
      text: 'Consulta CDR',
      icon: 'ph-file-magnifying-glass',
      onClick: () => consultInvoiceCdr(invoiceRow, handlers),
    },
    {
      text: 'Consulta CPE',
      icon: 'ph-seal-check',
      onClick: () => consultInvoiceCpe(invoiceRow, handlers),
    },
    {
      text: 'Compartir WhatsApp',
      icon: 'ph-whatsapp-logo',
      onClick: () => shareInvoiceWhatsApp(invoiceRow, handlers.notify),
    },
    {
      text: 'Enviar a SUNAT',
      icon: 'ph-cloud-arrow-up',
      onClick: () => sendInvoiceToSunat(invoiceRow, handlers),
    },
    {
      text: 'Enviar por correo',
      icon: 'ph-envelope-simple',
      onClick: () => sendInvoiceEmail(invoiceRow, handlers),
    },
  ];
};
