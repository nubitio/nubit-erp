import { useRef, useMemo } from 'react';
import { AppDialog } from '@nubit/ui';
import { useScreenSize } from '@nubit/admin';
import { DataGridView, type GridHandle } from '@nubit/crud';
import { API_BASE_URL } from '@app/constants';
import { InvoiceFields } from './InvoiceFields';
import type { InvoiceRow } from '@features/invoice/lib/invoiceActions';
import {
  runConsultInvoiceCdrAction,
  runConsultInvoiceCpeAction,
  runOpenInvoiceReceiptAction,
  runSendInvoiceEmailAction,
  runSendInvoiceToSunatAction,
} from '@features/invoice/lib/invoiceActions';
import toast from '@shared/lib/SharedUtils';

interface Props {
  saleId: string | null;
  visible: boolean;
  onClose: () => void;
}

const fields = InvoiceFields();

export const SaleTaxDocumentsDialog = ({ saleId, visible, onClose }: Props) => {
  const { isXSmall } = useScreenSize();
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
    <AppDialog
      title="Comprobantes de la venta"
      width={isXSmall ? undefined : 1100}
      height={isXSmall ? undefined : 600}
      fullScreen={isXSmall}
      visible={visible}
      showCloseButton
      closeOnOutsideClick
      closeOnEscape
      onClose={onClose}
    >
      {visible && saleId && (
        <DataGridView
          id="sale-tax-documents"
          ref={gridRef}
          title="Comprobantes"
          url={`${API_BASE_URL}sales/${saleId}/tax-documents`}
          fields={fields}
          toolbar={toolbar}
          sort={[{ selector: 'createdAt', desc: true }]}
        />
      )}
    </AppDialog>
  );
};
