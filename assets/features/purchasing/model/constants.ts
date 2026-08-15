import { createCrudEvents } from '@nubitio/crud';
import { purchaseOrderEndpoints } from '../api/purchaseOrderEndpoints';

export const purchaseOrderEvents = createCrudEvents('purchase-order');
export const purchaseOrderApiUrl = purchaseOrderEndpoints.list();

export const PURCHASE_ORDER_STATUS_LABELS: Record<string, string> = {
  draft: 'Borrador',
  sent: 'Enviada',
  partially_received: 'Recibida parcialmente',
  received: 'Recibida',
  cancelled: 'Cancelada',
};
