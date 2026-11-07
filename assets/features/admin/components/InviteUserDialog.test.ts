import { describe, expect, it } from 'vitest';
import { inviteErrorMessage } from './InviteUserDialog';

describe('inviteErrorMessage', () => {
  it('shows the server message when it is already Spanish', () => {
    expect(inviteErrorMessage({ data: { message: 'Ese correo ya tiene una cuenta.' } })).toBe(
      'Ese correo ya tiene una cuenta.',
    );
    expect(inviteErrorMessage({ data: { message: 'Escribe un correo válido.' } })).toBe(
      'Escribe un correo válido.',
    );
  });

  it('never leaks an unexpected or English message', () => {
    expect(inviteErrorMessage({ data: { message: 'Internal Server Error' } })).toMatch(
      /No se pudo/,
    );
    expect(inviteErrorMessage(new Error('boom'))).toMatch(/No se pudo/);
  });
});
