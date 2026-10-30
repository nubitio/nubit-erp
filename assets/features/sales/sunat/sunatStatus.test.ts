import { describe, expect, it } from 'vitest';
import { sunatStatus } from './sunatStatus';

describe('SUNAT actions follow persisted status ranges', () => {
  it.each([
    ['0', 'accepted'],
    ['accepted', 'accepted'],
    ['1999', 'error'],
    ['2000', 'rejected'],
    ['3999', 'rejected'],
    ['4000', 'observed'],
    ['sent', 'sent'],
    [null, 'pending'],
  ])('classifies %s as %s', (code, stage) => {
    expect(sunatStatus(code).stage).toBe(stage);
  });
  it('only replaces explicit numeric rejection codes', () => {
    for (const code of ['2000', '3999']) expect(sunatStatus(code).canReplace).toBe(true);
    for (const code of ['rejected', '0', '1999', '4000', 'sent', ''])
      expect(sunatStatus(code).canReplace).toBe(false);
  });
  it('does not offer another send for answered or in-flight documents', () => {
    for (const code of ['0', '2000', '4000', 'sent']) expect(sunatStatus(code).canSend).toBe(false);
    expect(sunatStatus(null).canSend).toBe(true);
    expect(sunatStatus('0199').canSend).toBe(true);
  });
});
