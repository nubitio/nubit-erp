import { useCallback, useMemo, useRef, useState } from 'react';
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
import { DocumentStatusDrawer } from './ui/DocumentStatusDrawer';
import toast from '@shared/lib/SharedUtils';
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

function formatInvoiceSubtitle(row: InvoiceRow): string | undefined {
  const doc =
    row.series && row.number != null ? `${row.series}-${String(row.number).padStart(3, '0')}` : null;
  const customer = typeof row.customer === 'string' ? row.customer : null;
  const parts = [doc, customer].filter(Boolean);
  return parts.length > 0 ? parts.join(' · ') : undefined;
}

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
  const [statusDrawerOpen, setStatusDrawerOpen] = useState(false);

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

  const openStatusDrawer = useCallback(() => {
    const row = gridRef.current?.getSelectedRow() as InvoiceRow | undefined;
    if (!row) {
      toast('Seleccione un comprobante', 'warning');
      return;
    }
    setSelectedInvoice(row);
    setStatusDrawerOpen(true);
  }, []);

  const resource = useMemo(
    () => ({
      ...baseResource,
      toolbar: {
        utility: [
          {
            key: 'sunat-status',
            text: 'Estado SUNAT',
            icon: 'ph-path',
            hint: 'Ciclo de vida SUNAT del comprobante seleccionado',
            onClick: openStatusDrawer,
          },
        ],
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
    [actionDeps, openStatusDrawer],
  );

  return (
    <>
      <SmartCrudPage
        resource={resource}
        gridRef={gridRef}
        onSelectionChanged={(rows) => {
          const row = (rows[0] as InvoiceRow | undefined) ?? null;
          setSelectedInvoice(row);
        }}
      />
      <DocumentStatusDrawer
        isOpen={statusDrawerOpen}
        onClose={() => setStatusDrawerOpen(false)}
        subtitle={selectedInvoice ? formatInvoiceSubtitle(selectedInvoice) : undefined}
        document={
          selectedInvoice
            ? {
                createdAt: selectedInvoice.createdAt,
                sunatStatus: selectedInvoice.sunatStatus,
              }
            : null
        }
      />
    </>
  );
};