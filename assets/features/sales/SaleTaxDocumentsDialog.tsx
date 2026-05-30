import { useRef, useMemo } from 'react';
import { AppDialog } from '@nubit/ui';
import { useScreenSize } from '@nubit/admin';
import { DataGridView, type GridHandle } from '@nubit/crud';
import { getCoreApiBaseUrl } from '@nubit/core';

const API = getCoreApiBaseUrl();
import { InvoiceFields } from './InvoiceFields';
import type { InvoiceRow } from './lib/invoiceActions';
import {
  runConsultInvoiceCdrAction,
  runConsultInvoiceCpeAction,
  runOpenInvoiceReceiptAction,
  runSendInvoiceEmailAction,
  runSendInvoiceToSunatAction,
} from './lib/invoiceActions';
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
    () => ({
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
    }),
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
          url={`${API}sales/${saleId}/tax-documents`}
          fields={fields}
          toolbar={toolbar}
          sort={[{ selector: 'createdAt', desc: true }]}
        />
      )}
    </AppDialog>
  );
};
