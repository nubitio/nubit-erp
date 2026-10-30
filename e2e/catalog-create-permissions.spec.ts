import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('administrator can start product and customer creation from their grids', async ({ page }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);

  for (const route of ['catalog/products', 'customer']) {
    await page.goto(`${BASE_URL}/#/${route}`);
    const toolbar = page.locator('.nb-datagrid__toolbar:visible').first();
    await expect(toolbar).toBeVisible({ timeout: 20_000 });

    const createButton = toolbar
      .getByRole('button', { name: /^(Nuevo|New|Agregar|Add)$/i })
      .first();
    await expect(createButton, `create action must be visible on /#/${route}`).toBeVisible();
    await createButton.click();
    if (route === 'catalog/products') {
      const form = page.locator('.nb-crud-page-shell');
      await expect(form).toBeVisible();
      await form.getByRole('button', { name: /cancelar/i, exact: true }).last().click();
      await expect(form).toHaveCount(0);
    } else {
      await expect(page.getByRole('dialog').last()).toBeVisible();
      await page.keyboard.press('Escape');
    }
  }

  await page.screenshot({
    path: 'var/test-results/catalog-create-permissions.png',
    fullPage: true,
  });
});
