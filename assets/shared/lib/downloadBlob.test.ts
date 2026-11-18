import { describe, expect, it } from 'vitest';
import { filenameFromDisposition } from './downloadBlob';

const disposition = (value: string) => ({ 'content-disposition': value });

describe('filenameFromDisposition', () => {
  it.each([
    ['a quoted name', 'attachment; filename="clientes.xlsx"', 'clientes.xlsx'],
    ['an unquoted name', 'attachment; filename=clientes.xlsx', 'clientes.xlsx'],
    [
      'a name with spaces',
      'attachment; filename="lista de clientes.xlsx"',
      'lista de clientes.xlsx',
    ],
    ['an RFC 5987 name', "attachment; filename*=UTF-8''clientes%20%C3%B1.xlsx", 'clientes ñ.xlsx'],
  ])('reads %s', (_label, value, expected) => {
    expect(filenameFromDisposition(disposition(value), 'x.xlsx')).toBe(expected);
  });

  it('prefers the RFC 5987 form when both are present', () => {
    expect(
      filenameFromDisposition(
        disposition(`attachment; filename="fallback.xlsx"; filename*=UTF-8''real.xlsx`),
        'x.xlsx',
      ),
    ).toBe('real.xlsx');
  });

  it('matches the header name in any case', () => {
    expect(
      filenameFromDisposition({ 'Content-Disposition': 'attachment; filename="a.xlsx"' }, 'x.xlsx'),
    ).toBe('a.xlsx');
  });

  it.each([
    ['no header', {}],
    ['undefined headers', undefined],
    ['an inline disposition', disposition('inline')],
    ['an empty name', disposition('attachment; filename=""')],
  ])('falls back for %s', (_label, headers) => {
    expect(filenameFromDisposition(headers, 'fallback.xlsx')).toBe('fallback.xlsx');
  });

  it('survives a malformed percent-encoding by using the plain name', () => {
    expect(
      filenameFromDisposition(
        disposition(`attachment; filename*=UTF-8''%E0%A4%A; filename="ok.xlsx"`),
        'x.xlsx',
      ),
    ).toBe('ok.xlsx');
  });
});
