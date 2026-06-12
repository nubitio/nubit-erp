import { useMemo, useRef, useState } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { SmartCrudPage, defineResource, type DataRecord, type GridHandle } from '@nubitio/crud';
import { InvoiceDetailFields, InvoiceFields } from './InvoiceFields';
import type { InvoiceRow } from './lib/invoiceActions';
import {
  runConsultInvoiceCdrAction,
  runConsultInvoiceCpeAction,
  runOpenInvoiceReceiptAction,
  runSendInvoiceEmailAction,
  runSendInvoiceToSunatAction,
} from './lib/invoiceActions';
import { DocumentStatusTimeline } from './ui/DocumentStatusTimeline';
import toast from '@shared/lib/SharedUtils';
import './InvoicePage.scss';

const API_URL = API + 'tax-documents';
const fields = InvoiceFields();

const INVOICE_FORM_LAYOUT = {
  type: 'tabs' as const,
  tabs: [
    {
      label: 'Comercial',
      icon: 'ph-receipt',
      fields: ['issueDate', 'customer', 'ruc', 'documentType', 'series', 'number', 'total'],
    },
    {
      label: 'SUNAT',
      icon: 'ph-cloud-arrow-up',
      fields: ['sunatStatus', 'sunatMessage', 'voided', 'voidTicket'],
    },
    {
      label: 'Detracción',
      icon: 'ph-percent',
      fields: ['detraccionCodigo', 'detraccionMonto'],
    },
  ],
};

const INVOICE_COLUMN_PRESETS = [
  {
    key: 'basic',
    label: 'Básico',
    columns: ['issueDate', 'customer', 'series', 'number', 'total', 'sunatStatus'],
  },
  {
    key: 'full',
    label: 'Completo',
    columns: [
      'issueDate',
      'ruc',
      'customer',
      'documentType',
      'series',
      'number',
      'total',
      'voided',
      'sunatStatus',
      'sunatMessage',
    ],
  },
];

const baseResource = defineResource(API_URL + '?type=invoice', {
  id: 'invoice',
  title: 'Comprobantes',
  fields,
  defaultPreset: 'basic',
  columnPresets: INVOICE_COLUMN_PRESETS,
  viewMode: 'page',
  routing: { routeParam: 'id' },
  formLayout: INVOICE_FORM_LAYOUT,
  emptyState: {
    title: 'Sin comprobantes',
    description: 'Los comprobantes electrónicos emitidos aparecerán aquí.',
    icon: 'receipt',
  },
  gridDetail: {
    url: API_URL + '/{id}/lines',
    fields: (parentRow: DataRecord) =>
      InvoiceDetailFields((parentRow?.currency as string) ?? 'PEN'),
  },
  sort: [{ selector: 'createdAt', desc: true }],
  permissions: { canAdd: false, canEdit: false, canView: true, canDelete: false },
});

export const InvoicePage = () => {
  const gridRef = useRef<GridHandle | null>(null);
  const [selectedInvoice, setSelectedInvoice] = useState<InvoiceRow | null>(null);

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

  return (
    <div className="nb-invoice-page">
      <div className="nb-invoice-page__main">
        <SmartCrudPage
          resource={resource}
          gridRef={gridRef}
          onSelectionChanged={(rows) => {
            const row = (rows[0] as InvoiceRow | undefined) ?? null;
            setSelectedInvoice(row);
          }}
        />
      </div>
      {selectedInvoice && (
        <aside className="nb-invoice-page__aside" aria-label="Estado SUNAT del comprobante">
          <DocumentStatusTimeline
            className="nb-document-status-timeline"
            document={{
              createdAt: selectedInvoice.createdAt,
              sunatStatus: selectedInvoice.sunatStatus,
            }}
          />
        </aside>
      )}
    </div>
  );
};