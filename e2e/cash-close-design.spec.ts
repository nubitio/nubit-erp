import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

for (const mobile of [false, true])
  test(`cash closing counts exact denominations, restores drafts and confirms (${mobile ? 'mobile dark' : 'desktop light'})`, async ({
    page,
  }, info) => {
    const seed = await getE2ESeed();
    const id = 'design-session';
    let summaryStatus = 200;
    let submitted: Record<string, unknown> | undefined;
    await page.route('**/api/cash-sessions/**', (route) => {
      if (route.request().method() !== 'GET') return route.abort();
      return route.fallback();
    });
    await page.route(`**/api/cash-sessions/${id}/summary`, (route) =>
      route.fulfill({
        status: summaryStatus,
        json:
          summaryStatus === 200
            ? [
                { paymentMethodId: 1, paymentMethod: 'Efectivo', expectedAmount: '401.50' },
                { paymentMethodId: 7, paymentMethod: 'Yape', expectedAmount: '23.50' },
              ]
            : {},
      }),
    );
    await page.route(`**/api/cash-sessions/${id}/x-report`, (route) =>
      route.fulfill({
        json: {
          openingBalance: '200.00',
          totalSales: '225.00',
          cashIn: '0.00',
          cashOut: '0.00',
          currentBalance: '425.00',
          shiftDurationMinutes: 60,
          salesByPaymentMethod: [
            { paymentMethodId: 1, paymentMethod: 'Efectivo', amount: '201.50' },
            { paymentMethodId: 7, paymentMethod: 'Yape', amount: '23.50' },
          ],
        },
      }),
    );
    await page.route(`**/api/cash-sessions/${id}/close`, (route) => {
      submitted = route.request().postDataJSON();
      return route.fulfill({ json: { success: true, message: 'Caja cerrada correctamente' } });
    });
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 960 });
    await page.emulateMedia({ colorScheme: mobile ? 'dark' : 'light' });
    await login(page, seed.username, seed.password);
    const url = `${getE2EBaseUrl()}/cash-session/${id}/close`;
    await page.goto(url);
    await expect(page.getByRole('heading', { name: 'Cierre de caja', exact: true })).toBeVisible();
    const close = page.getByTestId('cash-close-btn');
    await expect(close).toBeDisabled();
    await page.getByRole('spinbutton', { name: 'Cantidad de S/ 200.00', exact: true }).fill('2');
    await page.getByRole('spinbutton', { name: 'Cantidad de S/ 0.10', exact: true }).fill('3');
    await page.getByRole('spinbutton', { name: 'Cantidad de S/ 0.20', exact: true }).fill('1');
    await expect(
      page.getByRole('spinbutton', { name: 'Monto contado de Efectivo', exact: true }),
    ).toHaveValue('400.50');
    await expect(page.getByRole('status').filter({ hasText: 'Faltante' })).toContainText('1.00');
    await page.getByRole('checkbox', { name: /Yape/ }).check();
    await expect(close).toBeDisabled();
    await page
      .getByRole('textbox', { name: 'Motivo de la diferencia de Efectivo', exact: true })
      .fill('Moneda faltante en el turno');
    await expect(close).toBeEnabled();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      mobile ? 390 : 1440,
    );
    await page.screenshot({
      path: info.outputPath('cash-count.png'),
      fullPage: true,
      animations: 'disabled',
    });
    await page
      .getByRole('button', { name: 'Guardar conteo y seguir vendiendo', exact: true })
      .click();
    await expect(page).toHaveURL(/\/pos$/);
    await page.goto(url);
    await expect(
      page.getByText('Conteo recuperado de este equipo.', { exact: true }),
    ).toBeVisible();
    await expect(
      page.getByRole('spinbutton', { name: 'Monto contado de Efectivo', exact: true }),
    ).toHaveValue('400.50');
    await expect(close).toBeEnabled();
    await close.click();
    const confirm = page.getByRole('alertdialog', { name: 'Cerrar caja', exact: true });
    await expect(confirm).toBeVisible();
    expect(submitted).toBeUndefined();
    await confirm.getByRole('button', { name: 'No', exact: true }).click();
    expect(submitted).toBeUndefined();
    await close.click();
    await confirm.getByRole('button', { name: 'Sí', exact: true }).click();
    await expect.poll(() => submitted).toBeDefined();
    expect(submitted).toMatchObject({
      closureItems: [
        {
          paymentMethod: '/api/payment-methods/1',
          countedAmount: '400.50',
          note: 'Moneda faltante en el turno',
        },
        { paymentMethod: '/api/payment-methods/7', countedAmount: '23.50' },
      ],
    });
    await expect(page).toHaveURL(/\/cashbox\/dashboard$/);
    summaryStatus = 503;
    await page.goto(url);
    await expect(
      page.getByText('No se pudo cargar el resumen de cierre.', { exact: false }),
    ).toBeVisible();
    summaryStatus = 200;
    await page.getByRole('button', { name: 'Reintentar resumen', exact: true }).click();
    await expect(
      page.getByRole('spinbutton', { name: 'Monto contado de Efectivo', exact: true }),
    ).toHaveValue('');
  });
