import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2ESeed } from './support/e2eSeed';

test('duplicate voucher series is rejected as a validation error', async ({ page }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);

  const result = await page.evaluate(async () => {
    const collectionResponse = await fetch('/api/voucher-series?itemsPerPage=200', {
      headers: { Accept: 'application/ld+json' },
    });
    const collection = await collectionResponse.json();
    const rows = collection.member ?? collection['hydra:member'] ?? [];
    const existing = rows.find(
      (row: { voucherType?: { code?: string }; series?: string }) =>
        row.voucherType?.code === '00' && row.series === 'N001',
    );

    if (!existing) throw new Error('Expected seeded N001 voucher series');

    const response = await fetch('/api/voucher-series', {
      method: 'POST',
      headers: {
        Accept: 'application/ld+json',
        'Content-Type': 'application/ld+json',
      },
      body: JSON.stringify({
        branch: existing.branch['@id'],
        voucherType: existing.voucherType['@id'],
        series: existing.series,
        associatedSeries: existing.associatedSeries,
        isActive: existing.isActive,
      }),
    });

    return { status: response.status, body: await response.json() };
  });

  expect(result.status).toBe(422);
  expect(JSON.stringify(result.body)).toContain('Esta serie ya está registrada');
});
