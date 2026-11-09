import { describe, expect, it } from 'vitest';
import { describeIdentityError, validateNewPassword } from './identityErrors';

describe('validateNewPassword', () => {
  it('requires the server minimum and a matching confirmation', () => {
    expect(validateNewPassword('short', 'short')).toMatch(/al menos 8/);
    expect(validateNewPassword('long-enough-1', 'different-1')).toMatch(/no coinciden/);
    expect(validateNewPassword('long-enough-1', 'long-enough-1')).toBeNull();
  });
});

describe('describeIdentityError', () => {
  it('translates known server messages and never leaks English to the user', () => {
    expect(
      describeIdentityError(
        { data: { message: 'This invitation is no longer valid. Ask for a new one.' } },
        'x',
      ),
    ).toMatch(/venció/);
    expect(
      describeIdentityError({ data: { message: 'Passwords must be at least 8 characters.' } }, 'x'),
    ).toMatch(/al menos 8/);
    expect(describeIdentityError({ data: { message: 'Token expired' } }, 'x')).toMatch(/enlace/);
  });

  it('falls back when there is nothing to translate', () => {
    expect(describeIdentityError(new Error('boom'), 'Algo salió mal')).toBe('Algo salió mal');
    expect(describeIdentityError({ data: { message: 'Unmapped thing' } }, 'Algo salió mal')).toBe(
      'Algo salió mal',
    );
  });
});
