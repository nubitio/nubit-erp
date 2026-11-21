import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('Ajustes > Operación muestra el redondeo de efectivo del POS', async ({ page }) => {
  const seed = await getE2ESeed();

  await login(page, seed.username, seed.password);
  await page.goto(`${BASE_URL}/dashboard?settings=general`);

  const dialog = page.getByRole('dialog', { name: 'Ajustes' });
  await expect(dialog.getByRole('heading', { name: 'Operación', exact: true })).toBeVisible();
  await expect(dialog.getByText('Punto de venta', { exact: true })).toBeVisible();
  await expect(dialog.getByLabel('Redondeo de efectivo a S/ 0.10')).toBeAttached();
});

test('/config/general y /config/sunat abren Ajustes en su sección', async ({ page }) => {
  const seed = await getE2ESeed();

  await login(page, seed.username, seed.password);
  for (const [route, title] of [
    ['general', 'Operación'],
    ['sunat', 'SUNAT'],
  ]) {
    await page.goto(`${BASE_URL}/config/${route}`);
    await expect(page).toHaveURL(new RegExp(`[?&]settings=${route}`));
    await expect(
      page
        .getByRole('dialog', { name: 'Ajustes' })
        .getByRole('heading', { name: title, exact: true }),
    ).toBeVisible();
  }
});

test('Ajustes se adapta a móvil sin overflow horizontal', async ({ page }) => {
  const seed = await getE2ESeed();

  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, seed.username, seed.password);

  for (const route of ['company', 'sunat', 'general', 'integrations']) {
    await page.goto(`${BASE_URL}/config/${route}`);
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
