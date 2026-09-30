import { describe, expect, it } from 'vitest';
import { getCheckoutIdempotencyKey } from './checkoutIdempotency';

describe('restaurant checkout idempotency', () => {
  it('derives the same UUID v5 for the same order on separate clients', async () => {
    const clientOneKey = await getCheckoutIdempotencyKey('42');
    const clientTwoKey = await getCheckoutIdempotencyKey('42');

    expect(clientOneKey).toBe(clientTwoKey);
    expect(clientOneKey).toBe('6e94f168-8497-5d1f-b411-ea85f4e4dc79');
    expect(clientOneKey).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );
  });

  it('derives different keys for different order ids', async () => {
    await expect(getCheckoutIdempotencyKey('42')).resolves.not.toBe(
      await getCheckoutIdempotencyKey('43'),
    );
  });
});
