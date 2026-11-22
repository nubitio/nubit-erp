import { describe, expect, it } from 'vitest';
import { parsePriceListOverrides } from './parsePriceListOverrides';

describe('parsePriceListOverrides', () => {
  it('reads a plain array and rounds the four-decimal API price to two', () => {
    const map = parsePriceListOverrides([{ id: 'p1', price: '10.4000' }]);
    expect(map.get('p1')).toBe('10.40');
  });

  it.each(['member', 'hydra:member'])('reads the %s collection envelope', (key) => {
    const map = parsePriceListOverrides({ [key]: [{ id: 7, price: '9.8000' }] });
    expect(map.get('7')).toBe('9.80');
  });

  it('skips rows without a usable price and tolerates an empty body', () => {
    expect(parsePriceListOverrides([{ id: 'p1', price: '' }]).size).toBe(0);
    expect(parsePriceListOverrides(null).size).toBe(0);
  });
});
