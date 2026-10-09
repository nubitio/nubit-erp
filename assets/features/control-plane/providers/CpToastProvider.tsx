import { useEffect, type ReactNode } from 'react';
import { ToastHost, useAppRuntime, type NotificationType } from '@nubitio/admin';
import { registerCpNotify } from '../utils/notify';

interface CpToastProviderProps {
  children: ReactNode;
}

export const CpToastProvider = ({ children }: CpToastProviderProps) => {
  const { toasts, dismiss, runtime } = useAppRuntime();

  useEffect(() => {
    registerCpNotify((message: string, type: NotificationType = 'info', durationMs = 4000) => {
      runtime.notify(message, type, durationMs);
    });

    return () => registerCpNotify(null);
  }, [runtime]);

  return (
    <>
      {children}
      <ToastHost toasts={toasts} onDismiss={dismiss} />
    </>
  );
};
