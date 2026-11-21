import { expect, test } from '@playwright/test';
import { strFromU8, strToU8, unzipSync, zipSync } from 'fflate';
import { login } from './helpers/auth';
import { loginViaApi } from './helpers/fiscal';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('previsualiza e importa saldos iniciales por cobrar y los contabiliza', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

  const templateResponse = await request.get(
    `${BASE_URL}/api/v1/onboarding/spreadsheets/template/receivables`,
    { headers: auth.headers },
  );
  expect(templateResponse.ok()).toBeTruthy();

  // Keep the real template, but give this import its own document/customer so
  // an existing business balance (even in another branch) cannot satisfy it.
  const suffix = String(Date.now());
  const documentNumber = `E2E-${suffix}`;
  const customerName = `Saldo inicial E2E ${suffix}`;
  const workbook = unzipSync(new Uint8Array(await templateResponse.body()));
  for (const [path, content] of Object.entries(workbook)) {
    if (!path.endsWith('.xml')) continue;
    workbook[path] = strToU8(
      strFromU8(content)
        .replaceAll('F001-88', documentNumber)
        .replaceAll('20100100101', suffix.slice(-8))
        .replaceAll('Andina Retail SAC', customerName),
    );
  }

  await page.goto(`${BASE_URL}/setup/import`);
  await page.getByRole('button', { name: 'Omitir este paso' }).click();
  await page.getByRole('button', { name: 'Omitir este paso' }).click();

  const upload = page.locator('input[type="file"]').first();
  await upload.setInputFiles({
    name: 'saldos-iniciales.xlsx',
    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: Buffer.from(zipSync(workbook)),
  });

  await page.getByRole('button', { name: 'Previsualizar por cobrar' }).click();
  await expect(page.getByLabel('Vista previa por cobrar')).toBeVisible();
  await expect(page.getByText('Vista previa · 1 filas')).toBeVisible();

  const importResponsePromise = page.waitForResponse(
    (response) =>
      response.url().endsWith('/api/v1/onboarding/receivables') &&
      response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Confirmar por cobrar' }).click();
  const importResponse = await importResponsePromise;
  expect(importResponse.ok(), await importResponse.text()).toBeTruthy();
  expect(await importResponse.json()).toMatchObject({ created: 1, skipped: 0, errors: [] });
  await expect(page.getByText(/saldos por cobrar importados|filas ya importadas/)).toBeVisible();

  // The same Excel is a no-op, not a second debt or accounting entry.
  const repeatResponsePromise = page.waitForResponse(
    (response) =>
      response.url().endsWith('/api/v1/onboarding/receivables') &&
      response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: 'Confirmar por cobrar' }).click();
  const repeatResponse = await repeatResponsePromise;
  expect(repeatResponse.ok(), await repeatResponse.text()).toBeTruthy();
  expect(await repeatResponse.json()).toMatchObject({ created: 0, skipped: 1, errors: [] });

  await page.goto(`${BASE_URL}/accounts-receivable`);
  await expect(page.getByRole('table', { name: 'Deudores' })).toBeVisible();
  await expect(page.getByRole('row').filter({ hasText: customerName })).toContainText(/716[.,]30/);

  const ledgerResponse = await request.get(
    `${BASE_URL}/api/general-ledger/ledger?account=121&from=2026-06-15&to=2026-06-15`,
    { headers: auth.headers },
  );
  expect(ledgerResponse.ok()).toBeTruthy();
  const ledger = (await ledgerResponse.json()) as { lines: Array<{ description: string }> };
  expect(ledger.lines.filter((line) => line.description.includes(documentNumber))).toHaveLength(1);
});
