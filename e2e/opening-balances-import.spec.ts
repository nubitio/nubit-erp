import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { loginViaApi } from './helpers/fiscal';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('previsualiza e importa saldos iniciales por cobrar y los contabiliza', async ({ page, request }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

  const templateResponse = await request.get(
    `${BASE_URL}/api/v1/onboarding/spreadsheets/template/receivables`,
    { headers: auth.headers },
  );
  expect(templateResponse.ok()).toBeTruthy();

  await page.goto(`${BASE_URL}/#/setup/import`);
  await page.getByRole('button', { name: /Ya los tengo \/ Siguiente/ }).click();
  await page.getByRole('button', { name: 'Siguiente', exact: true }).click();

  const upload = page.locator('input[type="file"]').first();
  await upload.setInputFiles({
    name: 'saldos-iniciales.xlsx',
    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: await templateResponse.body(),
  });

  await page.getByRole('button', { name: 'Previsualizar por cobrar' }).click();
  await expect(page.getByLabel('Vista previa por cobrar')).toBeVisible();
  await expect(page.getByText('Vista previa · 1 filas')).toBeVisible();

  await page.getByRole('button', { name: 'Confirmar por cobrar' }).click();
  await expect(page.getByText(/saldos por cobrar importados|filas ya importadas/)).toBeVisible();

  await page.goto(`${BASE_URL}/#/accounts-receivable`);
  await expect(page.getByRole('table', { name: 'Deudores' })).toBeVisible();

  const ledgerResponse = await request.get(
    `${BASE_URL}/api/general-ledger/ledger?account=121&from=2026-06-15&to=2026-06-15`,
    { headers: auth.headers },
  );
  expect(ledgerResponse.ok()).toBeTruthy();
  const ledger = await ledgerResponse.json() as { lines: Array<{ description: string }> };
  expect(ledger.lines.some((line) => line.description.includes('F001-88'))).toBeTruthy();
});
