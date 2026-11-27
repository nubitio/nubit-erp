import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import { loginAtSeedBranchViaApi } from './helpers/fiscal';
import { parseApiCollection } from './helpers/api';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('filtra la deuda por cliente, registra un abono y muestra el historial', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await ensureOpenCashSession(page, BASE_URL);
  const auth = await loginAtSeedBranchViaApi(request, BASE_URL, seed.username, seed.password);

  const [productsResponse, warehousesResponse, methodsResponse] = await Promise.all([
    request.get(`${BASE_URL}/api/products?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/warehouses?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/payment-methods?itemsPerPage=100`, { headers: auth.headers }),
  ]);
  for (const response of [productsResponse, warehousesResponse, methodsResponse]) {
    expect(response.ok(), `${response.url()} debe responder 2xx`).toBeTruthy();
  }

  const customerName = `Cliente CxC E2E ${Date.now()}`;
  const customerResponse = await request.post(`${BASE_URL}/api/customers`, {
    headers: auth.headers,
    data: {
      businessName: customerName,
      ruc: String(Date.now()).slice(-8),
      isActive: true,
    },
  });
  expect(customerResponse.ok(), await customerResponse.text()).toBeTruthy();
  const customer = (await customerResponse.json()) as { id: number };
  const product = parseApiCollection<{ id: string; code?: string; salePrice: string }>(
    await productsResponse.json(),
  ).find((item) => item.code === E2E_SEED.productCode);
  const warehouse = parseApiCollection<{ id: string; name?: string }>(
    await warehousesResponse.json(),
  ).find((item) => item.name === E2E_SEED.warehouseName);
  const paymentMethod = parseApiCollection<{ id: number; name?: string }>(
    await methodsResponse.json(),
  ).find((item) => /efectivo/i.test(item.name ?? ''));
  if (!product || !warehouse || !paymentMethod) {
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
      writeItems: [
        {
          product: `/api/products/${product.id}`,
          qty: 1,
          unitPrice: '10.00',
          description: E2E_SEED.productName,
          discountAmount: '0.00',
          netAmount: '10.00',
        },
      ],
    },
  });
  const createBody = await createResponse.text();
  expect(createResponse.ok(), createBody).toBeTruthy();
  const createdSale = JSON.parse(createBody) as { id: string; balanceDue: string };
  expect(Number(createdSale.balanceDue)).toBe(9);

  await test.step('rechazar crédito como cobro sin alterar el saldo', async () => {
    const credit = parseApiCollection<{ id: number; settlementBehavior?: string }>(
      await methodsResponse.json(),
    ).find((method) => method.settlementBehavior === 'RECEIVABLE');
    expect(credit, 'Debe existir el método crédito').toBeTruthy();
    const rejected = await request.post(`${BASE_URL}/api/sales/${createdSale.id}/collect`, {
      headers: auth.headers,
      data: { id: createdSale.id, amount: 4, paymentMethodId: credit!.id },
    });
    expect(rejected.status()).toBe(422);
    const unchanged = await request.get(`${BASE_URL}/api/sales/${createdSale.id}`, {
      headers: auth.headers,
    });
    const body = await unchanged.json();
    expect(Number(body.balanceDue)).toBe(9);
    expect(Number(body.paidTotal)).toBe(1);
  });

  await test.step('filtrar por cliente y registrar un pago parcial', async () => {
    await page.goto(`${BASE_URL}/accounts-receivable`);

    await expect(page.getByRole('table', { name: 'Por cliente' })).toBeVisible();
    // Filters live in the toolbar above the grid, not inside the table header.
    await page.getByRole('textbox', { name: 'Filtrar Cliente' }).fill(customerName);

    const row = page.getByRole('row').filter({ hasText: customerName }).first();
    await expect(row).toContainText(/9[.,]00/, { timeout: 15000 });
    await row.getByRole('button', { name: 'Cobrar' }).click();

    const dialog = page.getByRole('dialog', { name: 'Registrar pago' });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByText(/Saldo pendiente.*9[.,]00/)).toBeVisible();
    await dialog.getByLabel(/Monto/).fill('4.00');
    await dialog.getByLabel('Referencia').fill('OP-E2E-001');
    const methodInput = dialog.getByText('Método de pago').locator('..').getByRole('combobox');
    await methodInput.click();
    await methodInput.fill(paymentMethod.name ?? 'Efectivo');
    await expect(
      page.getByRole('option', { name: paymentMethod.name ?? 'Efectivo', exact: true }),
    ).toBeVisible();
    await page.getByRole('option', { name: paymentMethod.name ?? 'Efectivo', exact: true }).click();
    await dialog.getByTestId('receivable-payment-submit-btn').click();

    await expect(dialog.getByText('Aplicación')).toBeVisible({ timeout: 15000 });
    await dialog.getByRole('button', { name: 'Listo' }).click();
    await expect(dialog).toBeHidden();
  });

  const detailResponse = await request.get(`${BASE_URL}/api/sales/${createdSale.id}`, {
    headers: auth.headers,
  });
  expect(detailResponse.ok()).toBeTruthy();
  const updatedSale = (await detailResponse.json()) as {
    paidTotal: string;
    balanceDue: string;
    payments?: Array<{ amount?: string; method_name?: string; reference?: string }>;
  };
  expect(Number(updatedSale.paidTotal)).toBe(5);
  expect(Number(updatedSale.balanceDue)).toBe(5);
  expect(
    updatedSale.payments?.some(
      (payment) => Number(payment.amount) === 4 && payment.reference === 'OP-E2E-001',
    ),
  ).toBeTruthy();

  await test.step('ver el abono en el detalle del cliente', async () => {
    const updatedRow = page.getByRole('row').filter({ hasText: customerName }).first();
    await expect(updatedRow).toContainText(/5[.,]00/, { timeout: 15000 });

    await updatedRow.getByRole('button', { name: customerName }).click();
    await expect(page.getByRole('heading', { name: customerName })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Pagos recientes' })).toBeVisible({
      timeout: 15000,
    });
    await expect(page.getByText('OP-E2E-001')).toBeVisible();
    await page.getByTestId('receivable-back').click();
    await expect(page.getByRole('table', { name: 'Por cliente' })).toBeVisible();
  });

  await test.step('saldar la cuenta y quitarla de la lista', async () => {
    const pendingRow = page.getByRole('row').filter({ hasText: customerName }).first();
    await pendingRow.getByRole('button', { name: 'Cobrar' }).click();

    const dialog = page.getByRole('dialog', { name: 'Registrar pago' });
    await expect(dialog.getByLabel(/Monto/)).toHaveValue('5.00');
    await dialog.getByLabel('Referencia').fill('PAGO-FINAL-E2E');
    await dialog.getByRole('combobox').click();
    await dialog.getByRole('combobox').fill(paymentMethod.name ?? 'Efectivo');
    await expect(
      page.getByRole('option', { name: paymentMethod.name ?? 'Efectivo', exact: true }),
    ).toBeVisible();
    await page.getByRole('option', { name: paymentMethod.name ?? 'Efectivo', exact: true }).click();
    await dialog.getByTestId('receivable-payment-submit-btn').click();
    await dialog.getByRole('button', { name: 'Listo' }).click();
    await expect(dialog).toBeHidden();

    await expect(page.getByRole('row').filter({ hasText: customerName })).toHaveCount(0, {
      timeout: 15000,
    });
  });

  const settledResponse = await request.get(`${BASE_URL}/api/sales/${createdSale.id}`, {
    headers: auth.headers,
  });
  const settledSale = (await settledResponse.json()) as { paidTotal: string; balanceDue: string };
  expect(Number(settledSale.paidTotal)).toBe(10);
  expect(Number(settledSale.balanceDue)).toBe(0);
});
