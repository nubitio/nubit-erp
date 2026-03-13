import React, { useEffect, useRef } from 'react';
import { Popup, ToolbarItem } from 'devextreme-react/popup';
import { Button } from 'devextreme-react/button';

export interface ConfirmDialogProps {
  open: boolean;
  title: string;
  message: string;
  onConfirm: () => void;
  onCancel: () => void;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'danger' | 'warning' | 'info';
}

export function ConfirmDialog({
  open,
  title,
  message,
  onConfirm,
  onCancel,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar',
  variant = 'warning',
}: ConfirmDialogProps) {
  const cancelBtnRef = useRef<HTMLButtonElement | null>(null);

  // Move focus to the Cancel button when the dialog opens (safer default)
  useEffect(() => {
    if (open) {
      // Allow DevExtreme to finish rendering before focusing
      const id = setTimeout(() => cancelBtnRef.current?.focus(), 50);
      return () => clearTimeout(id);
    }
  }, [open]);

  const confirmType = variant === 'danger' ? 'danger' : variant === 'info' ? 'default' : 'normal';

  return (
    <Popup
      visible={open}
      title={title}
      dragEnabled={false}
      hideOnOutsideClick={false}
      showCloseButton={false}
      onHiding={onCancel}
      maxWidth={420}
      height="auto"
      wrapperAttr={{
        role: 'alertdialog',
        'aria-modal': 'true',
        'aria-labelledby': 'confirm-dialog-title',
        'aria-describedby': 'confirm-dialog-message',
      }}
    >
      {/* Accessible IDs for screen readers */}
      <span id="confirm-dialog-title" className="dx-popup-title" style={{ display: 'none' }}>
        {title}
      </span>

      <p id="confirm-dialog-message" style={{ margin: '0.5rem 0 1rem', lineHeight: 1.5 }}>
        {message}
      </p>

      <ToolbarItem toolbar="bottom" location="before">
        <button
          ref={cancelBtnRef}
          type="button"
          className="dx-button dx-button-normal dx-button-mode-outlined"
          onClick={onCancel}
          aria-label={cancelLabel}
          style={{ minWidth: 100 }}
        >
          <span className="dx-button-text">{cancelLabel}</span>
        </button>
      </ToolbarItem>

      <ToolbarItem toolbar="bottom" location="after">
        <Button
          text={confirmLabel}
          type={confirmType}
          stylingMode="contained"
          onClick={onConfirm}
        />
      </ToolbarItem>
    </Popup>
  );
}
