// eslint-disable-next-line no-restricted-imports
import { AppDialog } from '@app/shell/components/AppDialog';
import { useTranslation } from 'react-i18next';

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

  const confirmClass =
    variant === 'danger'
      ? 'app-button app-button--danger'
      : variant === 'info'
        ? 'app-button app-button--primary'
        : 'app-button app-button--primary';

  return (
    <AppDialog
      open={open}
      title={title}
      onClose={onCancel}
      maxWidth={420}
      role="alertdialog"
      footer={
        <>
          <button type="button" className="app-button app-button--secondary" onClick={onCancel} autoFocus>
            {resolvedCancelLabel}
          </button>
          <button type="button" className={confirmClass} onClick={onConfirm}>
            {resolvedConfirmLabel}
          </button>
        </>
      }
    >
      <p style={{ margin: '0.5rem 0 1rem', lineHeight: 1.5 }}>{message}</p>
    </AppDialog>
  );
}
