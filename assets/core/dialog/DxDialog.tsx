import './DxDialog.scss';
import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

import { Popup, ToolbarItem } from 'devextreme-react/popup';
import { useScreenSize } from '../../utils/media-query';
import { Button } from 'devextreme-react';
import { useEvents } from '../event/EventHook';
import { DialogEventNames } from '../event/EventTypes';
import LoadIndicator from 'devextreme-react/load-indicator';
import LoadPanel from 'devextreme-react/load-panel';
import { SharedUtils } from '../utils/SharedUtils';

export interface DxDialogOptions {
  title?: string;
  width?: number;
  height?: number;
  maxHeight?: number;
  visible?: boolean;
  events?: DialogEventNames;
  children?: React.ReactNode;
  positiveText?: string;
  negativeText?: string;
  footerVisible?: boolean;
  fitContent?: boolean;
}

export interface DxDialogEvents {
  open: (title?: string) => void;
  close: () => void;
}

export const DxDialog = forwardRef<DxDialogEvents, DxDialogOptions>((options, ref) => {
  const dialogIdRef = useRef<string>(SharedUtils.generateId('dialog'));
  const dialogId = dialogIdRef.current;
  const [title, setTitle] = useState<string>(options.title ?? 'Nuevo');
  useEffect(() => {
    if (options.title) {
      setTitle(options.title);
    }
  }, [options.title]);
  const width = options.width ?? 480;
  const [visible, setVisible] = useState<boolean>(options.visible ?? false);
  const [isProcessing, setIsProcessing] = useState<boolean>(false);
  const { isXSmall } = useScreenSize();
  const [on, emit] = useEvents();

  const open = (title?: string) => {
    setVisible(true);
    if (!title) return;
    setTitle(title);
  };

  const close = () => {
    setVisible(false);
  };

  const onCancelClick = () => {
    close();
    if (options.events?.CANCEL) emit(options.events.CANCEL);
  };

  const onSaveClick = () => {
    if (options.events?.SAVE) emit(options.events.SAVE);
  };

  const setupObservers = () => {
    if (options.events?.ADD)
      on<any>(options.events.ADD, (payload) => open(payload?.title ?? 'Nuevo'));

    if (options.events?.EDIT) on(options.events.EDIT, () => open('Editar'));

    if (options.events?.LOADING)
      on<boolean>(options.events.LOADING, (isLoading) => setIsProcessing(isLoading));

    if (options.events?.SUCCESS) on(options.events.SUCCESS, () => close());
  };

  const setupObserversRef = useRef(setupObservers);
  // eslint-disable-next-line react-hooks/refs -- intentional stale-closure fix: keep ref current with latest closure
  setupObserversRef.current = setupObservers;

  useEffect(() => {
    setupObserversRef.current();
  }, []);

  useImperativeHandle(ref, () => ({
    open,
    close,
  }));

  return (
    <React.Fragment>
      <Popup
        title={title}
        visible={visible}
        fullScreen={isXSmall}
        width={width}
        height={options.height ?? 'auto'}
        maxHeight={options.maxHeight ?? '90%'}
        showCloseButton={true}
        dragEnabled={false}
        hideOnOutsideClick={false}
        onHiding={(e) => {
          if (isProcessing) {
            e.cancel = true;
          }
        }}
        wrapperAttr={{ id: dialogId, class: options.fitContent ? 'fit-content' : 'form-popup' }}
      >
        {(options.footerVisible ?? true) && (
          <ToolbarItem toolbar="bottom" location="after">
            <div className={`form-popup-buttons-container ${width <= 360 ? 'flex-buttons' : ''}`}>
              <Button
                text={options.negativeText ?? 'Cancelar'}
                stylingMode="outlined"
                onClick={onCancelClick}
                disabled={isProcessing}
              />
              <Button
                stylingMode="outlined"
                type="default"
                onClick={onSaveClick}
                disabled={isProcessing}
                className="dialog-save-button"
              >
                <LoadIndicator className="button-indicator" visible={isProcessing} />
                <span className="dx-button-text">{options.positiveText ?? 'Guardar'}</span>
              </Button>
            </div>
          </ToolbarItem>
        )}
        {options.children}
        <LoadPanel
          shadingColor="rgba(0,0,0,0.4)"
          position={{ of: `#${dialogId}` }}
          visible={isProcessing}
          showIndicator={true}
          shading={true}
          showPane={true}
          hideOnOutsideClick={false}
        />
      </Popup>
    </React.Fragment>
  );
});

DxDialog.displayName = 'DxDialog';
