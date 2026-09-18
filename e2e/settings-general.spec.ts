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

test('Ajustes se adapta a móvil sin overflow horizontal', async ({ page }) => {
  const seed = await getE2ESeed();

  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, seed.username, seed.password);

  for (const route of ['company', 'sunat', 'general', 'integrations']) {
    await page.goto(`${BASE_URL}/#/config/${route}`);
    await expect(page.getByRole('navigation', { name: 'Secciones de ajustes' })).toBeVisible();
    await expect
      .poll(() =>
        page.evaluate(
          () => document.documentElement.scrollWidth <= document.documentElement.clientWidth,
        ),
      )
      .toBe(true);
  }
});
