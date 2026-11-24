import { describe, expect, it } from 'vitest';
import { closureOutcome, closureText, formatSignedMoney, shiftCode } from './format';

// Intl writes the currency with a no-break space.
const plain = (text: string) => text.replace(/\u00a0/g, ' ');

describe('cash formats', () => {
  it('pads the shift code', () => {
    expect(shiftCode(481)).toBe('T-0481');
    expect(shiftCode('12345')).toBe('T-12345');
  });

  it('writes negative money with a minus sign in front of the currency', () => {
    expect(plain(formatSignedMoney(-12.5))).toBe('−S/ 12.50');
    expect(plain(formatSignedMoney(12.5))).toBe('S/ 12.50');
  });

  it('reads how a closing turned out', () => {
    expect(closureOutcome('0.00')).toBe('balanced');
    expect(closureOutcome('-5.00')).toBe('shortage');
    expect(closureOutcome('3.10')).toBe('overage');
    expect(closureOutcome(null)).toBe('uncounted');
    expect(closureText('0.00')).toBe('cuadrado, sin diferencia');
    expect(plain(closureText('-5.00'))).toBe('faltante −S/ 5.00');
    expect(closureText(null)).toBe('sin arqueo registrado');
  });
});
