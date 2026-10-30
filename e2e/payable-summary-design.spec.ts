import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

for (const mobile of [false, true])
  test(`payable summary separates currencies and retries (${mobile ? 'mobile dark' : 'desktop light'})`, async ({
    page,
  }, info) => {
    const seed = await getE2ESeed();
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 960 });
    await page.emulateMedia({ colorScheme: mobile ? 'dark' : 'light' });
    await login(page, seed.username, seed.password);
    let fail = true;
    await page.route('**/api/supplier-bills-summary', (route) =>
      route.fulfill({
        status: fail ? 503 : 200,
        json: fail
          ? {}
          : {
              asOf: '2026-10-29',
              currencies: [
                {
                  currency: 'PEN',
                  balanceDue: '5915.08',
                  overdueTotal: '2600.00',
                  dueWithinSevenDays: '2300.00',
                  dueWithinThirtyDays: '3315.08',
                  billCount: 5,
                  supplierCount: 5,
                },
                {
                  currency: 'USD',
                  balanceDue: '100.50',
                  overdueTotal: '0.00',
                  dueWithinSevenDays: '0.00',
                  dueWithinThirtyDays: '100.50',
                  billCount: 1,
                  supplierCount: 1,
                },
              ],
            },
      }),
    );
    await page.goto(`${getE2EBaseUrl()}/#/accounts-payable`);
    const summary = page.getByRole('region', { name: 'Resumen de cuentas por pagar' });
    await expect(summary.getByRole('alert')).toContainText('No se pudo cargar el resumen');
    await expect(summary.locator('.nb-kpi-metric-row')).toHaveCount(0);
    fail = false;
    await summary.getByRole('button', { name: 'Reintentar resumen' }).click();
    await expect(summary.locator('.nb-kpi-metric-row')).toHaveCount(2);
    await expect(summary).toContainText('PEN · 5 facturas · 5 proveedores');
    await expect(summary).toContainText('USD · 1 factura · 1 proveedor');
    await expect(summary.locator('.nb-kpi-metric-row').nth(0)).toContainText('5,915.08');
    await expect(summary.locator('.nb-kpi-metric-row').nth(1)).toContainText('100.50');
    const navigation = page.getByRole('navigation', { name: 'Secciones de finanzas' });
    await expect(navigation.getByRole('link', { name: 'Por pagar' })).toHaveAttribute(
      'aria-current',
      'page',
    );
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      mobile ? 390 : 1440,
    );
    await expect(page.locator('.nb-datagrid').first()).toHaveAttribute('aria-busy', 'false');
    await page.screenshot({
      path: info.outputPath('payable-summary.png'),
      fullPage: true,
      animations: 'disabled',
    });
    await navigation.getByRole('link', { name: 'Por cobrar' }).click();
    await expect(page).toHaveURL(/#\/accounts-receivable$/);
    await expect(navigation.getByRole('link', { name: 'Por cobrar' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });
