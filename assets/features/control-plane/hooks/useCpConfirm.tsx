import { useCallback, useState } from 'react';
import { ConfirmDialog } from '@nubitio/ui';
import { CP_STRINGS } from '../i18n/es';

export type CpConfirmRequest = {
  title: string;
  message: string;
  variant?: 'danger' | 'warning' | 'info';
  confirmLabel?: string;
  onConfirm: () => void;
};

export const useCpConfirm = () => {
  const [pending, setPending] = useState<CpConfirmRequest | null>(null);

  const ask = useCallback((request: CpConfirmRequest) => {
    setPending(request);
  }, []);

  const dismiss = useCallback(() => setPending(null), []);

  const ConfirmHost = () => (
    <ConfirmDialog
      open={pending !== null}
      title={pending?.title ?? ''}
      message={pending?.message ?? ''}
      variant={pending?.variant}
      confirmLabel={pending?.confirmLabel ?? CP_STRINGS.confirm}
      cancelLabel={CP_STRINGS.cancel}
      onCancel={dismiss}
      onConfirm={() => {
        pending?.onConfirm();
        dismiss();
      }}
    />
  );

  return { ask, ConfirmHost };
};