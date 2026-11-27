import { expect, test, type APIRequestContext, type Locator, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import { loginAtSeedBranchViaApi } from './helpers/fiscal';
import { parseApiCollection } from './helpers/api';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

interface Method {
  id: number;
  name?: string;
  settlementBehavior?: string;
  isActive?: boolean;
}

/** Picks `name` in the payment-method combobox that sits inside `scope`. */
async function pickMethod(page: Page, scope: Locator, name: string) {
  const combobox = scope.getByRole('combobox');
  await combobox.click();
  await combobox.fill(name);
  const option = page.getByRole('option', { name, exact: true });
  await expect(option).toBeVisible();
  await option.click();
}

test('una cobranza con dos medios deja saldo a favor y una cobranza parcial se puede revertir', async ({
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
  const product = parseApiCollection<{ id: string; code?: string }>(
    await productsResponse.json(),
  ).find((item) => item.code === E2E_SEED.productCode);
  const warehouse = parseApiCollection<{ id: string; name?: string }>(
    await warehousesResponse.json(),
  ).find((item) => item.name === E2E_SEED.warehouseName);
  const collectable = parseApiCollection<Method>(await methodsResponse.json()).filter(
    (method) => method.settlementBehavior === 'COLLECTED' && method.isActive !== false,
  );
  const cash = collectable.find((method) => /efectivo/i.test(method.name ?? ''));
  const other = collectable.find((method) => method.id !== cash?.id);
  if (!product || !warehouse || !cash || !other) {
    throw new Error('Faltan datos E2E: producto, almacén y dos medios de cobro');
  }

  const now = new Date();
  const today = now.toISOString().slice(0, 10);
  const createCustomer = async (label: string) => {
    const name = `${label} ${Date.now()}`;
    const response = await request.post(`${BASE_URL}/api/customers`, {
      headers: auth.headers,
      data: { businessName: name, ruc: String(Date.now()).slice(-8), isActive: true },
    });
    expect(response.ok(), await response.text()).toBeTruthy();
    return { id: ((await response.json()) as { id: number }).id, name };
  };
  const createCreditSale = async (customerId: number) => {
    const response = await request.post(`${BASE_URL}/api/sales`, {
      headers: auth.headers,
      data: {
        issuedAt: now.toISOString(),
        issuedDate: today,
        dueDate: today,
        customer: `/api/customers/${customerId}`,
        warehouse: `/api/warehouses/${warehouse.id}`,
        settlements: [{ type: 'RECEIVABLE', amount: '10.00' }],
        emitInvoice: false,
        currency: 'PEN',
        writeItems: [
          {
            product: `/api/products/${product.id}`,
            qty: 1,
            unitPrice: '10.00',
            netAmount: '10.00',
          },
        ],
      },
    });
    expect(response.ok(), await response.text()).toBeTruthy();
    return (await response.json()) as { id: string };
  };
  const balanceOf = async (api: APIRequestContext, saleId: string) => {
    const response = await api.get(`${BASE_URL}/api/sales/${saleId}`, { headers: auth.headers });
    return Number(((await response.json()) as { balanceDue: string }).balanceDue);
  };
  const openCollectDialog = async (customerName: string) => {
    await page.goto(`${BASE_URL}/accounts-receivable`);
    await page.getByRole('textbox', { name: 'Filtrar Cliente' }).fill(customerName);
    const row = page.getByRole('row').filter({ hasText: customerName }).first();
    await expect(row).toBeVisible({ timeout: 15000 });
    await row.getByRole('button', { name: 'Cobrar' }).click();
    const dialog = page.getByRole('dialog', { name: 'Registrar pago' });
    await expect(dialog).toBeVisible();
    return dialog;
  };

  // --- Dos facturas de 10, un cobro de 25 recibido como 10 + 15 por dos medios ---
  const buyer = await createCustomer('CxC MULTIMEDIO');
  const first = await createCreditSale(buyer.id);
  const second = await createCreditSale(buyer.id);

  await test.step('cobrar 25 con dos medios: se distribuye y el resto queda a favor', async () => {
    const dialog = await openCollectDialog(buyer.name);
    await dialog.getByLabel(/Monto/).fill('25.00');

    await dialog.getByTestId('receivable-payment-add-tender').click();
    await pickMethod(page, dialog.getByTestId('receivable-tender-0'), cash.name ?? 'Efectivo');
    await pickMethod(page, dialog.getByTestId('receivable-tender-1'), other.name ?? '');
    await dialog.getByLabel('Importe de este medio').first().fill('10.00');
    await dialog.getByLabel('Importe de este medio').last().fill('15.00');

    // The preview already says where it goes and what stays as the customer's advance.
    await expect(dialog.getByTestId('receivable-distribution')).toContainText(/Quedarán.*5[.,]00/);

    await dialog.getByTestId('receivable-payment-submit-btn').click();
    await expect(dialog.getByTestId('receivable-receipt')).toBeVisible({ timeout: 15000 });
    await expect(dialog.getByTestId('receivable-receipt')).toContainText(/5[.,]00.*saldo a favor/);
    await dialog.getByRole('button', { name: 'Listo' }).click();
    await expect(dialog).toBeHidden();
  });

  expect(await balanceOf(request, first.id)).toBe(0);
  expect(await balanceOf(request, second.id)).toBe(0);
  const historyResponse = await request.get(
    `${BASE_URL}/api/sales/reports/receivable-customers/${buyer.id}/collections`,
    { headers: auth.headers },
  );
  expect(historyResponse.ok(), await historyResponse.text()).toBeTruthy();
  const history = (await historyResponse.json()) as Array<{
    unapplied: string;
    status: string;
    tenders: Array<{ amount: string }>;
  }>;
  expect(history).toHaveLength(1);
  expect(Number(history[0].unapplied)).toBe(5);
  expect(history[0].tenders.map((tender) => Number(tender.amount)).sort()).toEqual([10, 15]);

  // --- Un cobro parcial de 4 sobre una factura de 10, y se revierte ---
  const partial = await createCustomer('CxC REVERSION');
  const sale = await createCreditSale(partial.id);

  await test.step('cobrar 4 con efectivo', async () => {
    const dialog = await openCollectDialog(partial.name);
    await dialog.getByLabel(/Monto/).fill('4.00');
    await pickMethod(page, dialog.getByTestId('receivable-tender-0'), cash.name ?? 'Efectivo');
    await dialog.getByTestId('receivable-payment-submit-btn').click();
    await expect(dialog.getByText('Aplicación')).toBeVisible({ timeout: 15000 });
    await dialog.getByRole('button', { name: 'Listo' }).click();
  });
  expect(await balanceOf(request, sale.id)).toBe(6);

  await test.step('revertir la cobranza desde el detalle del cliente', async () => {
    const row = page.getByRole('row').filter({ hasText: partial.name }).first();
    await expect(row).toContainText(/6[.,]00/, { timeout: 15000 });
    await row.getByRole('button', { name: partial.name }).click();

    await expect(page.getByRole('heading', { name: 'Cobranzas' })).toBeVisible({ timeout: 15000 });
    await page
      .getByTestId(/^receivable-reverse-/)
      .first()
      .click();
    await page
      .getByRole('alertdialog', { name: 'Revertir cobranza' })
      .getByRole('button', { name: 'Confirmar reversión' })
      .click();
    await expect(page.getByTestId('receivable-collections').getByText('Revertida')).toBeVisible({
      timeout: 15000,
    });
  });
  expect(await balanceOf(request, sale.id)).toBe(10);
});
