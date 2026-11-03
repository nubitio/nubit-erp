import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('sales shortcuts compose server filters and stay synchronized with mobile', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1440, height: 960 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/billing/sales`);
  const grid = page.locator('.nb-datagrid').first();
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
  const shortcuts = grid.locator('.nb-datagrid__filter-shortcuts');
  const waitForFilter = (field: string) =>
    page.waitForResponse((response) => {
      const url = new URL(response.url());
      return url.pathname === '/api/sales' && decodeURIComponent(url.search).includes(field);
    });
  let pending = waitForFilter('paymentState');
  await shortcuts.getByRole('checkbox', { name: 'Por cobrar', exact: true }).click();
  expect((await pending).status()).toBe(200);
  await expect(shortcuts.getByRole('checkbox', { name: 'Por cobrar', exact: true })).toBeChecked();
  pending = waitForFilter('sunatState');
  await shortcuts.getByRole('checkbox', { name: 'Pend. SUNAT', exact: true }).click();
  const response = await pending;
  expect(response.status()).toBe(200);
  expect(decodeURIComponent(new URL(response.url()).search)).toContain('paymentState');
  const active = grid.getByRole('group', { name: 'Filtros activos', exact: true });
  await expect(active).toContainText('Pago: Por cobrar');
  await expect(active).toContainText('SUNAT: Pendiente de atención');
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
  await page.screenshot({
    path: testInfo.outputPath('sales-filters-desktop.png'),
    animations: 'disabled',
  });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.nb-admin-bottom-nav')).toBeVisible();
  await expect(grid.locator('.nb-datagrid__filter-editors')).toHaveCount(0);
  await shortcuts.getByRole('button', { name: 'Filtros (2)', exact: true }).click();
  const sheet = page.getByRole('dialog', { name: 'Filtros', exact: true });
  await expect(sheet).toBeVisible();
  await expect(sheet.locator('#nb-datagrid-filter-paymentState')).toContainText('Por cobrar');
  await expect(sheet.locator('#nb-datagrid-filter-sunatState')).toContainText(
    'Pendiente de atención',
  );
  await page.screenshot({
    path: testInfo.outputPath('sales-filters-mobile.png'),
    animations: 'disabled',
  });
  await sheet.getByRole('button', { name: 'Limpiar filtros', exact: true }).click();
  await sheet.getByRole('button', { name: 'Listo', exact: true }).click();
  await expect(active).toHaveCount(0);
  await expect(
    shortcuts.getByRole('checkbox', { name: 'Por cobrar', exact: true }),
  ).not.toBeChecked();
  await page.setViewportSize({ width: 1440, height: 960 });
  pending = waitForFilter('issuedDate');
  await shortcuts.getByRole('checkbox', { name: 'Este mes', exact: true }).click();
  expect((await pending).status()).toBe(200);
  await expect(active).toContainText('Fecha:');
  const range = grid.locator('.nb-date-range-picker');
  await expect(range).toBeVisible();
  await expect(range.locator('.nb-date-range-picker__clear')).toHaveCount(0);
  const clearDate = grid.getByRole('button', { name: 'Limpiar filtro', exact: true }).first();
  const calendarBox = (await range.locator('.nb-date-range-picker__calendar-btn').boundingBox())!;
  const clearDateBox = (await clearDate.boundingBox())!;
  expect(calendarBox.x + calendarBox.width).toBeLessThanOrEqual(clearDateBox.x);
  await page.screenshot({
    path: testInfo.outputPath('sales-filters-date-range.png'),
    animations: 'disabled',
  });
  await active.getByRole('button').click();
  await expect(active).toHaveCount(0);
});
