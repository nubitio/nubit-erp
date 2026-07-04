import toast from '@shared/lib/SharedUtils';

export const cpNotify = {
  success: (message: string) => toast(message, 'success', 3500),
  error: (message: string) => toast(message, 'error', 5000),
  info: (message: string) => toast(message, 'info', 3500),
};