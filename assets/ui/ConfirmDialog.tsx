import { useTranslation } from 'react-i18next';
import { AppDialog } from './AppDialog';
import './ConfirmDialog.scss';

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
  confirmLabel,
  cancelLabel,
  variant = 'warning',
}: ConfirmDialogProps) {
  const { t } = useTranslation();
  const resolvedConfirmLabel = confirmLabel ?? t('common.confirm');
  const resolvedCancelLabel = cancelLabel ?? t('common.cancel');
  const confirmClass = variant === 'danger'
    ? 'app-button app-button--danger'
    : 'app-button app-button--primary';

  return (
    <AppDialog
      open={open}
      title={title}
      onClose={onCancel}
      maxWidth={420}
      role="alertdialog"
      footer={(
        <>
          <button
            type="button"
            className="app-button app-button--secondary"
            onClick={onCancel}
            autoFocus
          >
            {resolvedCancelLabel}
          </button>
          <button type="button" className={confirmClass} onClick={onConfirm}>
            {resolvedConfirmLabel}
          </button>
        </>
      )}
    >
      <p className="confirm-dialog__message">{message}</p>
    </AppDialog>
  );
}
