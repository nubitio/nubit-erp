import { useMemo, useRef } from 'react';
import { Constants } from '@core/common/Constants';
import { DataGridView, DialogView, FormView, type GridHandle } from '@core/ui/CrudViews';
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

const API_URL = Constants.API_BASE_URL + 'tax-documents';
const fields = InvoiceFields();
const detailFields = (parentRow: Record<string, unknown>) =>
  InvoiceDetailFields((parentRow?.currency as string) ?? 'PEN');

export const InvoicePage = () => {
  const gridRef = useRef<GridHandle>(null);

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

  const toolbar = useMemo(
    () => [
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
    [actionDeps],
  );

  return (
    <div id="wrapper" className="invoice-page">
      <DataGridView
        id="invoice"
        ref={gridRef}
        title="Comprobantes"
        toolbar={toolbar}
        url={API_URL + '?type=invoice'}
        fields={fields}
        detailUrl={API_URL + '/{id}/lines'}
        detailFields={detailFields}
        events={invoiceEvents}
        sort={[{ selector: 'createdAt', desc: true }]}
      />
      <DialogView title="Comprobante" width={900} maxHeight={720} events={invoiceEvents}>
        <FormView
          className="plain-styled-form entity-form"
          url={API_URL}
          fields={fields}
          events={invoiceEvents}
        />
      </DialogView>
    </div>
  );
};
