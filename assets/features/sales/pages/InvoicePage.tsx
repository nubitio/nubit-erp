import './InvoicePage.scss';
import { Constants } from '@core/common/Constants';
import { DxDataGrid, DxDataGridEvents } from '@core/datagrid/DxDataGrid';
import { DxDialog } from '@core/dialog/DxDialog';
import { DxForm } from '@core/form/DxForm';
import { InvoiceDetailFields, InvoiceFields } from './InvoiceFields';
import { useMemo, useRef } from 'react';
import {
  createInvoiceToolbar,
  invoiceEvents,
  runConsultInvoiceCdrAction,
  runConsultInvoiceCpeAction,
  runOpenInvoiceReceiptAction,
  runSendInvoiceEmailAction,
  runSendInvoiceToSunatAction,
} from '@features/invoice';
import toast from '@shared/lib/SharedUtils';

export const InvoicePage = () => {
  const container = 'wrapper';
  const fields = useMemo(() => InvoiceFields(), []);
  const detailFields = useMemo(
    () => (parentRow: Record<string, unknown>) =>
      InvoiceDetailFields((parentRow?.currency as string) ?? 'PEN'),
    [],
  );
  const gridRef = useRef<DxDataGridEvents>(null);

  const API_URL = Constants.API_BASE_URL + 'tax-documents';

  const actionDeps = {
    getSelectedRow: () => gridRef.current?.getSelectedRow(),
    showLoading: (message?: string) => gridRef.current?.showLoading(message),
    hideLoading: () => gridRef.current?.hideLoading(),
    refreshGrid: () => gridRef.current?.refresh(),
    notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') =>
      toast(message, type),
  };

  const toolbar = createInvoiceToolbar({
    onSendSunat: () => runSendInvoiceToSunatAction(actionDeps),
    onSendEmail: () => runSendInvoiceEmailAction(actionDeps),
    onView: () => runOpenInvoiceReceiptAction(actionDeps),
    onConsultCdr: () => runConsultInvoiceCdrAction(actionDeps),
    onConsultCpe: () => runConsultInvoiceCpeAction(actionDeps),
  });

  return (
    <div id={container} className="invoice-page">
      <DxDataGrid
        id={'invoice'}
        ref={gridRef}
        title={'Comprobantes'}
        toolbar={toolbar}
        url={API_URL + '?type=invoice'}
        fields={fields}
        detailUrl={API_URL + '/{id}/lines'}
        detailFields={detailFields}
        events={invoiceEvents}
        sort={[{ selector: 'createdAt', desc: true }]}
      />
      <DxDialog title="Comprobante" width={900} maxHeight={720} events={invoiceEvents}>
        <DxForm
          className="plain-styled-form entity-form"
          url={API_URL}
          fields={fields}
          events={invoiceEvents}
        />
      </DxDialog>
    </div>
  );
};
