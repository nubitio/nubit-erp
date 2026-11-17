import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('sales shares its real records between table and responsive cards', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1440, height: 960 });
  await login(page, seed.username, seed.password);
  let failSummary = true;
  await page.route('**/api/sales/reports/monthly-summary', async (route) => {
    if (failSummary) {
      failSummary = false;
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    } else {
      await route.continue();
    }
  });
  await page.goto(`${getE2EBaseUrl()}/billing/sales`);
  const summary = page.getByRole('region', { name: 'Resumen mensual de ventas' });
  await expect(summary.getByRole('alert')).toContainText('No se pudo cargar');
  const summaryResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/sales/reports/monthly-summary') && response.status() === 200,
  );
  await summary.getByRole('button', { name: 'Actualizar resumen' }).click();
  const summaryData = await (await summaryResponse).json();
  await expect(summary.getByRole('alert')).toHaveCount(0);
  for (const item of summaryData.currencies) {
    await expect(summary.getByTestId(`sales-kpis-${item.currency}`)).toContainText(item.currency);
  }
  if (summaryData.currencies.length === 0) {
    await expect(summary).toContainText('Sin ventas este mes');
  }
  const grid = page.locator('.nb-datagrid').first();
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
  await expect(grid.locator('.nb-datagrid__table')).toBeVisible();
  const tableText = await grid.locator('.nb-datagrid__table').innerText();
  await grid.getByRole('button', { name: 'Tarjetas', exact: true }).click();
  await expect(grid.locator('.nb-datagrid__table')).toHaveCount(0);
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
  const customer = await grid.locator('.sale-summary-card__customer').first().innerText();
  expect(customer.trim()).not.toBe('');
  expect(tableText).toContain(customer.trim());
  await page.screenshot({ path: testInfo.outputPath('sales-desktop.png'), animations: 'disabled' });
  await grid.getByRole('button', { name: 'Tabla', exact: true }).click();
  await expect(grid.locator('.nb-datagrid__table')).toBeVisible();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(grid.locator('.nb-datagrid__table')).toHaveCount(0);
  await expect(page.locator('.nb-admin-bottom-nav')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('sales-mobile.png'), animations: 'disabled' });
});

test('monthly KPI cards keep currencies separate on mobile', async ({ page }, testInfo) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  // Presentation fixture only; the aggregation query has its own database test.
  await page.route('**/api/sales/reports/monthly-summary', (route) =>
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        periodStart: '2026-10-01',
        periodEnd: '2026-11-01',
        currencies: [
          { currency: 'PEN', sold: '150.30', balanceDue: '20.10', saleCount: 2, sunatPending: 1 },
          { currency: 'USD', sold: '9.99', balanceDue: '1.99', saleCount: 1, sunatPending: 0 },
        ],
      }),
    }),
  );
  await page.setViewportSize({ width: 1440, height: 960 });
  await page.goto(`${getE2EBaseUrl()}/billing/sales`);
  const pen = page.getByTestId('sales-kpis-PEN');
  const usd = page.getByTestId('sales-kpis-USD');
  await expect(pen).toContainText('150.30');
  await expect(usd).toContainText('9.99');
  await page.screenshot({
    path: testInfo.outputPath('sales-kpis-desktop.png'),
    animations: 'disabled',
  });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.locator('.nb-admin-bottom-nav')).toBeVisible();
  await expect(page.locator('.nb-datagrid__table')).toHaveCount(0);
  await page.getByRole('button', { name: 'Resumen mensual', exact: true }).click();
  await expect(pen).toBeVisible();
  await expect(usd).toBeVisible();
  const overflows = await page.evaluate(
    () => document.documentElement.scrollWidth > window.innerWidth,
  );
  expect(overflows).toBe(false);
  await page.screenshot({
    path: testInfo.outputPath('sales-kpis-mobile.png'),
    animations: 'disabled',
  });
});
