import { DateTime } from 'luxon';
import { describe, expect, it } from 'vitest';
import {
  COLLAPSED_COUNT,
  expiryLabel,
  invitationErrorMessage,
  visibleInvitations,
} from './PendingInvitations';

const now = DateTime.fromISO('2026-11-07T12:00:00Z', { zone: 'utc' });

describe('expiryLabel', () => {
  it('counts calendar days, so 8 hours left is "hoy", not "mañana"', () => {
    expect(expiryLabel('2026-11-14T12:00:00Z', now)).toBe('vence en 7 días');
    expect(expiryLabel('2026-11-10T13:00:00Z', now)).toBe('vence en 3 días');
    expect(expiryLabel('2026-11-08T11:00:00Z', now)).toBe('vence mañana');
    expect(expiryLabel('2026-11-07T20:00:00Z', now)).toBe('vence hoy');
  });

  it('never reads as a negative number for an expired or unreadable date', () => {
    expect(expiryLabel('2026-11-01T12:00:00Z', now)).toBe('vence hoy');
    expect(expiryLabel('not a date', now)).toBe('');
  });
});

describe('invitationErrorMessage', () => {
  it('keeps the server message when it is Spanish', () => {
    expect(
      invitationErrorMessage({ data: { message: 'Esa invitación ya no está pendiente.' } }, 'x'),
    ).toBe('Esa invitación ya no está pendiente.');
  });

  it('never shows English or technical text', () => {
    expect(
      invitationErrorMessage({ data: { message: 'Internal Server Error' } }, 'No se pudo.'),
    ).toBe('No se pudo.');
    expect(invitationErrorMessage(undefined, 'No se pudo.')).toBe('No se pudo.');
  });
});

describe('visibleInvitations', () => {
  const items = Array.from({ length: 7 }, (_, index) => index);

  it('shows only the first few until expanded', () => {
    expect(visibleInvitations(items, false)).toHaveLength(COLLAPSED_COUNT);
    expect(visibleInvitations(items, false)).toEqual(items.slice(0, COLLAPSED_COUNT));
    expect(visibleInvitations(items, true)).toEqual(items);
  });

  it('leaves short lists alone', () => {
    expect(visibleInvitations([1, 2], false)).toEqual([1, 2]);
    expect(visibleInvitations([], false)).toEqual([]);
  });
});
