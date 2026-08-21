import { describe, expect, it } from 'vitest';
import { generateIdempotencyKey } from './saleSubmitter';

describe('generateIdempotencyKey', () => {
  it('returns a UUID v4 string', () => {
    expect(generateIdempotencyKey()).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );
  });
});
