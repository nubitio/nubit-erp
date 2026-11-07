import { DateTime } from 'luxon';
import { describe, expect, it } from 'vitest';
import { formatLastLogin } from './userResource';

const now = DateTime.fromISO('2026-11-07T10:00:00', { zone: 'America/Lima' });

describe('formatLastLogin', () => {
  it('shows a dash when the user never signed in', () => {
    expect(formatLastLogin(null, now)).toBe('—');
    expect(formatLastLogin('', now)).toBe('—');
    expect(formatLastLogin('not a date', now)).toBe('—');
  });

  it('uses relative words for today and yesterday, in the viewer zone', () => {
    expect(formatLastLogin('2026-11-07T14:12:00Z', now)).toBe('Hoy, 09:12');
    expect(formatLastLogin('2026-11-07T02:04:00Z', now)).toBe('Ayer, 21:04');
  });

  it('falls back to the date for older sign-ins', () => {
    expect(formatLastLogin('2026-10-12T15:00:00Z', now)).toBe('12/10/2026');
  });
});
