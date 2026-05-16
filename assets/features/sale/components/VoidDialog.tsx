import React, { useEffect, useRef, useState } from 'react';
import ReactDOM from 'react-dom/client';
// eslint-disable-next-line no-restricted-imports -- VoidDialog is an injection point for AppDialog from the sale feature
import { AppDialog } from '@app/shell/components/AppDialog';
import { useScreenSize } from '@utils/media-query';
import './VoidDialog.scss';

export type VoidDialogResult = {
  reason: string;
  restoreStock: boolean;
};

interface VoidDialogProps {
  onConfirm: (result: VoidDialogResult) => void;
  onCancel?: () => void;
}

const VoidDialogInner = ({ onConfirm, onCancel }: VoidDialogProps) => {
  const { isXSmall } = useScreenSize();
  const [visible, setVisible] = useState(true);
  const [reason, setReason] = useState('');
  const [restoreStock, setRestoreStock] = useState(false);
  const [reasonError, setReasonError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const handleConfirm = () => {
    if (!reason.trim()) {
      setReasonError('El motivo es obligatorio');
      return;
    }
    onConfirm({ reason: reason.trim(), restoreStock });
    setVisible(false);
  };

  const handleCancel = () => {
    onCancel?.();
    setVisible(false);
  };

  return (
    <AppDialog
      title="Anular comprobante"
      fullScreen={isXSmall}
      width={isXSmall ? undefined : 420}
      visible={visible}
      showCloseButton={false}
      closeOnOutsideClick={false}
      closeOnEscape
      onClose={handleCancel}
      className="void-dialog"
    >
      <div className="void-dialog__body">
        {/* Motivo */}
        <div className={`void-field${reasonError ? ' void-field--error' : ''}`}>
          <label className="void-field__label" htmlFor="void-reason">
            Motivo de anulación
          </label>
          <input
            ref={inputRef}
            id="void-reason"
            type="text"
            className="void-input"
            placeholder="Escribe el motivo..."
            aria-invalid={!!reasonError}
            aria-describedby={reasonError ? 'void-reason-error' : undefined}
            value={reason}
            onChange={(e) => {
              setReason(e.target.value);
              if (reasonError) setReasonError(null);
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter') handleConfirm();
            }}
          />
          {reasonError && (
            <span id="void-reason-error" className="void-field__error" role="alert">
              {reasonError}
            </span>
          )}
        </div>

        {/* Restaurar stock */}
        <label className="void-checkbox">
          <input
            type="checkbox"
            checked={restoreStock}
            onChange={(e) => setRestoreStock(e.target.checked)}
          />
          <span>Restaurar stock al anular</span>
        </label>
      </div>

      <div className="form-popup-buttons-container">
        <button
          type="button"
          className="app-button app-button--secondary"
          onClick={handleCancel}
        >
          Cancelar
        </button>
        <button
          type="button"
          className="app-button app-button--primary"
          disabled={!reason.trim()}
          onClick={handleConfirm}
        >
          Confirmar
        </button>
      </div>
    </AppDialog>
  );
};

/** Imperative helper — resolves with the result or null if cancelled. */
export const voidPrompt = (): Promise<VoidDialogResult | null> =>
  new Promise((resolve) => {
    const container = document.createElement('div');
    document.body.appendChild(container);
    const root = ReactDOM.createRoot(container);

    const cleanup = () => {
      root.unmount();
      container.remove();
    };

    root.render(
      <VoidDialogInner
        onConfirm={(result) => {
          resolve(result);
          cleanup();
        }}
        onCancel={() => {
          resolve(null);
          cleanup();
        }}
      />,
    );
  });
