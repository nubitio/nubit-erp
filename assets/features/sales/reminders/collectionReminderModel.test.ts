import { describe, expect, it } from 'vitest';
import {
  formatWhatsappNumber,
  outcomeMessage,
  overdueLabel,
  reminderErrorInfo,
} from './collectionReminderModel';

describe('overdueLabel', () => {
  it('describes overdue invoices and stays silent otherwise', () => {
    expect(overdueLabel(43)).toBe('43 días vencida');
    expect(overdueLabel(1)).toBe('1 día vencida');
    expect(overdueLabel(0)).toBeNull();
    expect(overdueLabel(-3)).toBeNull();
  });
});

describe('reminderErrorInfo', () => {
  it('uses the API message and flags the 24 h repeat rule', () => {
    expect(
      reminderErrorInfo({
        status: 409,
        data: { error: 'Ya se envió un recordatorio por este canal el 03/11/2026 00:11.' },
      }),
    ).toEqual({
      message: 'Ya se envió un recordatorio por este canal el 03/11/2026 00:11.',
      tooFrequent: true,
    });
  });

  it('does not offer to force other conflicts', () => {
    expect(
      reminderErrorInfo({ status: 409, data: { error: 'La venta no tiene saldo pendiente.' } })
        .tooFrequent,
    ).toBe(false);
  });

  it('keeps validation messages and falls back to a generic one', () => {
    expect(
      reminderErrorInfo({
        status: 422,
        data: { error: 'El cliente no tiene un correo registrado.' },
      }).message,
    ).toBe('El cliente no tiene un correo registrado.');
    expect(reminderErrorInfo(new Error('network')).message).toContain('No se pudo completar');
    expect(reminderErrorInfo(undefined).tooFrequent).toBe(false);
  });
});

describe('outcomeMessage', () => {
  it('says WhatsApp was prepared, never sent', () => {
    const result = outcomeMessage('whatsapp', 'prepared', null);

    expect(result.tone).toBe('success');
    expect(result.text).toContain('preparado');
    expect(result.text).not.toMatch(/enviado/i);
  });

  it('confirms a sent e-mail and reports a failed one as an error', () => {
    expect(outcomeMessage('email', 'sent', null)).toEqual({
      text: 'Correo enviado.',
      tone: 'success',
    });
    expect(outcomeMessage('email', 'failed', 'No se pudo enviar el correo: timeout')).toEqual({
      text: 'No se pudo enviar el correo: timeout',
      tone: 'error',
    });
  });
});

describe('formatWhatsappNumber', () => {
  it('groups Peruvian numbers and keeps others readable', () => {
    expect(formatWhatsappNumber('51920000001')).toBe('+51 920 000 001');
    expect(formatWhatsappNumber('13055550100')).toBe('+13055550100');
    expect(formatWhatsappNumber(null)).toBe('');
  });
});
