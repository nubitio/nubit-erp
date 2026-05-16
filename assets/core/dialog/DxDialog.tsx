import './DxDialog.scss';
import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

import { Popup, ToolbarItem } from 'devextreme-react/popup';
import { useScreenSize } from '../../utils/media-query';
import { Button } from 'devextreme-react';
import { useEvents } from '../event/EventHook';
import { DialogEventNames } from '../event/EventTypes';
import LoadIndicator from 'devextreme-react/load-indicator';
import LoadPanel from 'devextreme-react/load-panel';
import { SharedUtils } from '@shared/lib/SharedUtils';
import { useCoreTranslation } from '../i18n/useCoreTranslation';

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
  /** When provided, controls the popup visibility externally (controlled mode). */
  isOpen?: boolean;
  /** Called when the popup requests to be hidden (controlled mode). */
  onClose?: () => void;
}

export interface DxDialogEvents {
  open: (title?: string) => void;
  close: () => void;
}

export const DxDialog = forwardRef<DxDialogEvents, DxDialogOptions>((options, ref) => {
  const { t } = useCoreTranslation();
  const [dialogId] = useState(() => SharedUtils.generateId('dialog'));
  const [title, setTitle] = useState<string>(options.title ?? t('crudPage.dialogTitleAdd'));
  useEffect(() => {
    if (options.title) {
      setTitle(options.title);
    }
  }, [options.title]);
  const width = options.width ?? 480;

  // Controlled mode: isOpen prop drives visibility; uncontrolled: internal state.
  const isControlled = options.isOpen !== undefined;
  const [internalVisible, setInternalVisible] = useState<boolean>(options.visible ?? false);
  const visible = isControlled ? (options.isOpen ?? false) : internalVisible;

  const [isProcessing, setIsProcessing] = useState<boolean>(false);
  const { isXSmall } = useScreenSize();
  const [on, emit] = useEvents();

  const open = (title?: string) => {
    if (!isControlled) setInternalVisible(true);
    if (!title) return;
    setTitle(title);
  };

  const close = () => {
    if (!isControlled) setInternalVisible(false);
  };

  const onCancelClick = () => {
    close();
    if (options.onClose) options.onClose();
    if (options.events?.CANCEL) emit(options.events.CANCEL);
  };

  const onSaveClick = () => {
    if (options.events?.SAVE) emit(options.events.SAVE);
  };

  const setupObservers = () => {
    if (options.events?.ADD)
      on<{ title?: string } | undefined>(options.events.ADD, (payload) =>
        open(payload?.title ?? t('crudPage.dialogTitleAdd')),
      );

    if (options.events?.EDIT) on(options.events.EDIT, () => open(t('crudPage.dialogTitleEdit')));

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
            return;
          }
          if (options.onClose) options.onClose();
        }}
        wrapperAttr={{ id: dialogId, class: options.fitContent ? 'fit-content' : 'form-popup' }}
      >
        {(options.footerVisible ?? true) && (
          <ToolbarItem toolbar="bottom" location="after">
            <div className={`form-popup-buttons-container ${width <= 360 ? 'flex-buttons' : ''}`}>
              <Button
                text={options.negativeText ?? t('dialog.buttonCancel')}
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
                <span className="dx-button-text">{options.positiveText ?? t('dialog.buttonSave')}</span>
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
