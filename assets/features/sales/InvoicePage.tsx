import { useMemo, useRef } from 'react';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { SmartCrudPage, defineResource, type DataRecord, type GridHandle } from '@nubit/crud';
import { InvoiceDetailFields, InvoiceFields } from './InvoiceFields';
import type { InvoiceRow } from './lib/invoiceActions';
import {
  runConsultInvoiceCdrAction,
  runConsultInvoiceCpeAction,
  runOpenInvoiceReceiptAction,
  runSendInvoiceEmailAction,
  runSendInvoiceToSunatAction,
} from './lib/invoiceActions';
import toast from '@shared/lib/SharedUtils';

const API_URL = API + 'tax-documents';
const fields = InvoiceFields();

const baseResource = defineResource(API_URL + '?type=invoice', {
  id: 'invoice',
  title: 'Comprobantes',
  fields,
  gridDetail: {
    url: API_URL + '/{id}/lines',
    fields: (parentRow: DataRecord) =>
      InvoiceDetailFields((parentRow?.currency as string) ?? 'PEN'),
  },
  sort: [{ selector: 'createdAt', desc: true }],
  dialogWidth: 900,
  dialogHeight: 720,
  permissions: { canAdd: false, canEdit: false, canDelete: false },
});

export const InvoicePage = () => {
  const gridRef = useRef<GridHandle | null>(null);

  const actionDeps = useMemo(
    () => ({
      getSelectedRow: () => gridRef.current?.getSelectedRow() as InvoiceRow | undefined,
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
      toolbar: {
        selection: [
          {
            text: 'Ver',
            type: 'primary',
            group: 'view',
            groupLabel: 'Consulta',
            onClick: () => runOpenInvoiceReceiptAction(actionDeps),
          },
          {
            text: 'Consulta CDR',
            type: 'warning',
            group: 'view',
            groupLabel: 'Consulta',
            onClick: () => runConsultInvoiceCdrAction(actionDeps),
          },
          {
            text: 'Consulta CPE',
            type: 'success',
            group: 'view',
            groupLabel: 'Consulta',
            onClick: () => runConsultInvoiceCpeAction(actionDeps),
          },
          {
            text: 'Enviar a SUNAT',
            type: 'info',
            group: 'delivery',
            groupLabel: 'Envío',
            onClick: () => runSendInvoiceToSunatAction(actionDeps),
          },
          {
            text: 'Enviar por correo',
            type: 'success',
            group: 'delivery',
            groupLabel: 'Envío',
            onClick: () => runSendInvoiceEmailAction(actionDeps),
          },
        ],
      },
    }),
    [actionDeps],
  );

  return <SmartCrudPage resource={resource} gridRef={gridRef} />;
};
