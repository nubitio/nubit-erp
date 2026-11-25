import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('a series created from Ajustes appears in the series list', async ({ page }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/config/series`);

  const settings = page.getByRole('dialog', { name: 'Ajustes' });
  await expect(settings.getByRole('heading', { name: 'Series', exact: true })).toBeVisible();
  const list = settings.locator('.settings-group');
  const createButton = settings.getByRole('button', { name: 'Nueva serie' });
  await expect(createButton).toBeVisible({ timeout: 20_000 });
  await createButton.click();

  const formDialog = page.getByRole('dialog', { name: 'Nueva serie' });
  const comboboxes = formDialog.getByRole('combobox');
  await expect(comboboxes).toHaveCount(2);

  const optionButtons = formDialog.getByRole('button', { name: 'Abrir opciones' });
  await optionButtons.nth(0).click();
  const branchOption = page.getByRole('option', { name: 'CI E2E Branch', exact: true });
  await expect(branchOption).toBeVisible();
  await branchOption.click();
  await expect(comboboxes.nth(0)).toHaveValue('CI E2E Branch');

  await optionButtons.nth(1).click();
  const voucherTypeOption = page.getByRole('option').first();
  await expect(voucherTypeOption).toBeVisible();
  const voucherTypeLabel = await voucherTypeOption.innerText();
  await voucherTypeOption.click();
  await expect(comboboxes.nth(1)).toHaveValue(voucherTypeLabel);

  const createdSeries = `Q${Date.now().toString().slice(-3)}`;
  await formDialog.locator('#series-code').fill(createdSeries);
  const createResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/voucher-series') && response.request().method() === 'POST',
  );
  await formDialog.getByRole('button', { name: 'Crear serie' }).click();
  const createdResponse = await createResponse;
  expect(createdResponse.status()).toBe(201);

  await expect(formDialog).toHaveCount(0);
  const collectionAfter = await page.evaluate(async () => {
    const response = await fetch('/api/voucher-series?itemsPerPage=200&page=1', {
      cache: 'no-store',
      headers: { Accept: 'application/ld+json' },
    });
    const payload = await response.json();
    const members = payload.member ?? payload['hydra:member'] ?? [];
    return {
      status: response.status,
      totalItems: payload.totalItems,
      series: members.map((member: { series: string }) => member.series),
    };
  });
  expect(collectionAfter.status).toBe(200);
  expect(collectionAfter.series).toContain(createdSeries);
  await expect(list.getByText(new RegExp(`^${createdSeries} ·`))).toBeVisible();
});
