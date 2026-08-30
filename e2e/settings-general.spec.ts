import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('Configuración general muestra el redondeo de efectivo del POS', async ({ page }) => {
  const seed = await getE2ESeed();

  await login(page, seed.username, seed.password);
  await page.goto(`${BASE_URL}/#/config/general`);

  await expect(page.getByTestId('settings-general-tab')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Punto de venta' })).toBeVisible();
  await expect(page.getByLabel('Redondeo de efectivo a diez céntimos')).toBeAttached();
  await expect(page.getByText('Redondeo de efectivo a S/ 0.10')).toBeVisible();
});
