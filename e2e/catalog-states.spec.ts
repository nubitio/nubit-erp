import { expect, test } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('catalog first-use actions and transient failures are recoverable', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  let response: 'empty' | 'error' | 'forbidden' | 'normal' = 'empty';
  // Intercept only collection reads; exercise the real shell, schema, forms and import dialog.
  await page.route(/\/api\/products(?:\?|$)/, async (route) => {
    if (route.request().method() !== 'GET' || response === 'normal') return route.continue();
    if (response === 'error' || response === 'forbidden') {
      return route.fulfill({
        status: response === 'error' ? 503 : 403,
        json: { title: 'Test response' },
      });
    }
    return route.fulfill({
      json: {
        '@context': '/api/contexts/Product',
        '@id': '/api/products',
        '@type': 'Collection',
        member: [],
        totalItems: 0,
      },
    });
  });
  await page.setViewportSize({ width: 1440, height: 960 });
  await page.goto(`${getE2EBaseUrl()}/#/catalog/products`);
  await expect(
    page.getByText('Agrega tus productos para empezar a vender', { exact: true }),
  ).toBeVisible();
  const illustrations = page.locator('.product-catalog-empty img');
  await expect(illustrations).toHaveCount(3);
  for (const illustration of await illustrations.all()) {
    await expect(illustration).toHaveJSProperty('complete', true);
    expect(
      await illustration.evaluate((img: HTMLImageElement) => img.naturalWidth),
    ).toBeGreaterThan(0);
  }
  await page.screenshot({ path: testInfo.outputPath('erp-catalog-first-use.png') });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.emulateMedia({ colorScheme: 'dark' });
  await expect(page.locator('.app')).toHaveClass(/screen-x-small/);
  await expect(page.locator('.nb-admin-shell__bottom-navigation')).toBeVisible();
  await expect(page.locator('.nb-admin-shell__scrim')).toHaveCount(0);
  await expect(page.locator('.nb-admin-shell__panel')).not.toBeInViewport();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  const options = page.locator('.product-catalog-empty__option');
  const firstOption = (await options.first().boundingBox())!;
  const secondOption = (await options.last().boundingBox())!;
  expect(secondOption.y).toBeGreaterThanOrEqual(firstOption.y + firstOption.height);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: testInfo.outputPath('erp-catalog-first-use-mobile-dark.png') });
  await page.setViewportSize({ width: 1440, height: 960 });
  await page.emulateMedia({ colorScheme: 'light' });
  await page.getByRole('button', { name: 'Crear producto', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Nuevo producto', exact: true })).toBeVisible();
  await page
    .locator('.nb-crud-page-shell')
    .getByRole('button', { name: 'Cancelar', exact: true })
    .last()
    .click();
  await page.getByRole('button', { name: 'Subir archivo', exact: true }).click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.getByText('Seleccionar archivo .xlsx', { exact: true })).toBeVisible();
  await page.keyboard.press('Escape');
  const download = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Descargar plantilla de Excel', exact: true }).click();
  const template = await download;
  expect(template.suggestedFilename()).toBe('plantilla-productos.xlsx');
  const templatePath = await template.path();
  expect((await readFile(templatePath!)).subarray(0, 2).toString()).toBe('PK');

  response = 'error';
  await page.reload();
  await expect(page.getByText('No se pudo cargar la información', { exact: true })).toBeVisible();
  await expect(page.locator('.product-catalog-empty')).toHaveCount(0);
  response = 'normal';
  await page.getByRole('button', { name: 'Reintentar', exact: true }).click();
  await expect(page.locator('.product-catalog-card').first()).toBeVisible({ timeout: 30000 });
  await page
    .getByRole('searchbox', { name: 'Filtrar Nombre del producto', exact: true })
    .fill('No existe este producto 19583');
  await expect(
    page.getByText('No hay resultados para estos filtros', { exact: true }),
  ).toBeVisible();
  await page.getByRole('button', { name: 'Limpiar filtros', exact: true }).click();
  await expect(page.locator('.product-catalog-card').first()).toBeVisible();

  response = 'forbidden';
  await page.reload();
  await expect(page.getByText('Acceso denegado', { exact: true })).toBeVisible();
  await expect(page.locator('.product-catalog-empty')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Reintentar', exact: true })).toHaveCount(0);
});

test('read-only catalog permissions allow consultation and block create deep links', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  // Publish restricted UI grants through the normal session commit path.
  // The server session is still the seeded administrator; this checks frontend behavior.
  await page.route(/\/api\/auth\/(login|select-branch)(?:\?|$)/, async (route) => {
    const response = await route.fetch();
    const body = await response.json();
    if (body.user) body.user.permissions = ['catalog.view'];
    await route.fulfill({ response, json: body });
  });
  await login(page, seed.username, seed.password);
  const writes: string[] = [];
  page.on('request', (request) => {
    if (
      /\/api\/(?:v1\/)?products(?:\/|\?|$)/.test(request.url()) &&
      ['POST', 'PATCH', 'DELETE'].includes(request.method())
    )
      writes.push(request.url());
  });
  await page.goto(`${getE2EBaseUrl()}/#/catalog/products`);
  await expect(page.locator('.product-catalog-card').first()).toBeVisible({ timeout: 30000 });
  await expect(page.getByRole('button', { name: 'Importar productos', exact: true })).toHaveCount(
    0,
  );
  await page.locator('.nb-datagrid__card-open').first().click();
  const form = page.locator('.nb-crud-page-shell');
  await expect(
    form.getByRole('heading', { name: 'Consultar producto', exact: true }),
  ).toBeVisible();
  await expect(form.getByLabel('Nombre del producto', { exact: true })).toHaveAttribute(
    'readonly',
    '',
  );
  await expect(form.getByRole('button', { name: 'Guardar producto', exact: true })).toHaveCount(0);
  await expect(form.locator('input[type="file"]')).toHaveCount(0);
  await expect(form.getByText('Sin imagen', { exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('erp-product-readonly.png') });
  await page.goto(`${getE2EBaseUrl()}/#/catalog/products/new`);
  await expect(page.getByText('Acceso denegado', { exact: true })).toBeVisible();
  await expect(page.locator('.nb-crud-page-shell')).toHaveCount(0);
  expect(writes).toEqual([]);
});
