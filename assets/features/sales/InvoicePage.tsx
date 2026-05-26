import { useMemo, useRef } from 'react';
import { API_BASE_URL } from '@app/constants';
import { SmartCrudPage, defineResource, type DataRecord, type GridHandle } from '@nubit/crud';
import { InvoiceDetailFields, InvoiceFields } from './InvoiceFields';
import type { InvoiceRow } from '@features/invoice/lib/invoiceActions';
import { invoiceEvents } from '@features/invoice/model/events';
import {
  runConsultInvoiceCdrAction,
  runConsultInvoiceCpeAction,
  runOpenInvoiceReceiptAction,
  runSendInvoiceEmailAction,
  runSendInvoiceToSunatAction,
} from '@features/invoice/lib/invoiceActions';
import toast from '@shared/lib/SharedUtils';

const API_URL = API_BASE_URL + 'tax-documents';
const fields = InvoiceFields();

const baseResource = defineResource({
  id: 'invoice',
  title: 'Comprobantes',
  apiUrl: API_URL + '?type=invoice',
  fields,
  gridDetailFields: (parentRow: DataRecord) =>
    InvoiceDetailFields((parentRow?.currency as string) ?? 'PEN'),
  detailUrl: API_URL + '/{id}/lines',
  events: invoiceEvents,
  sort: [{ selector: 'createdAt', desc: true }],
  dialogWidth: 900,
  dialogHeight: 720,
  permissions: { canAdd: true, canEdit: true, canDelete: true },
});

export const InvoicePage = () => {
  const gridRef = useRef<GridHandle | null>(null);
  const selectedRowRef = useRef<DataRecord | null>(null);

  const actionDeps = useMemo(
    () => ({
      getSelectedRow: () => selectedRowRef.current as InvoiceRow | undefined,
      showLoading: (message?: string) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') =>
        toast(message, type),
    }),
    [],
  );

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: [
        {
          text: 'Enviar a SUNAT',
          type: 'info',
          onClick: () => runSendInvoiceToSunatAction(actionDeps),
        },
        {
          text: 'Enviar por correo',
          type: 'success',
          onClick: () => runSendInvoiceEmailAction(actionDeps),
        },
        { text: 'Ver', type: 'primary', onClick: () => runOpenInvoiceReceiptAction(actionDeps) },
        {
          text: 'Consulta CDR',
          type: 'warning',
          onClick: () => runConsultInvoiceCdrAction(actionDeps),
        },
        {
          text: 'Consulta CPE',
          type: 'success',
          onClick: () => runConsultInvoiceCpeAction(actionDeps),
        },
      ],
    }),
    [actionDeps],
  );

  return (
    <SmartCrudPage
      resource={resource}
      gridRef={gridRef}
      onSelectionChanged={(rows) => {
        selectedRowRef.current = rows[0] ?? null;
      }}
    />
  );
};
