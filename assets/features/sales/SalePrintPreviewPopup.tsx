import React from 'react';

import { AppDialog, Button } from '@nubitio/ui';
import './SalePrintPreviewPopup.scss';
import { useScreenSize } from '@nubitio/admin';

interface SalePrintPreviewPopupProps {
  saleId: any;
  visible: boolean;
  onPositive: () => void;
  onNegative: () => void;
}

export const SalePrintPreviewPopup = (props: SalePrintPreviewPopupProps) => {
  const { isXSmall } = useScreenSize();

  return (
    <AppDialog
      title="Vista previa"
      width={isXSmall ? undefined : '90mm'}
      height={isXSmall ? undefined : 650}
      fullScreen={isXSmall}
      visible={props.visible}
      showCloseButton={false}
      closeOnOutsideClick={false}
      closeOnEscape={false}
      onClose={props.onNegative}
      className="sale-print-preview-popup"
      footer={
        <>
          <Button
            variant="secondary"
            onClick={props.onNegative}
          >
            Cerrar
          </Button>
          <Button variant="primary" onClick={props.onPositive}>
            Ver PDF
          </Button>
        </>
      }
    >
      <div className="sale-print-preview-body">
        <iframe
          src={`/receipt/${props.saleId}/ticket?preview=true`}
          width="100%"
          height="100%"
          style={{ border: 'none', display: 'block' }}
          title="Vista previa de comprobante"
        />
      </div>
    </AppDialog>
  );
};
