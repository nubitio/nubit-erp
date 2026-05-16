import React from 'react';

// eslint-disable-next-line no-restricted-imports -- SalePrintPreviewPopup is an injection point for AppDialog from the sales feature
import { AppDialog } from '@app/shell/components/AppDialog';
import './SalePrintPreviewPopup.scss';
import { useScreenSize } from '@utils/media-query';

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
      <div className="form-popup-buttons-container">
        <button
          type="button"
          className="app-button app-button--secondary"
          onClick={props.onNegative}
        >
          Cerrar
        </button>
        <button
          type="button"
          className="app-button app-button--primary"
          onClick={props.onPositive}
        >
          Ver PDF
        </button>
      </div>
    </AppDialog>
  );
};
