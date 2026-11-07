import { describe, expect, it } from 'vitest';
import { groupSecret } from './TwoFactorModal';

describe('groupSecret', () => {
  it('splits the key in blocks of four so it is easy to type into an app', () => {
    expect(groupSecret('JBSWY3DPEHPK3PXP')).toBe('JBSW Y3DP EHPK 3PXP');
  });

  it('copes with spaces and short keys', () => {
    expect(groupSecret('JBSW Y3DP')).toBe('JBSW Y3DP');
    expect(groupSecret('ABC')).toBe('ABC');
  });
});
