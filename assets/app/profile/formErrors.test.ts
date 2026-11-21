import { describe, expect, it } from 'vitest';
import { violationsOf } from './formErrors';

const error = (violations: unknown) => ({ status: 422, data: { violations } });

describe('violationsOf', () => {
  it('places each message under its field', () => {
    const result = violationsOf(
      error([
        { propertyPath: 'phone', message: 'El teléfono ingresado no es válido.' },
        { propertyPath: 'email', message: 'Ya existe un usuario con este correo.' },
      ]),
      ['phone', 'email'],
    );
    expect(result.fields).toEqual({
      phone: 'El teléfono ingresado no es válido.',
      email: 'Ya existe un usuario con este correo.',
    });
    expect(result.general).toBeNull();
  });

  it('keeps messages for unknown fields so they are not lost', () => {
    const result = violationsOf(
      error([{ propertyPath: 'preferences.theme', message: 'El tema no es válido.' }]),
      ['phone'],
    );
    expect(result.fields).toEqual({});
    expect(result.general).toBe('El tema no es válido.');
  });

  it('ignores failures that are not validation errors', () => {
    expect(violationsOf(new Error('network'), ['phone'])).toEqual({ fields: {}, general: null });
    expect(violationsOf({ status: 500, data: { detail: 'x' } }, ['phone'])).toEqual({
      fields: {},
      general: null,
    });
  });
});
