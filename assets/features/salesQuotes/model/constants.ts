import { createCrudEvents } from '@nubitio/crud';
import { saleQuoteEndpoints } from '../api/saleQuoteEndpoints';

export const saleQuoteEvents = createCrudEvents('sale-quote');
export const saleQuoteApiUrl = saleQuoteEndpoints.list();

export const SALE_QUOTE_STATUS_LABELS: Record<string, string> = {
  draft: 'Borrador',
  sent: 'Enviada',
  accepted: 'Aceptada',
  expired: 'Vencida',
  converted: 'Convertida',
};
