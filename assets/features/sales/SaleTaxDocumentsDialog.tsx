/* eslint-disable react-hooks/refs -- Dialog grid actions intentionally read Grid refs inside callbacks, not during render. */
import { useRef, useMemo } from 'react';
import { AppDialog } from '@nubitio/ui';
import { useScreenSize } from '@nubitio/admin';
import { DataGridView, type GridHandle } from '@nubitio/crud';
import { getCoreApiBaseUrl } from '@nubitio/core';

const API = getCoreApiBaseUrl();
import { InvoiceFields } from './InvoiceFields';
import { createInvoiceRowActions } from './ui/createInvoiceRowActions';
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

  const actionHandlers = useMemo(
    () => ({
      showLoading: (message?: string) => gridRef.current?.showLoading(message),
      hideLoading: () => gridRef.current?.hideLoading(),
      refreshGrid: () => gridRef.current?.refresh(),
      notify: (message: string, type: 'success' | 'error' | 'warning' | 'info') =>
        toast(message, type),
    }),
    [],
  );

  const rowActions = useMemo(
    () => createInvoiceRowActions(actionHandlers),
    [actionHandlers],
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
          rowActions={rowActions}
          sort={[{ selector: 'createdAt', desc: true }]}
        />
      )}
    </AppDialog>
  );
};
