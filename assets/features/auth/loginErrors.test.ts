import { describe, expect, it } from 'vitest';
import {
  classifyLoginError,
  SECOND_FACTOR_ENROLMENT_REQUIRED,
  SECOND_FACTOR_REQUIRED,
} from './loginErrors';

describe('classifyLoginError', () => {
  it('asks for the code only for the exact "second factor required" answer', () => {
    expect(classifyLoginError(SECOND_FACTOR_REQUIRED).kind).toBe('needs-code');
    expect(classifyLoginError('Invalid credentials').kind).toBe('other');
    expect(classifyLoginError(undefined).kind).toBe('other');
    expect(classifyLoginError('second factor required').kind).toBe('other');
  });

  it('explains mandatory enrolment instead of leaving the user at a dead end', () => {
    const result = classifyLoginError(SECOND_FACTOR_ENROLMENT_REQUIRED);
    expect(result.kind).toBe('enrolment-required');
    expect(result.text).toMatch(/administrador/);
  });

  it('translates code failures and keeps the user on the code step', () => {
    expect(classifyLoginError('That code is not valid.')).toMatchObject({ kind: 'bad-code' });
    expect(
      classifyLoginError('That code has already been used. Wait for the next one.').text,
    ).toMatch(/ya se usó/);
  });

  it('translates every message the second-factor endpoints can return', () => {
    const messages = [
      'That code is not valid.',
      'That code is not valid. Check the clock on your device and try again.',
      'That code has already been used. Wait for the next one.',
      'This account already has a second factor; remove it before enrolling again.',
      'Start enrolment before confirming it.',
      'This second factor is already confirmed.',
      'This account has no confirmed second factor.',
    ];
    for (const message of messages) {
      const { text } = classifyLoginError(message);
      expect(text, message).toBeDefined();
      expect(text).not.toMatch(/\b(code|account|factor)\b/i);
    }
  });
});
