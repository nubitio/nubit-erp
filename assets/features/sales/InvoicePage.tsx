/* eslint-disable react-hooks/refs -- CRUD row action handlers intentionally read Grid refs inside callbacks, not during render. */
import { useMemo, useRef } from 'react';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { SmartCrudPage, defineResource, type DataRecord, type GridHandle } from '@nubitio/crud';
import { InvoiceDetailFields, InvoiceFields } from './InvoiceFields';
import { createInvoiceRowActions } from './ui/createInvoiceRowActions';
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
  permissions: { canAdd: false, canEdit: false, canView: false, canDelete: false },
});

export const InvoicePage = () => {
  const gridRef = useRef<GridHandle | null>(null);
  const actionHandlersRef = useRef({
    showLoading: (message?: string) => gridRef.current?.showLoading(message),
    hideLoading: () => gridRef.current?.hideLoading(),
    refreshGrid: () => gridRef.current?.refresh(),
    notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') =>
      toast(message, type),
  });

  const resource = useMemo(
    () => ({
      ...baseResource,
      rowActions: createInvoiceRowActions(actionHandlersRef.current),
    }),
    [],
  );

  return <SmartCrudPage resource={resource} gridRef={gridRef} />;
};
