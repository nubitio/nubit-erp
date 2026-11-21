import { describe, expect, it } from 'vitest';
import { describeTotp } from './totpStatus';

describe('describeTotp', () => {
  it('reads as off, with an "Activar" action, when nothing is enrolled', () => {
    expect(describeTotp({ enrolled: false, pending: false, recoveryCodesLeft: 0 })).toMatchObject({
      badge: { label: 'Desactivada', tone: 'secondary' },
      action: 'Activar',
    });
  });

  it('an unfinished enrolment is not in force, so it also reads as off', () => {
    expect(
      describeTotp({ enrolled: false, pending: true, recoveryCodesLeft: 10 }).badge.label,
    ).toBe('Desactivada');
  });

  it('reads as on, with a "Administrar" action, once enrolled', () => {
    const row = describeTotp({ enrolled: true, pending: false, recoveryCodesLeft: 10 });
    expect(row.badge).toEqual({ label: 'Activada', tone: 'success' });
    expect(row.action).toBe('Administrar');
    expect(row.description).toContain('10');
  });

  it('warns when few recovery codes are left', () => {
    expect(
      describeTotp({ enrolled: true, pending: false, recoveryCodesLeft: 2 }).description,
    ).toContain('Te quedan 2 códigos');
    expect(
      describeTotp({ enrolled: true, pending: false, recoveryCodesLeft: 1 }).description,
    ).toContain('Te queda 1 código');
    expect(
      describeTotp({ enrolled: true, pending: false, recoveryCodesLeft: 0 }).description,
    ).toContain('Te quedan 0 códigos');
  });
});
