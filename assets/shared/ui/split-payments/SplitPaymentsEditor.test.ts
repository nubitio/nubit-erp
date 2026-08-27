import { describe, expect, it } from 'vitest';
import { buildTenderSuggestions } from './SplitPaymentsEditor';

describe('buildTenderSuggestions', () => {
  it('offers every Peruvian banknote for a small cash total', () => {
    expect(buildTenderSuggestions(0.7)).toEqual([0.7, 10, 20, 50, 100, 200]);
  });

  it('does not offer banknotes below the amount due', () => {
    expect(buildTenderSuggestions(120)).toEqual([120, 200]);
  });
});
