import { expect, test } from '@playwright/test';
import { parseApiCollection } from './helpers/api';
import { login } from './helpers/auth';
import { loginViaApi } from './helpers/fiscal';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('registra una factura por pagar, la paga, la reversa y conserva la auditoría', async ({ page, request }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

  const [suppliersResponse, branchesResponse, categoriesResponse, methodsResponse] = await Promise.all([
    request.get(`${BASE_URL}/api/suppliers?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/branches?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/expense-categories?itemsPerPage=100`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/payment-methods?itemsPerPage=100`, { headers: auth.headers }),
  ]);

  for (const response of [suppliersResponse, branchesResponse, categoriesResponse, methodsResponse]) {
    expect(response.ok(), `${response.url()} debe responder 2xx`).toBeTruthy();
  }

  const supplier = parseApiCollection<{ id: string; ruc?: string; businessName?: string }>(await suppliersResponse.json())
    .find((item) => item.ruc === E2E_SEED.supplierRuc);
  const branch = parseApiCollection<{ id: string; name?: string }>(await branchesResponse.json())
    .find((item) => item.name === E2E_SEED.branchName);
  const category = parseApiCollection<{ id: number }>(await categoriesResponse.json())[0];
  const cash = parseApiCollection<{ id: number; name?: string }>(await methodsResponse.json())
    .find((item) => /efectivo/i.test(item.name ?? ''));

  if (!supplier || !branch || !category || !cash) {
    throw new Error('Faltan datos E2E para crear la cuenta por pagar');
  }

  const today = new Date().toISOString().slice(0, 10);
  const number = String(Date.now()).slice(-8);
  const createResponse = await request.post(`${BASE_URL}/api/supplier-bills`, {
    headers: auth.headers,
    data: {
      supplier: `/api/suppliers/${supplier.id}`,
      branch: `/api/branches/${branch.id}`,
      category: `/api/expense-categories/${category.id}`,
      documentType: 'FACTURA',
      series: 'F001',
      number,
      issueDate: today,
      dueDate: today,
      currency: 'PEN',
      amount: '118.00',
      note: 'CxP E2E',
    },
  });
  const createBody = await createResponse.text();
  expect(createResponse.ok(), createBody).toBeTruthy();
  const created = JSON.parse(createBody) as { id: string; balanceDue: string };
  expect(Number(created.balanceDue)).toBe(118);

  await page.goto(`${BASE_URL}/#/accounts-payable`);
  const billRow = page.getByRole('row').filter({ hasText: number }).first();
  await expect(billRow).toBeVisible({ timeout: 15000 });
  await billRow.getByRole('button', { name: 'Pagar' }).click();

  await expect(page.getByText(/Saldo pendiente.*118[.,]00/)).toBeVisible();
  await page.getByLabel(/Monto/).fill('118.00');
  const methodInput = page.getByText('Método de pago').locator('..').getByRole('combobox');
  await methodInput.fill(cash.name ?? 'Efectivo');
  await page.locator('.nb-form__lookup-menu button[role="option"]')
    .filter({ hasText: cash.name ?? 'Efectivo' }).first().click();
  await page.getByTestId('supplier-bill-pay-submit-btn').click();
  await expect(page).toHaveURL(/#\/accounts-payable/);

  const paidResponse = await request.get(`${BASE_URL}/api/supplier-bills/${created.id}`, { headers: auth.headers });
  expect(paidResponse.ok()).toBeTruthy();
  const paid = await paidResponse.json() as {
    status: string;
    balanceDue: string;
    payments?: Array<{ id: number; amount: string; reversedAt?: string | null }>;
  };
  expect(paid.status).toBe('PAID');
  expect(Number(paid.balanceDue)).toBe(0);
  expect(paid.payments?.some((payment) => Number(payment.amount) === 118)).toBeTruthy();

  await page.goto(`${BASE_URL}/#/supplier-bill/${created.id}/pay`);
  await expect(page.getByText(/Historial de pagos/)).toBeVisible();
  page.on('dialog', (dialog) => dialog.accept());
  await page.getByRole('button', { name: 'Reversar' }).click();
  await expect(page.getByText('Pago reversado')).toBeVisible();
  await expect(page.getByText(/Saldo pendiente.*118[.,]00/)).toBeVisible();

  const reversedResponse = await request.get(`${BASE_URL}/api/supplier-bills/${created.id}`, { headers: auth.headers });
  const reversed = await reversedResponse.json() as {
    status: string;
    balanceDue: string;
    payments?: Array<{ amount: string; reversedAt?: string | null }>;
  };
  expect(reversed.status).toBe('PENDING');
  expect(Number(reversed.balanceDue)).toBe(118);
  expect(reversed.payments?.some((payment) => payment.reversedAt)).toBeTruthy();

  const ledgerResponse = await request.get(
    `${BASE_URL}/api/general-ledger/ledger?account=421&from=${today}&to=${today}`,
    { headers: auth.headers },
  );
  expect(ledgerResponse.ok()).toBeTruthy();
  const ledger = await ledgerResponse.json() as { lines: Array<{ description: string }> };
  expect(ledger.lines.some((line) => line.description === 'Pago de factura de proveedor')).toBeTruthy();
  expect(ledger.lines.some((line) => line.description === 'Reversión de pago de factura de proveedor')).toBeTruthy();

  const trialResponse = await request.get(
    `${BASE_URL}/api/general-ledger/trial-balance?period=${today.slice(0, 7).replace('-', '')}`,
    { headers: auth.headers },
  );
  expect(trialResponse.ok()).toBeTruthy();
  expect((await trialResponse.json()).balanced).toBe(true);

  await page.goto(`${BASE_URL}/#/accounting`);
  await page.getByRole('tab', { name: 'Diario', exact: true }).click();
  await expect(page.getByText(/Factura de proveedor/).first()).toBeVisible({ timeout: 15000 });
});
