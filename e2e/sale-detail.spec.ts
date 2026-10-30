import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const noOverflow = async (page: Page) => {
  expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(
    false,
  );
};

function fixture(id: string) {
  return {
    id,
    status: 'COMPLETED',
    currency: 'PEN',
    hasInvoice: false,
    invoiceFullNumber: null,
    series: 'NV01',
    number: '000001',
    subtotal: '25.50',
    discountTotal: '0.00',
    taxTotal: '0.00',
    grandTotal: '25.50',
    paidTotal: '0.00',
    changeTotal: '0.00',
    balanceDue: '25.50',
    paymentState: 'due',
    sunatState: 'not_applicable',
    issuedDate: '2026-10-29T00:00:00+00:00',
    issuedAt: '2026-10-29T12:00:00-05:00',
    createdAt: '2026-10-29T12:00:00-05:00',
    dueDate: '2026-11-29T00:00:00+00:00',
    customer: {
      id: 1,
      businessName: 'Cliente de prueba',
      ruc: '20123456789',
      fiscalAddress: 'Dirección de prueba',
      email: 'cliente@example.test',
    },
    payments: [] as {
      payment_method_id: number;
      method_name: string;
      amount: string;
      paid_at: string;
      reference: string;
    }[],
  };
}

async function mockItems(page: Page, id: string) {
  await page.route(`**/api/sales/${id}/items*`, (route) =>
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        member: [
          {
            id: 1,
            lineNo: 1,
            nameSnapshot: 'Producto de prueba',
            qty: '1.000',
            unitPrice: '25.5000',
            netAmount: '25.50',
          },
        ],
      }),
    }),
  );
}

test('compact sales cards open the real detail and preview on desktop and mobile', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/billing/sales`);
  const card = page.locator('.sale-summary-card').first();
  await expect(card).toBeVisible({ timeout: 30000 });
  await expect(card.locator('.sale-summary-card__badges')).toBeInViewport({ ratio: 1 });
  const title = (await card.locator('.sale-summary-card__customer').boundingBox())!;
  const total = (await card.locator('.sale-summary-card__total').boundingBox())!;
  expect(Math.abs(title.y - total.y)).toBeLessThan(3);
  await noOverflow(page);
  await card.scrollIntoViewIfNeeded();
  await page.screenshot({
    path: testInfo.outputPath('sales-compact-mobile.png'),
    animations: 'disabled',
  });
  const saleResponse = page.waitForResponse((response) =>
    /\/api\/sales\/[^/?]+$/.test(new URL(response.url()).pathname),
  );
  const itemsResponse = page.waitForResponse((response) =>
    /\/api\/sales\/[^/]+\/items/.test(new URL(response.url()).pathname),
  );
  await page.locator('button.nb-datagrid__card-open').first().click();
  const sale = await (await saleResponse).json();
  const itemsResult = await itemsResponse;
  expect(itemsResult.status()).toBe(200);
  const body = await itemsResult.json();
  const items = Array.isArray(body) ? body : (body.member ?? body['hydra:member']);
  expect(items.length).toBeGreaterThan(0);
  await expect(page).toHaveURL(new RegExp(`/billing/sales/${sale.id}$`));
  await expect(page.getByTestId('sale-detail-items').locator('tbody tr')).toHaveCount(items.length);
  await expect(
    page.getByRole('heading', { name: 'Pagos y vencimiento', exact: true }),
  ).toBeVisible();
  const main = (await page.locator('.nb-detail-layout__main').boundingBox())!;
  const aside = (await page.locator('.nb-detail-layout__aside').boundingBox())!;
  expect(aside.y).toBeGreaterThan(main.y);
  await noOverflow(page);
  await page.screenshot({
    path: testInfo.outputPath('sale-detail-mobile.png'),
    animations: 'disabled',
  });
  await page.setViewportSize({ width: 1440, height: 960 });
  await expect(page.locator('.nb-detail-layout')).toHaveCSS('grid-template-columns', /\d+.* \d+/);
  await expect(page.locator('.app')).not.toHaveClass(/screen-x-small/);
  await expect(page.locator('.nb-admin-bottom-nav')).toBeHidden();
  await page.getByRole('heading', { name: 'Cliente', exact: true }).scrollIntoViewIfNeeded();
  await page.screenshot({
    path: testInfo.outputPath('sale-detail-desktop.png'),
    animations: 'disabled',
  });
  await page.emulateMedia({ colorScheme: 'dark' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(10, 11, 13)');
  const darkPdf = page.getByRole('button', { name: 'PDF', exact: true }).locator('img');
  await expect(darkPdf).toHaveAttribute('src', /pdf-dark\.svg$/);
  await expect(darkPdf).toHaveJSProperty('complete', true);
  expect(await darkPdf.evaluate((element) => (element as HTMLImageElement).naturalWidth)).toBe(22);
  await page.screenshot({
    path: testInfo.outputPath('sale-detail-desktop-dark.png'),
    animations: 'disabled',
  });
  const receipt = page.waitForResponse((response) =>
    response.url().includes(`/receipt/${sale.id}/ticket?preview=true`),
  );
  await page.getByRole('button', { name: 'Vista previa', exact: true }).click();
  expect((await receipt).status()).toBe(200);
  await expect(page.getByRole('dialog', { name: 'Vista previa', exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'Cerrar', exact: true }).click();
});

test('collecting from detail targets only that sale and refreshes its balance', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  // Financial writes must never reach the real ERP from this presentation test.
  await page.route('**/api/sales/**', (route) => {
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(route.request().method())) return route.abort();
    return route.fallback();
  });
  let sale = fixture(seed.saleId);
  await page.route(`**/api/sales/${seed.saleId}`, (route) => route.fulfill({ json: sale }));
  await mockItems(page, seed.saleId);
  let submitted: Record<string, unknown> | undefined;
  await page.route(`**/api/sales/${seed.saleId}/collect`, async (route) => {
    submitted = route.request().postDataJSON();
    sale = {
      ...sale,
      balanceDue: '0.00',
      paidTotal: '25.50',
      paymentState: 'paid',
      payments: [
        {
          payment_method_id: 1,
          method_name: 'Efectivo',
          amount: '25.50',
          paid_at: '2026-10-29',
          reference: 'Prueba UI',
        },
      ],
    };
    await route.fulfill({ json: sale });
  });
  const fifoCalls: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('collect-on-account')) fifoCalls.push(request.url());
  });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/billing/sales/${seed.saleId}`);
  await expect(page.getByRole('button', { name: 'Registrar pago', exact: true })).toBeVisible();
  const designIcons = page.locator('.sale-detail img');
  await expect(designIcons).toHaveCount(3);
  for (const icon of await designIcons.all()) {
    await expect(icon).toHaveJSProperty('complete', true);
    expect(
      await icon.evaluate((element) => (element as HTMLImageElement).naturalWidth),
    ).toBeGreaterThan(0);
    const expected = (await icon.getAttribute('src'))!.includes('arrow-right') ? 16 : 20;
    expect((await icon.boundingBox())!.width).toBe(expected);
  }
  await page.getByRole('button', { name: 'Registrar pago', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Registrar pago de esta venta', exact: true });
  await expect(dialog.getByRole('spinbutton')).toHaveValue('25.50');
  await dialog.getByRole('button', { name: 'Abrir Método de pago', exact: true }).click();
  await page.getByRole('option', { name: /^Efectivo$/i }).click();
  await expect(dialog.getByRole('combobox', { name: 'Método de pago', exact: true })).toHaveValue(
    'Efectivo',
  );
  await dialog.getByRole('textbox', { name: 'Referencia', exact: true }).fill('Prueba UI');
  await expect(dialog.getByRole('combobox', { name: 'Método de pago', exact: true })).toHaveValue(
    'Efectivo',
  );
  await page.screenshot({
    path: testInfo.outputPath('sale-collect-dialog.png'),
    animations: 'disabled',
  });
  await dialog.getByRole('button', { name: 'Confirmar pago', exact: true }).click();
  await expect(dialog).toBeHidden();
  await expect(page.getByRole('button', { name: 'Registrar pago', exact: true })).toHaveCount(0);
  expect(submitted).toMatchObject({
    id: seed.saleId,
    amount: '25.50',
    paymentMethodId: 1,
    reference: 'Prueba UI',
  });
  expect(fifoCalls).toEqual([]);
  await expect(page.getByTestId('sale-detail-payments')).toContainText('Prueba UI');
});

test('view-only details hide mutations and handle missing or inaccessible sales', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  // UI grants only; the seeded server session retains its normal test permissions.
  await page.route(/\/api\/auth\/(login|select-branch)(?:\?|$)/, async (route) => {
    const response = await route.fetch();
    const body = await response.json();
    if (body.user) body.user.permissions = ['sales.view'];
    await route.fulfill({ response, json: body });
  });
  let mode = 200;
  await page.route(`**/api/sales/${seed.saleId}`, (route) =>
    route.fulfill({ status: mode, json: mode === 200 ? fixture(seed.saleId) : {} }),
  );
  await mockItems(page, seed.saleId);
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/billing/sales/${seed.saleId}`);
  await expect(page.getByRole('heading', { name: 'Cliente', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Registrar pago', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Anular venta', exact: true })).toHaveCount(0);
  mode = 404;
  await page.reload();
  await expect(page.getByText('Venta no encontrada', { exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Reintentar', exact: true })).toHaveCount(0);
  mode = 403;
  await page.reload();
  await expect(page.getByText('Acceso restringido', { exact: true })).toBeVisible();
  mode = 503;
  await page.reload();
  await expect(page.getByRole('button', { name: 'Reintentar', exact: true })).toBeVisible();
  mode = 200;
  await page.getByRole('button', { name: 'Reintentar', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Cliente', exact: true })).toBeVisible();
});
