import type { ReminderChannel, ReminderStatus, ReminderTone } from './collectionReminderService';

export const CHANNEL_OPTIONS: { value: ReminderChannel; label: string }[] = [
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'Correo' },
];

export const TONE_OPTIONS: { value: ReminderTone; label: string }[] = [
  { value: 'friendly', label: 'Amable' },
  { value: 'direct', label: 'Directo' },
  { value: 'final', label: 'Último aviso' },
];

export interface ReminderErrorInfo {
  message: string;
  /** The API refused a repeat within 24 h; offer to send it anyway. */
  tooFrequent: boolean;
}

/** Reads the API's `{ error, lastReminderAt }` body (or a network failure) into dialog text. */
export function reminderErrorInfo(error: unknown): ReminderErrorInfo {
  const failure = error as { status?: number; data?: { error?: unknown }; message?: unknown };
  const apiMessage = typeof failure?.data?.error === 'string' ? failure.data.error : null;

  return {
    message: apiMessage ?? 'No se pudo completar el recordatorio. Inténtalo de nuevo.',
    tooFrequent: failure?.status === 409 && apiMessage !== null && /recordatorio/i.test(apiMessage),
  };
}

/** "43 días vencida", "1 día vencida" or null when it is not overdue yet. */
export function overdueLabel(daysOverdue: number): string | null {
  if (daysOverdue <= 0) return null;

  return daysOverdue === 1 ? '1 día vencida' : `${daysOverdue} días vencida`;
}

/** What to tell the cashier once a reminder was recorded. */
export function outcomeMessage(
  channel: ReminderChannel,
  status: ReminderStatus,
  failureReason: string | null,
): { text: string; tone: 'success' | 'error' } {
  if (status === 'failed') {
    return { text: failureReason ?? 'No se pudo enviar el correo.', tone: 'error' };
  }
  if (channel === 'whatsapp') {
    return {
      text: 'Se abrió WhatsApp con el mensaje listo. Queda registrado como preparado.',
      tone: 'success',
    };
  }

  return { text: 'Correo enviado.', tone: 'success' };
}

/** "51920000001" -> "+51 920 000 001"; other lengths are shown as "+digits". */
export function formatWhatsappNumber(digits: string | null): string {
  if (!digits) return '';
  const match = /^51(\d{3})(\d{3})(\d{3})$/.exec(digits);

  return match ? `+51 ${match[1]} ${match[2]} ${match[3]}` : `+${digits}`;
}
