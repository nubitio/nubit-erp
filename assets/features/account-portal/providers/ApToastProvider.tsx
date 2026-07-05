import { createContext, useCallback, useContext, type ReactNode } from 'react';
import { ToastHost, useAppRuntime, type NotificationType } from '@nubitio/admin';

type ToastTone = NotificationType;

interface ApToastContextValue {
  notify: (message: string, tone?: ToastTone) => void;
}

const ApToastContext = createContext<ApToastContextValue | null>(null);

export function ApToastProvider({ children }: { children: ReactNode }) {
  const { toasts, dismiss, runtime } = useAppRuntime();

  const notify = useCallback(
    (message: string, tone: ToastTone = 'info') => {
      runtime.notify(message, tone, 4000);
    },
    [runtime],
  );

  return (
    <ApToastContext.Provider value={{ notify }}>
      {children}
      <ToastHost toasts={toasts} onDismiss={dismiss} />
    </ApToastContext.Provider>
  );
}

export function useApToast() {
  const context = useContext(ApToastContext);
  if (!context) {
    throw new Error('useApToast must be used within ApToastProvider');
  }
  return context;
}