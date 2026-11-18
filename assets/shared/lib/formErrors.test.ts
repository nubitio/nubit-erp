import { describe, expect, it, vi } from 'vitest';
import { applyServerErrors, GENERIC_SAVE_ERROR } from './formErrors';

type Values = { ruc: string; businessName: string; email: string };
const FIELDS = ['ruc', 'businessName', 'email'] as const;
const violation = (propertyPath: string, message: string) => ({ propertyPath, message });
const failure = (data: unknown) => Object.assign(new Error('Unprocessable'), { status: 422, data });

describe('applyServerErrors', () => {
  it('puts each violation on its field as a server error', () => {
    const setError = vi.fn();

    const outcome = applyServerErrors<Values>(
      failure({ violations: [violation('businessName', 'Ya existe un cliente con este nombre.')] }),
      setError,
      FIELDS,
    );

    expect(setError).toHaveBeenCalledWith(
      'businessName',
      { type: 'server', message: 'Ya existe un cliente con este nombre.' },
      { shouldFocus: true },
    );
    expect(outcome).toEqual({ formError: null });
  });

  it('focuses only the first field', () => {
    const setError = vi.fn();

    applyServerErrors<Values>(
      failure({ violations: [violation('ruc', 'a'), violation('email', 'b')] }),
      setError,
      FIELDS,
    );

    expect(setError.mock.calls.map((c) => c[2])).toEqual([
      { shouldFocus: true },
      { shouldFocus: false },
    ]);
  });

  it('returns violations on fields the form does not have as a form error, not nothing', () => {
    const setError = vi.fn();

    const outcome = applyServerErrors<Values>(
      failure({
        violations: [violation('creditLimit', 'Límite inválido'), violation('ruc', 'RUC en uso')],
      }),
      setError,
      FIELDS,
    );

    expect(setError).toHaveBeenCalledTimes(1);
    expect(outcome.formError).toBe('Límite inválido');
  });

  it('keeps violations with no property path as a form error', () => {
    const outcome = applyServerErrors<Values>(
      failure({ violations: [violation('', 'Error general del servidor')] }),
      vi.fn(),
      FIELDS,
    );

    expect(outcome.formError).toBe('Error general del servidor');
  });

  it.each([
    [
      'detail',
      { detail: 'No se puede editar un cliente con ventas.' },
      'No se puede editar un cliente con ventas.',
    ],
    ['message', { message: 'Conflicto de datos' }, 'Conflicto de datos'],
    ['title', { title: 'An error occurred' }, 'An error occurred'],
  ])('uses the server %s when there are no violations', (_k, data, expected) => {
    const setError = vi.fn();

    expect(applyServerErrors<Values>(failure(data), setError, FIELDS).formError).toBe(expected);
    expect(setError).not.toHaveBeenCalled();
  });

  it('hides the server detail on a 5xx, which can carry internals', () => {
    const error = Object.assign(new Error('Server error'), {
      status: 500,
      data: { detail: 'php_network_getaddresses: getaddrinfo for redis failed' },
    });

    expect(applyServerErrors<Values>(error, vi.fn(), FIELDS).formError).toBe(GENERIC_SAVE_ERROR);
  });

  it('still shows the detail of a 4xx domain error', () => {
    const error = Object.assign(new Error('Conflict'), {
      status: 409,
      data: { detail: 'El cliente tiene ventas.' },
    });

    expect(applyServerErrors<Values>(error, vi.fn(), FIELDS).formError).toBe(
      'El cliente tiene ventas.',
    );
  });

  it.each([
    ['a network error', new Error('Failed to fetch')],
    ['an empty body', failure({})],
    ['no data at all', failure(undefined)],
    ['null', null],
  ])('falls back to a generic message for %s', (_k, error) => {
    expect(applyServerErrors<Values>(error, vi.fn(), FIELDS).formError).toBe(GENERIC_SAVE_ERROR);
  });

  it('normalises nested paths the way the rest of the app does (a form for one record, no rows)', () => {
    const setError = vi.fn();

    applyServerErrors<Values>(
      failure({ violations: [violation('children[ruc].data', 'x')] }),
      setError,
      FIELDS,
    );

    expect(setError).toHaveBeenCalledWith('ruc', expect.anything(), expect.anything());
  });
});
