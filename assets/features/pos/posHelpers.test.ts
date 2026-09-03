import { describe, expect, it } from 'vitest';

import { formatPosQuantity, toMoneyString } from './posHelpers';

describe('toMoneyString', () => {
  it('normalizes four-decimal API prices for display in the POS', () => {
    expect(toMoneyString('5.0000', '')).toBe('5.00');
  });
});

describe('formatPosQuantity', () => {
  it('does not append weight units for non-weight products', () => {
    expect(formatPosQuantity(2, 'NIU')).toBe('2');
    expect(formatPosQuantity(1.5, 'NIU')).toBe('1.5');
  });

  it('formats kilogram quantities for POS readability', () => {
    expect(formatPosQuantity(0.25, 'KGM')).toBe('250 g');
    expect(formatPosQuantity(1.25, 'KG')).toBe('1.25 kg');
  });

  it('formats gram quantities with grams', () => {
    expect(formatPosQuantity(250, 'GRM')).toBe('250 g');
  });
});
