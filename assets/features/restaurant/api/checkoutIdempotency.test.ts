import { beforeEach, describe, expect, it } from 'vitest';
import { clearCheckoutIdempotencyKey, getCheckoutIdempotencyKey } from './checkoutIdempotency';

describe('restaurant checkout idempotency', () => {
  beforeEach(() => localStorage.clear());

  it('reuses the key for retries of the same order', () => {
    const firstKey = getCheckoutIdempotencyKey('order-1');

    expect(getCheckoutIdempotencyKey('order-1')).toBe(firstKey);
    expect(firstKey).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );
  });

  it('uses different keys for different orders', () => {
    expect(getCheckoutIdempotencyKey('order-1')).not.toBe(getCheckoutIdempotencyKey('order-2'));
  });

  it('clears the key when checkout is complete', () => {
    const firstKey = getCheckoutIdempotencyKey('order-1');

    clearCheckoutIdempotencyKey('order-1');

    expect(getCheckoutIdempotencyKey('order-1')).not.toBe(firstKey);
  });
});
