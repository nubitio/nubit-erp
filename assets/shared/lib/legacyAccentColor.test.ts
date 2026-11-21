import { beforeEach, describe, expect, it } from 'vitest';
import { clearLegacyAccentColor } from './legacyAccentColor';

beforeEach(() => {
  window.localStorage.clear();
  document.documentElement.style.removeProperty('--accent-color');
});

describe('clearLegacyAccentColor', () => {
  it('drops the colour a user picked with the removed accent picker', () => {
    window.localStorage.setItem('app-accent-color', '#e11d48');
    document.documentElement.style.setProperty('--accent-color', '#e11d48');

    clearLegacyAccentColor();

    expect(window.localStorage.getItem('app-accent-color')).toBeNull();
    expect(document.documentElement.style.getPropertyValue('--accent-color')).toBe('');
  });

  it('leaves other preferences alone', () => {
    window.localStorage.setItem('app-density', 'compact');
    clearLegacyAccentColor();
    expect(window.localStorage.getItem('app-density')).toBe('compact');
  });

  it('does nothing when there is no stored colour', () => {
    expect(() => clearLegacyAccentColor()).not.toThrow();
  });
});
