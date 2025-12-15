import React from 'react';
import { Popup, ToolbarItem } from 'devextreme-react/popup';
import { Button } from 'devextreme-react';

import './SalePrintPreviewPopup.scss';
import { useScreenSize } from '../../utils/media-query';

interface SalePrintPreviewPopupProps {
  saleId: any;
  visible: boolean;
  onPositive: () => void;
  onNegative: () => void;
}

export const SalePrintPreviewPopup = (props: SalePrintPreviewPopupProps) => {
  const { isXSmall } = useScreenSize();

  return (
    <Popup
      title={'Vista previa'}
      showTitle={true}
      fullScreen={isXSmall}
      width="100mm"
      height={600}
      visible={props.visible}
      wrapperAttr={{ class: `form-popup` }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className={`form-popup-buttons-container`}>
          <Button text="Cerrar" stylingMode="outlined" onClick={props.onNegative} />
          <Button text="Ver PDF" stylingMode="outlined" type="default" onClick={props.onPositive} />
        </div>
      </ToolbarItem>
      <iframe
        src={`/receipt/${props.saleId}/ticket?preview=true`}
        width={'100%'}
        height={'100%'}
        style={{ border: 'none' }}
      />
    </Popup>
  );
};
