import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

for (const mobile of [false, true]) {
  test(`receivable aging and recoverable errors (${mobile ? 'mobile dark' : 'desktop light'})`, async ({
    page,
  }, info) => {
    const seed = await getE2ESeed();
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 960 });
    await page.emulateMedia({ colorScheme: mobile ? 'dark' : 'light' });
    await login(page, seed.username, seed.password);
    let failSummary = false;
    let failDetail = true;
    await page.route('**/api/sales/reports/receivable-summary*', (route) =>
      route.fulfill({
        status: failSummary ? 503 : 200,
        json: failSummary
          ? {}
          : {
              totalBalance: '101.80',
              currency: 'PEN',
              debtorCount: 1,
              overdueTotal: '1.80',
              notYetDueTotal: '100.00',
              aging: [
                { bucket: 'current', label: 'Al día', total: '100.00' },
                { bucket: '1-30', label: '', total: '0.30' },
                { bucket: '31-60', label: '', total: '1.20' },
                { bucket: '61-90', label: '', total: '0.10' },
                { bucket: '90+', label: '', total: '0.20' },
              ],
            },
      }),
    );
    await page.route('**/api/sales/reports/receivable-customers*', (route) =>
      route.fulfill({
        json: {
          '@context': '/api/contexts/CustomerReceivable',
          '@id': '/api/sales/reports/receivable-customers',
          '@type': 'hydra:Collection',
          'hydra:totalItems': 1,
          'hydra:member': [
            {
              id: '42',
              customerId: 42,
              customerName: 'Cliente de revisión',
              customerPhone: null,
              totalBalance: '101.80',
              currency: 'PEN',
              earliestDueDate: '2026-08-01',
              overdueDays: 89,
              agingBucket: '61-90',
              pendingCount: 2,
            },
          ],
        },
      }),
    );
    await page.route(/\/api\/sales\?/, (route) =>
      route.fulfill({
        status: failDetail ? 503 : 200,
        json: failDetail ? {} : { 'hydra:member': [], 'hydra:totalItems': 0 },
      }),
    );
    await page.goto(`${getE2EBaseUrl()}/#/accounts-receivable`);
    const summary = page.getByRole('region', { name: 'Resumen de cuentas por cobrar' });
    await expect(summary).toContainText('101.80 pendientes de 1 cliente');
    const cards = summary.locator('.nb-kpi-metric-card');
    await expect(cards).toHaveCount(4);
    await expect(cards.nth(0)).toContainText('100.00');
    await expect(cards.nth(3)).toContainText('0.30');
    await expect(
      page.getByRole('button', { name: 'Cliente de revisión', exact: true }),
    ).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      mobile ? 390 : 1440,
    );
    await page.screenshot({
      path: info.outputPath('receivable-aging.png'),
      fullPage: true,
      animations: 'disabled',
    });
    await page.getByRole('button', { name: 'Cliente de revisión', exact: true }).click();
    const drawer = page.getByRole('dialog', { name: 'Cliente de revisión' });
    await expect(drawer.getByRole('alert')).toContainText('No se pudieron cargar los comprobantes');
    await expect(drawer).not.toContainText('0 comprobantes por cobrar');
    failDetail = false;
    await drawer.getByRole('button', { name: 'Reintentar comprobantes' }).click();
    await expect(drawer).toContainText('0 comprobantes por cobrar');
    await page.keyboard.press('Escape');
    failSummary = true;
    await page.reload();
    await expect(summary.getByRole('alert')).toContainText('No se pudo cargar el resumen');
    await expect(summary.getByTestId('accounts-receivable-kpis')).toHaveCount(0);
    failSummary = false;
    await summary.getByRole('button', { name: 'Reintentar resumen' }).click();
    await expect(summary.getByTestId('accounts-receivable-kpis')).toBeVisible();
  });
}
