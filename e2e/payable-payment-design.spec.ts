import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

function fixture() {
  return {
    id: 'design-bill',
    supplier: { businessName: 'Importaciones del Sur' },
    series: 'F001',
    number: '000042',
    issueDate: '2026-10-01',
    dueDate: '2026-10-20',
    currency: 'PEN',
    amount: '118.00',
    paidTotal: '18.00',
    balanceDue: '100.00',
    status: 'PARTIAL',
    payments: [
      {
        id: 9,
        amount: '18.00',
        paidAt: '2026-10-10',
        reference: 'OP-42',
        reversedAt: null as string | null,
      },
    ],
  };
}

for (const mobile of [false, true])
  test(`supplier payment validates, retries and reverses (${mobile ? 'mobile dark' : 'desktop light'})`, async ({
    page,
  }, info) => {
    const seed = await getE2ESeed();
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 960 });
    await page.emulateMedia({ colorScheme: mobile ? 'dark' : 'light' });
    await login(page, seed.username, seed.password);
    let bill = fixture();
    let failLoad = true;
    let failPayment = true;
    let failReverse = true;
    const payments: unknown[] = [];
    const reversals: unknown[] = [];
    await page.route(/\/api\/supplier-bills\/design-bill(?:\/.*)?$/, async (route) => {
      const request = route.request();
      if (request.method() === 'GET')
        return route.fulfill({ status: failLoad ? 503 : 200, json: failLoad ? {} : bill });
      if (request.url().endsWith('/reverse-payment')) {
        reversals.push(request.postDataJSON());
        if (failReverse) return route.fulfill({ status: 503, json: {} });
        bill = {
          ...bill,
          balanceDue: '118.00',
          paidTotal: '0.00',
          status: 'PENDING',
          payments: bill.payments.map((payment) => ({
            ...payment,
            reversedAt: '2026-10-29T12:00:00-05:00',
          })),
        };
        return route.fulfill({ json: bill });
      }
      if (request.url().endsWith('/pay')) {
        payments.push(request.postDataJSON());
        if (failPayment) return route.fulfill({ status: 503, json: {} });
        return route.fulfill({ json: bill });
      }
      return route.abort();
    });
    const url = `${getE2EBaseUrl()}/supplier-bill/design-bill/pay`;
    await page.goto(url);
    await expect(page.getByRole('alert')).toContainText('No se pudo cargar el contexto');
    await expect(page.getByTestId('supplier-bill-pay-submit-btn')).toHaveCount(0);
    failLoad = false;
    await page.getByRole('button', { name: 'Reintentar factura' }).click();
    const summary = page.getByRole('complementary', { name: 'Resumen de la factura' });
    await expect(summary).toContainText('F001-000042');
    await expect(summary).toContainText('100.00');
    const amount = page.getByRole('spinbutton', { name: 'Monto (PEN)' });
    const submit = page.getByTestId('supplier-bill-pay-submit-btn');
    await amount.fill('100.01');
    await submit.click();
    await expect(amount).toBeFocused();
    await expect(amount).toHaveAttribute('aria-invalid', 'true');
    expect(payments).toHaveLength(0);
    await amount.fill('0.10');
    await submit.click();
    const method = page.getByRole('combobox', { name: 'Método de pago', exact: true });
    await expect(method).toBeFocused();
    await method.fill('Efectivo');
    await page
      .getByRole('option')
      .filter({ hasText: /efectivo/i })
      .first()
      .click();
    await page.getByRole('textbox', { name: 'Referencia (opcional)' }).fill('OP-EXACTA');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      mobile ? 390 : 1440,
    );
    await page.screenshot({
      path: info.outputPath('supplier-payment.png'),
      fullPage: true,
      animations: 'disabled',
    });
    await page
      .getByRole('heading', { name: 'Registrar pago', exact: true })
      .scrollIntoViewIfNeeded();
    await page.screenshot({
      path: info.outputPath('supplier-payment-context.png'),
      fullPage: true,
      animations: 'disabled',
    });
    await submit.click();
    await expect(page.getByRole('alert').filter({ hasText: 'No se pudo registrar' })).toBeVisible();
    await expect(amount).toHaveValue('0.10');
    expect(payments).toEqual([
      { id: 'design-bill', amount: '0.10', paymentMethodId: 1, reference: 'OP-EXACTA' },
    ]);
    failPayment = false;
    await submit.click();
    await expect(page).toHaveURL(/\/accounts-payable$/);
    await page.goto(url);
    await page.getByRole('button', { name: 'Reversar', exact: true }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Reversar pago', exact: true });
    await expect(dialog).toContainText('18.00');
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    expect(reversals).toHaveLength(0);
    await page.getByRole('button', { name: 'Reversar', exact: true }).click();
    await dialog.getByRole('button', { name: 'Confirmar reversión' }).click();
    await expect(dialog.getByRole('alert')).toContainText('No se pudo reversar');
    failReverse = false;
    await dialog.getByRole('button', { name: 'Confirmar reversión' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(summary).toContainText('118.00');
    await expect(page.getByText('Reversado', { exact: true })).toBeVisible();
    expect(reversals).toEqual([
      { id: 'design-bill', paymentId: 9 },
      { id: 'design-bill', paymentId: 9 },
    ]);
  });

test('view-only supplier bill hides payment and reversal actions', async ({ page }) => {
  const seed = await getE2ESeed();
  await page.route(/\/api\/auth\/(login|select-branch)(?:\?|$)/, async (route) => {
    const response = await route.fetch();
    const body = await response.json();
    if (body.user) body.user.permissions = ['accounts_payable.view'];
    await route.fulfill({ response, json: body });
  });
  await login(page, seed.username, seed.password);
  await page.route('**/api/supplier-bills/design-bill', (route) =>
    route.fulfill({ json: fixture() }),
  );
  await page.goto(`${getE2EBaseUrl()}/supplier-bill/design-bill/pay`);
  await expect(page.getByText('Consulta de factura.', { exact: false })).toBeVisible();
  await expect(page.getByTestId('supplier-bill-pay-submit-btn')).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Reversar', exact: true })).toHaveCount(0);
});
