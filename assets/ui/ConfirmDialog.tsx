import { useTranslation } from 'react-i18next';
import { AppDialog } from './AppDialog';
import { Button } from './Button';
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

  return (
    <AppDialog
      open={open}
      title={title}
      onClose={onCancel}
      maxWidth={420}
      role="alertdialog"
      footer={(
        <>
          <Button
            variant="secondary"
            onClick={onCancel}
            autoFocus
          >
            {resolvedCancelLabel}
          </Button>
          <Button variant={variant === 'danger' ? 'danger' : 'primary'} onClick={onConfirm}>
            {resolvedConfirmLabel}
          </Button>
        </>
      )}
    >
      <p className="confirm-dialog__message">{message}</p>
    </AppDialog>
  );
}
