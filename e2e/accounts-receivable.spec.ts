import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import { loginViaApi } from './helpers/fiscal';
import { parseApiCollection } from './helpers/api';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('filtra la deuda por cliente, registra un abono y muestra el historial', async ({ page, request }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await ensureOpenCashSession(page, BASE_URL);
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

  const [customersResponse, productsResponse, warehousesResponse, methodsResponse] = await Promise.all([
    request.get(`${BASE_URL}/api/customers?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/products?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/warehouses?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/payment-methods?itemsPerPage=100`, { headers: auth.headers }),
  ]);
  for (const response of [customersResponse, productsResponse, warehousesResponse, methodsResponse]) {
    expect(response.ok(), `${response.url()} debe responder 2xx`).toBeTruthy();
  }

  const customer = parseApiCollection<{ id: number; ruc?: string }>(await customersResponse.json())
    .find((item) => item.ruc === E2E_SEED.customerDni);
  const product = parseApiCollection<{ id: string; code?: string; salePrice: string }>(await productsResponse.json())
    .find((item) => item.code === E2E_SEED.productCode);
  const warehouse = parseApiCollection<{ id: string; name?: string }>(await warehousesResponse.json())
    .find((item) => item.name === E2E_SEED.warehouseName);
  const paymentMethod = parseApiCollection<{ id: number; name?: string }>(await methodsResponse.json())
    .find((item) => /efectivo/i.test(item.name ?? ''));
  if (!customer || !product || !warehouse || !paymentMethod) {
    throw new Error('Faltan datos E2E para crear la cuenta por cobrar');
  }

  const now = new Date();
  const issuedDate = now.toISOString().slice(0, 10);
  const createResponse = await request.post(`${BASE_URL}/api/sales`, {
    headers: auth.headers,
    data: {
      issuedAt: now.toISOString(),
      issuedDate,
      dueDate: issuedDate,
      warehouse: `/api/warehouses/${warehouse.id}`,
      customer: `/api/customers/${customer.id}`,
      paymentMethod: `/api/payment-methods/${paymentMethod.id}`,
      payments: [{ payment_method_id: paymentMethod.id, amount: '1.00' }],
      note: 'Cuenta por cobrar E2E',
      discountPercent: '0.00',
      cashRoundingAdjustment: '0.00',
      emitInvoice: false,
      creditSale: true,
      currency: 'PEN',
      exchangeRate: '1.000000',
      grandTotal: '10.00',
      writeItems: [{
        product: `/api/products/${product.id}`,
        qty: 1,
        unitPrice: '10.00',
        description: E2E_SEED.productName,
        discountAmount: '0.00',
        netAmount: '10.00',
      }],
    },
  });
  const createBody = await createResponse.text();
  expect(createResponse.ok(), createBody).toBeTruthy();
  const createdSale = JSON.parse(createBody) as { id: string; balanceDue: string };
  expect(Number(createdSale.balanceDue)).toBe(9);

  await test.step('rechazar crédito como cobro sin alterar el saldo', async () => {
    const credit = parseApiCollection<{ id: number; settlementBehavior?: string }>(await methodsResponse.json())
      .find((method) => method.settlementBehavior === 'RECEIVABLE');
    expect(credit, 'Debe existir el método crédito').toBeTruthy();
    const rejected = await request.post(`${BASE_URL}/api/sales/${createdSale.id}/collect`, {
      headers: auth.headers,
      data: { id: createdSale.id, amount: 4, paymentMethodId: credit!.id },
    });
    expect(rejected.status()).toBe(422);
    const unchanged = await request.get(`${BASE_URL}/api/sales/${createdSale.id}`, { headers: auth.headers });
    const body = await unchanged.json();
    expect(Number(body.balanceDue)).toBe(9);
    expect(Number(body.paidTotal)).toBe(1);
  });

  await test.step('filtrar por cliente y abrir la deuda', async () => {
    await page.goto(`${BASE_URL}/#/accounts-receivable`);
    const customerFilter = page.getByRole('table', { name: 'Cuentas por cobrar' })
      .locator('.nb-datagrid__filter-select')
      .first();
    await customerFilter.fill(E2E_SEED.customerName);
    await page.locator('.nb-form__lookup-menu button[role="option"]')
      .filter({ hasText: E2E_SEED.customerName }).first().click();

    const row = page.getByRole('row').filter({ has: page.locator(`[data-sale-id="${createdSale.id}"]`) });
    await expect(row).toContainText(/9[.,]00/, { timeout: 15000 });
    await row.locator('.nb-datagrid__expand-button').click();
    await expect(row.locator('xpath=following-sibling::tr[1]')).toContainText(paymentMethod.name ?? 'Efectivo');
    await row.getByRole('button', { name: 'Cobrar' }).click();
  });

  await test.step('registrar un pago parcial', async () => {
    await expect(page.getByRole('dialog', { name: 'Registrar cobro de venta' })).toBeVisible();
    await expect(page.getByRole('dialog').getByText(/Saldo.*9[.,]00/)).toBeVisible();
    await page.getByLabel(/Monto/).fill('4.00');
    await page.getByLabel('Referencia').fill('OP-E2E-001');
    const methodInput = page.getByText('Método de pago').locator('..').getByRole('combobox');
    await methodInput.fill(paymentMethod.name ?? 'Efectivo');
    await page.locator('.nb-form__lookup-menu button[role="option"]')
      .filter({ hasText: paymentMethod.name ?? 'Efectivo' }).first().click();
    await page.getByTestId('sale-collect-submit-btn').click();
    await expect(page.getByRole('dialog', { name: 'Registrar cobro de venta' })).toBeHidden({ timeout: 15000 });
  });

  const detailResponse = await request.get(`${BASE_URL}/api/sales/${createdSale.id}`, { headers: auth.headers });
  expect(detailResponse.ok()).toBeTruthy();
  const updatedSale = (await detailResponse.json()) as {
    paidTotal: string;
    balanceDue: string;
    payments?: Array<{ amount?: string; method_name?: string; reference?: string }>;
  };
  expect(Number(updatedSale.paidTotal)).toBe(5);
  expect(Number(updatedSale.balanceDue)).toBe(5);
  expect(updatedSale.payments?.some(
    (payment) => Number(payment.amount) === 4 && payment.reference === 'OP-E2E-001',
  )).toBeTruthy();

  const updatedRow = page.getByRole('row').filter({ has: page.locator(`[data-sale-id="${createdSale.id}"]`) });
  await expect(updatedRow).toContainText(/5[.,]00/, { timeout: 15000 });
  const detail = updatedRow.locator('xpath=following-sibling::tr[1]');
  await expect(detail).toContainText(paymentMethod.name ?? 'Efectivo');
  await expect(detail).toContainText('OP-E2E-001');

  await test.step('conservar la cuenta y sus abonos después del pago completo', async () => {
    await updatedRow.getByRole('button', { name: 'Cobrar', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Registrar cobro de venta' });
    await expect(dialog.getByLabel(/Monto/)).toHaveValue('5.00');
    await dialog.getByLabel('Referencia').fill('PAGO-FINAL-E2E');
    await dialog.getByRole('combobox').fill(paymentMethod.name ?? 'Efectivo');
    await page.getByRole('option', { name: paymentMethod.name ?? 'Efectivo', exact: true }).click();
    await dialog.getByTestId('sale-collect-submit-btn').click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('row').filter({ has: page.locator(`[data-sale-id="${createdSale.id}"]`) })).toHaveCount(0);
    await page.getByRole('button', { name: /Estado de cuenta/ }).click();
    await page.getByRole('option', { name: 'Pagadas', exact: true }).click();
    const paidRow = page.getByRole('row').filter({ has: page.locator(`[data-sale-id="${createdSale.id}"]`) });
    await expect(paidRow).toContainText('Cobrada');
    await expect(paidRow.getByRole('button', { name: 'Cobrar', exact: true })).toHaveCount(0);
    if (!(await paidRow.locator('.nb-datagrid__expand-button').getAttribute('class'))?.includes('is-expanded')) {
      await paidRow.locator('.nb-datagrid__expand-button').click();
    }
    await expect(paidRow.locator('xpath=following-sibling::tr[1]')).toContainText('PAGO-FINAL-E2E');
  });

});
