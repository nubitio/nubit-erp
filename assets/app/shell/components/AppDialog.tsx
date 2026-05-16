import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

import './AppDialog.scss';

interface AppDialogProps {
  visible: boolean;
  title: string;
  onClose: () => void;
  children: React.ReactNode;
  width?: number | string;
  height?: number | string;
  fullScreen?: boolean;
  showCloseButton?: boolean;
  closeOnOutsideClick?: boolean;
  closeOnEscape?: boolean;
  className?: string;
}

const formatSize = (value?: number | string) => {
  if (value === undefined) return undefined;
  return typeof value === 'number' ? `${value}px` : value;
};

export const AppDialog = ({
  visible,
  title,
  onClose,
  children,
  width,
  height,
  fullScreen = false,
  showCloseButton = true,
  closeOnOutsideClick = true,
  closeOnEscape = true,
  className,
}: AppDialogProps) => {
  const dialogRef = useRef<HTMLDivElement | null>(null);
  const lastFocusedElementRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!visible) return;

    lastFocusedElementRef.current = document.activeElement as HTMLElement | null;
    window.setTimeout(() => {
      const focusable = dialogRef.current?.querySelector<HTMLElement>(
        'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
      );
      focusable?.focus();
    });

    return () => {
      lastFocusedElementRef.current?.focus?.();
    };
  }, [visible]);

  useEffect(() => {
    if (!visible || !closeOnEscape) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [closeOnEscape, onClose, visible]);

  if (!visible) return null;

  return createPortal(
    <div className="app-dialog-root">
      <button
        className="app-dialog__scrim"
        type="button"
        aria-label="Cerrar diálogo"
        onClick={closeOnOutsideClick ? onClose : undefined}
      />
      <div
        className={[
          'app-dialog',
          fullScreen ? 'app-dialog--fullscreen' : '',
          className,
        ].filter(Boolean).join(' ')}
        role="dialog"
        aria-modal="true"
        aria-labelledby="app-dialog-title"
        ref={dialogRef}
        style={{ width: formatSize(width), height: formatSize(height) }}
      >
        <div className="app-dialog__header">
          <h2 className="app-dialog__title" id="app-dialog-title">{title}</h2>
          {showCloseButton && (
            <button className="app-icon-button app-dialog__close" type="button" aria-label="Cerrar" onClick={onClose}>
              <i className="ph ph-x" aria-hidden="true" />
            </button>
          )}
        </div>
        <div className="app-dialog__body">{children}</div>
      </div>
    </div>,
    document.body,
  );
};
