import type { NotificationType } from '@nubitio/admin';

type NotifyFn = (message: string, type?: NotificationType, durationMs?: number) => void;

let notifyImpl: NotifyFn | null = null;

export const registerCpNotify = (fn: NotifyFn | null) => {
  notifyImpl = fn;
};

const emit = (message: string, type: NotificationType, durationMs: number) => {
  if (notifyImpl) {
    notifyImpl(message, type, durationMs);
    return;
  }

  // Fallback before provider mounts (e.g. tests).
  if (typeof document !== 'undefined') {
    console.info(`[cp-notify:${type}] ${message}`);
  }
};

export const cpNotify = {
  success: (message: string) => emit(message, 'success', 3500),
  error: (message: string) => emit(message, 'error', 5000),
  info: (message: string) => emit(message, 'info', 3500),
  warning: (message: string) => emit(message, 'warning', 4000),
};