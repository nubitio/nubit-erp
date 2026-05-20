import './CrudDialogView.scss';
import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';

import { AppDialog } from '@core/ui/app-dialog';
import { useEvents } from '@core/event/EventHook';
import { DialogEventNames } from '@core/event/EventTypes';
import { useCoreTranslation } from '@core/i18n/useCoreTranslation';
import { useScreenSize } from '@utils/media-query';

export interface CrudDialogViewOptions {
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
  isOpen?: boolean;
  onClose?: () => void;
}

export interface CrudDialogViewEvents {
  open: (title?: string) => void;
  close: () => void;
}

export const CrudDialogView = forwardRef<CrudDialogViewEvents, CrudDialogViewOptions>(
  (options, ref) => {
    const { t } = useCoreTranslation();
    const [title, setTitle] = useState<string>(options.title ?? t('crudPage.dialogTitleAdd'));
    const isControlled = options.isOpen !== undefined;
    const [internalVisible, setInternalVisible] = useState<boolean>(options.visible ?? false);
    const visible = isControlled ? (options.isOpen ?? false) : internalVisible;
    const [isProcessing, setIsProcessing] = useState<boolean>(false);
    const { isXSmall } = useScreenSize();
    const [on, emit] = useEvents();
    const width = options.width ?? 480;

    useEffect(() => {
      if (options.title) {
        setTitle(options.title);
      }
    }, [options.title]);

    const open = (nextTitle?: string) => {
      if (!isControlled) setInternalVisible(true);
      if (nextTitle) setTitle(nextTitle);
    };

    const close = () => {
      if (isProcessing) return;
      if (!isControlled) setInternalVisible(false);
    };

    const handleClose = () => {
      if (isProcessing) return;
      close();
      options.onClose?.();
    };

    const onCancelClick = () => {
      handleClose();
      if (options.events?.CANCEL) emit(options.events.CANCEL);
    };

    const onSaveClick = () => {
      if (options.events?.SAVE) emit(options.events.SAVE);
    };

    const setupObservers = () => {
      if (options.events?.ADD) {
        on<{ title?: string } | undefined>(options.events.ADD, (payload) =>
          open(payload?.title ?? t('crudPage.dialogTitleAdd')),
        );
      }
      if (options.events?.EDIT) on(options.events.EDIT, () => open(t('crudPage.dialogTitleEdit')));
      if (options.events?.LOADING) {
        on<boolean>(options.events.LOADING, (isLoading) => setIsProcessing(isLoading));
      }
      if (options.events?.SUCCESS) on(options.events.SUCCESS, () => close());
    };

    const setupObserversRef = useRef(setupObservers);
    // eslint-disable-next-line react-hooks/refs -- keep event subscriptions using latest options without resubscribing
    setupObserversRef.current = setupObservers;

    useEffect(() => {
      setupObserversRef.current();
    }, []);

    useImperativeHandle(ref, () => ({ open, close }));

    return (
      <AppDialog
        title={title}
        open={visible}
        fullScreen={isXSmall}
        width={width}
        height={options.height ?? 'auto'}
        maxWidth={options.width ?? undefined}
        onClose={handleClose}
        closeOnEscape={false}
        closeOnOutsideClick={false}
        keepMounted
        rootClassName="crud-dialog-root"
        className={['crud-dialog', options.fitContent ? 'fit-content' : 'form-popup'].join(' ')}
        headerClassName="crud-dialog__header"
        titleClassName="crud-dialog__title"
        bodyClassName="crud-dialog__body"
        footerClassName="crud-dialog__footer"
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
  },
);

CrudDialogView.displayName = 'CrudDialogView';
