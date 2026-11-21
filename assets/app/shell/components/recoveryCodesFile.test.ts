import { describe, expect, it } from 'vitest';
import { recoveryCodesFile } from './recoveryCodesFile';

const issuedAt = new Date('2026-11-21T12:00:00Z');
const codes = ['DA03E-76419', '1DB12-F25AF'];

describe('recoveryCodesFile', () => {
  it('lists every code, one per line, with who they belong to and a warning', () => {
    const { content } = recoveryCodesFile({ username: 'admin', codes, issuedAt });
    const lines = content.split('\n');
    expect(lines).toContain('Usuario: admin');
    expect(lines).toContain('DA03E-76419');
    expect(lines).toContain('1DB12-F25AF');
    expect(content).toContain('una sola vez');
    expect(content).toContain('2026');
  });

  it('names the file after the user, without characters a file system dislikes', () => {
    expect(recoveryCodesFile({ username: 'admin', codes, issuedAt }).filename).toBe(
      'nubit-erp-codigos-recuperacion-admin.txt',
    );
    expect(recoveryCodesFile({ username: 'a/b c', codes, issuedAt }).filename).toBe(
      'nubit-erp-codigos-recuperacion-a_b_c.txt',
    );
  });

  it('still produces a usable name for an empty username', () => {
    expect(recoveryCodesFile({ username: '', codes, issuedAt }).filename).toBe(
      'nubit-erp-codigos-recuperacion-usuario.txt',
    );
  });
});
