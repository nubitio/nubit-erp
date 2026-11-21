import { describe, expect, it } from 'vitest';
import { changesToSave, planAdoption, themeSteps } from './preferencesSync';

describe('planAdoption', () => {
  it('applies what the account chose', () => {
    expect(
      planAdoption({ density: 'compact', theme: 'dark' }, { density: 'normal', theme: 'light' }),
    ).toEqual({ apply: { density: 'compact', theme: 'dark' }, offer: {} });
  });

  it("keeps this browser's choice when the account never chose, and offers it", () => {
    expect(
      planAdoption({ density: null, theme: null }, { density: 'compact', theme: 'dark' }),
    ).toEqual({ apply: {}, offer: { density: 'compact', theme: 'dark' } });
  });

  it('mixes both, one preference at a time', () => {
    expect(
      planAdoption({ density: 'compact', theme: null }, { density: 'normal', theme: 'auto' }),
    ).toEqual({ apply: { density: 'compact' }, offer: { theme: 'auto' } });
  });

  it('does nothing when both agree', () => {
    expect(
      planAdoption({ density: 'normal', theme: 'auto' }, { density: 'normal', theme: 'auto' }),
    ).toEqual({ apply: {}, offer: {} });
  });
});

describe('changesToSave', () => {
  it('saves only what differs from the account', () => {
    expect(
      changesToSave({ density: 'compact', theme: 'auto' }, { density: 'normal', theme: 'auto' }),
    ).toEqual({ density: 'compact' });
  });

  it('saves a preference the account never had', () => {
    expect(
      changesToSave({ density: 'normal', theme: 'dark' }, { density: null, theme: 'dark' }),
    ).toEqual({
      density: 'normal',
    });
  });

  it('is empty when nothing changed', () => {
    expect(
      changesToSave({ density: 'normal', theme: 'auto' }, { density: 'normal', theme: 'auto' }),
    ).toEqual({});
  });
});

describe('themeSteps', () => {
  it('counts the steps of the cycle light → dark → auto → light', () => {
    expect(themeSteps('light', 'light')).toBe(0);
    expect(themeSteps('light', 'dark')).toBe(1);
    expect(themeSteps('light', 'auto')).toBe(2);
    expect(themeSteps('dark', 'light')).toBe(2);
    expect(themeSteps('auto', 'light')).toBe(1);
    expect(themeSteps('auto', 'dark')).toBe(2);
  });
});
