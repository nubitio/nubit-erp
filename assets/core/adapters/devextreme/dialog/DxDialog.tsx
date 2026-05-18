import './DxDialog.scss';
import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

import { useScreenSize } from '@utils/media-query';
import { useEvents } from '@core/event/EventHook';
import { DialogEventNames } from '@core/event/EventTypes';
import { AppDialog } from '@shared/ui/app-dialog';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';

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
    <AppDialog
      title={title}
      open={visible}
      fullScreen={isXSmall}
      width={width}
      height={options.height ?? 'auto'}
      maxWidth={options.width ?? undefined}
      onClose={() => {
        if (isProcessing) return;
        if (options.onClose) options.onClose();
      }}
      closeOnEscape={false}
      closeOnOutsideClick={false}
      keepMounted
      className={options.fitContent ? 'fit-content' : 'form-popup'}
      footer={
        (options.footerVisible ?? true) ? (
          <div className={`form-popup-buttons-container ${width <= 360 ? 'flex-buttons' : ''}`}>
            <button
              type="button"
              className="app-button app-button--secondary"
              onClick={onCancelClick}
              disabled={isProcessing}
            >
              {options.negativeText ?? t('dialog.buttonCancel')}
            </button>
            <button
              type="button"
              className="app-button app-button--primary dialog-save-button"
              onClick={onSaveClick}
              disabled={isProcessing}
            >
              {isProcessing && <span className="button-indicator" aria-hidden="true" />}
              <span>{options.positiveText ?? t('dialog.buttonSave')}</span>
            </button>
          </div>
        ) : undefined
      }
    >
      {options.children}
      {isProcessing && (
        <div className="crud-dialog__loading" aria-hidden="true">
          <span className="crud-dialog__loading-indicator" />
        </div>
      )}
    </AppDialog>
  );
});

DxDialog.displayName = 'DxDialog';
