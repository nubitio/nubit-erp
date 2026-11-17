import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('ERP consumes the shared workspace and catalog layouts without changing product data', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1440, height: 960 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/catalog/products`);
  await expect(page.locator('html')).toHaveAttribute('data-visual-style', 'nubit');
  await expect(page.locator('.nb-admin-shell')).toHaveClass(/workspace/);
  const productCards = page.locator('.product-catalog-card');
  await expect(productCards.first()).toBeVisible({ timeout: 30000 });
  await expect(page.getByRole('heading', { name: 'Productos', exact: true })).toBeVisible();
  const card = page.locator('.nb-datagrid__card--media').first();
  const cardBox = (await card.boundingBox())!;
  const mediaBox = (await card.locator('.nb-datagrid__card-media').boundingBox())!;
  expect(mediaBox.width).toBeGreaterThan(cardBox.width - 3);
  const categoryFilters = page.locator('.nb-datagrid__quick-filters').first();
  await expect(categoryFilters).toBeVisible();
  const category = categoryFilters.getByRole('checkbox').nth(1);
  const categoryName = await category.innerText();
  await category.click();
  await expect(category).toBeChecked();
  await expect(productCards.first().locator('.product-catalog-card__category')).toHaveText(
    categoryName,
  );
  await categoryFilters.getByRole('checkbox', { name: 'Todos', exact: true }).click();
  await page.getByRole('button', { name: 'Tabla', exact: true }).click();
  await expect(page.locator('.nb-datagrid__table')).toBeVisible();
  await page.getByRole('button', { name: 'Tarjetas', exact: true }).click();
  await expect(productCards.first()).toBeVisible();
  await expect(page.locator('.nb-datagrid')).toHaveAttribute('aria-busy', 'false');
  await page.screenshot({
    path: testInfo.outputPath('erp-catalog-light.png'),
    animations: 'disabled',
  });

  await page
    .locator('.nb-datagrid__toolbar')
    .getByRole('button', { name: /^(Nuevo|New|Agregar|Add)$/i })
    .first()
    .click();
  const form = page.locator('.nb-crud-page-shell');
  await expect(form).toBeVisible();
  await expect(form.getByRole('heading', { name: 'Nuevo producto', exact: true })).toBeVisible();
  await expect(form.locator('.nb-form__columns')).toBeVisible();
  await expect(form.getByRole('button', { name: 'Guardar producto', exact: true })).toBeVisible();
  await page.screenshot({
    path: testInfo.outputPath('erp-product-form.png'),
    animations: 'disabled',
  });
  await form.getByRole('button', { name: 'Cancelar', exact: true }).last().click();
  await expect(form).toHaveCount(0);

  await page.keyboard.press('Control+k');
  const search = page.getByRole('combobox', { name: 'Buscar secciones del ERP' });
  await expect(search).toBeFocused();
  await search.fill('Cliente');
  await page
    .getByRole('option', { name: /clientes/i })
    .first()
    .click();
  await expect(page).toHaveURL(/\/customer/);

  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.getByRole('navigation', { name: 'Accesos principales' })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({
    path: testInfo.outputPath('erp-workspace-mobile.png'),
    animations: 'disabled',
  });
});

test('catalog and product form remain usable on mobile in dark mode', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/catalog/products`);
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('.product-catalog-card').first()).toBeVisible({ timeout: 30000 });
  await expect(page.locator('.nb-datagrid')).toHaveAttribute('aria-busy', 'false');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({
    path: testInfo.outputPath('erp-catalog-mobile-dark.png'),
    animations: 'disabled',
  });
  const newButton = page.locator('.nb-datagrid__fab');
  const newBox = (await newButton.boundingBox())!;
  const pagerBox = (await page.locator('.nb-datagrid__pager').boundingBox())!;
  expect(newBox.y + newBox.height).toBeLessThanOrEqual(pagerBox.y);
  await newButton.click();
  const form = page.locator('.nb-crud-page-shell');
  await expect(form.getByRole('heading', { name: 'Nuevo producto', exact: true })).toBeVisible();
  await expect(page.getByRole('navigation', { name: 'Secciones de catálogo' })).toHaveCount(0);
  const columns = form.locator('.nb-form__column');
  const main = (await columns.first().boundingBox())!;
  const aside = (await form.locator('.nb-form__column--aside').boundingBox())!;
  expect(aside.y).toBeGreaterThan(main.y + main.height);
  await form.getByLabel('Nombre del producto', { exact: true }).fill('Producto sin guardar');
  const save = form.getByRole('button', { name: 'Guardar producto', exact: true });
  await expect(save).toBeInViewport();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({
    path: testInfo.outputPath('erp-product-form-mobile-dark.png'),
    animations: 'disabled',
  });
});
