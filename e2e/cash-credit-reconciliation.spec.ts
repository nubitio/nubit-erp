import { expect, test, type Page } from '@playwright/test';
import { strFromU8, unzipSync } from 'fflate';
import { parseApiCollection } from './helpers/api';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import { loginViaApi } from './helpers/fiscal';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

async function exportedCustomerRow(
  page: Page,
  customerName: string,
): Promise<Record<string, string>> {
  const response = await page.request.get(`${BASE_URL}/api/sales/report`, {
    params: { report: 'payment-method-breakdown' },
  });
  expect(response.ok(), await response.text()).toBeTruthy();
  const workbook = unzipSync(new Uint8Array(await response.body()));
  const rows = await page.evaluate(
    ({ sharedXml, sheetXml }) => {
      const parser = new DOMParser();
      const shared = Array.from(
        parser.parseFromString(sharedXml, 'application/xml').querySelectorAll('si'),
      ).map((entry) => entry.textContent ?? '');
      return Array.from(
        parser.parseFromString(sheetXml, 'application/xml').querySelectorAll('row'),
      ).map((row) =>
        Array.from(row.querySelectorAll('c')).map((cell) => {
          const value = cell.querySelector('v')?.textContent ?? '';
          return cell.getAttribute('t') === 's' ? shared[Number(value)] : value;
        }),
      );
    },
    {
      sharedXml: strFromU8(workbook['xl/sharedStrings.xml']),
      sheetXml: strFromU8(workbook['xl/worksheets/sheet1.xml']),
    },
  );
  const row = rows.slice(1).find((values) => values.includes(customerName));
  expect(row, 'El Excel debe contener la venta del cliente').toBeTruthy();
  return Object.fromEntries(rows[0].map((header, index) => [header, row![index]]));
}

test('María: crédito 13.88, abono 3.88, Excel y cierre coinciden sin sumar deuda a caja', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const sessionId = await ensureOpenCashSession(page, BASE_URL);
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);
  const summaryResponse = await page.request.get(
    `${BASE_URL}/api/cash-sessions/${sessionId}/summary`,
  );
  expect(summaryResponse.ok()).toBeTruthy();
  const openingRows = (await summaryResponse.json()) as Array<{
    paymentMethodId: number;
    expectedAmount: string;
  }>;
  const openingExpected = Number(
    openingRows.find((row) => row.paymentMethodId === 1)?.expectedAmount,
  );
  expect(Number.isFinite(openingExpected)).toBeTruthy();

  const customerName = `SRA MARIA E2E ${Date.now()}`;
  const customerResponse = await request.post(`${BASE_URL}/api/customers`, {
    headers: auth.headers,
    data: { businessName: customerName, ruc: String(Date.now()).slice(-8), isActive: true },
  });
  expect(customerResponse.ok(), await customerResponse.text()).toBeTruthy();
  const customer = (await customerResponse.json()) as { id: number };
  const productsResponse = await request.get(`${BASE_URL}/api/products?itemsPerPage=100`, {
    headers: auth.headers,
  });
  const warehousesResponse = await request.get(`${BASE_URL}/api/warehouses?itemsPerPage=100`, {
    headers: auth.headers,
  });
  expect(productsResponse.ok()).toBeTruthy();
  expect(warehousesResponse.ok()).toBeTruthy();
  const product = parseApiCollection<{ id: string; code: string }>(
    await productsResponse.json(),
  ).find((item) => item.code === E2E_SEED.productCode);
  const warehouse = parseApiCollection<{ id: string; name: string }>(
    await warehousesResponse.json(),
  ).find((item) => item.name === E2E_SEED.warehouseName);
  expect(product).toBeTruthy();
  expect(warehouse).toBeTruthy();

  const now = new Date();
  const saleResponse = await request.post(`${BASE_URL}/api/sales`, {
    headers: auth.headers,
    data: {
      issuedAt: now.toISOString(),
      issuedDate: now.toISOString().slice(0, 10),
      customer: `/api/customers/${customer.id}`,
      warehouse: `/api/warehouses/${warehouse!.id}`,
      settlements: [{ type: 'RECEIVABLE', amount: '13.88' }],
      emitInvoice: true,
      currency: 'PEN',
      grandTotal: '13.88',
      writeItems: [
        { product: `/api/products/${product!.id}`, qty: 1, unitPrice: '13.88', netAmount: '13.88' },
      ],
    },
  });
  expect(saleResponse.ok(), await saleResponse.text()).toBeTruthy();
  const sale = (await saleResponse.json()) as { id: string; balanceDue: string; paidTotal: string };
  expect(Number(sale.balanceDue)).toBe(13.88);
  expect(Number(sale.paidTotal)).toBe(0);

  async function assertCash(expected: number) {
    const dashboardResponse = await page.request.get(`${BASE_URL}/api/cash-registers/dashboard`);
    expect(dashboardResponse.ok()).toBeTruthy();
    const dashboard = (await dashboardResponse.json()) as Array<{
      cashSessionId: number;
      balance: number;
    }>;
    expect(
      Number(dashboard.find((row) => String(row.cashSessionId) === sessionId)?.balance),
    ).toBeCloseTo(expected, 2);
    const summary = await page.request.get(`${BASE_URL}/api/cash-sessions/${sessionId}/summary`);
    expect(summary.ok()).toBeTruthy();
    const rows = (await summary.json()) as Array<{
      paymentMethodId: number;
      expectedAmount: string;
    }>;
    expect(Number(rows.find((row) => row.paymentMethodId === 1)?.expectedAmount)).toBeCloseTo(
      expected,
      2,
    );
    const movementsResponse = await page.request.get(
      `${BASE_URL}/api/cash-sessions/${sessionId}/movements`,
    );
    expect(movementsResponse.ok()).toBeTruthy();
    const movements = parseApiCollection<{ type: string; amount: string }>(
      await movementsResponse.json(),
    );
    const gridBalance = movements.reduce(
      (sum, row) => sum + (row.type === 'income' ? 1 : -1) * Number(row.amount),
      0,
    );
    expect(gridBalance).toBeCloseTo(expected, 2);
  }

  await assertCash(openingExpected);
  const unpaidRow = await exportedCustomerRow(page, customerName);
  const creditHeader = Object.keys(unpaidRow).find((header) =>
    /cr[eé]dito pendiente/i.test(header),
  );
  expect(creditHeader).toBeTruthy();
  expect(Number(unpaidRow[creditHeader!])).toBe(13.88);
  expect(Number(unpaidRow['Total pagado'])).toBe(0);
  expect(Number(unpaidRow['Total comprobante'])).toBe(13.88);

  const collectResponse = await request.post(`${BASE_URL}/api/sales/${sale.id}/collect`, {
    headers: auth.headers,
    data: { id: sale.id, amount: 3.88, paymentMethodId: 1, reference: 'ABONO-MARIA-E2E' },
  });
  expect(collectResponse.ok(), await collectResponse.text()).toBeTruthy();
  await assertCash(openingExpected + 3.88);
  const paidRow = await exportedCustomerRow(page, customerName);
  expect(Number(paidRow[creditHeader!])).toBe(10);
  expect(Number(paidRow['Efectivo'])).toBe(3.88);
  expect(Number(paidRow['Total pagado'])).toBe(3.88);
  expect(Number(paidRow['Total comprobante'])).toBe(13.88);

  await page.goto(`${BASE_URL}/#/cash-session/${sessionId}/close`);
  const expectedAmount = (openingExpected + 3.88).toFixed(2);
  const cashPanel = page
    .locator('section')
    .filter({ has: page.getByRole('heading', { name: 'Cuadre de efectivo', exact: true }) })
    .last();
  await expect(cashPanel).toContainText(expectedAmount);
  await page
    .getByRole('spinbutton', { name: 'Monto contado de Efectivo', exact: true })
    .fill(expectedAmount);
  const closeResponsePromise = page.waitForResponse(
    (response) =>
      response.url().endsWith(`/api/cash-sessions/${sessionId}/close`) &&
      response.request().method() === 'POST',
  );
  await page.getByTestId('cash-close-btn').click();
  await page
    .getByRole('alertdialog', { name: 'Cerrar caja' })
    .getByRole('button', { name: 'Sí', exact: true })
    .click();
  const closedResponse = await closeResponsePromise;
  expect(closedResponse.ok(), await closedResponse.text()).toBeTruthy();
  const closedSessionResponse = await page.request.get(
    `${BASE_URL}/api/cash-sessions/${sessionId}`,
  );
  expect(closedSessionResponse.ok()).toBeTruthy();
  const closedSession = (await closedSessionResponse.json()) as {
    expectedAmount: string;
    differenceAmount: string;
    status: string;
  };
  expect(closedSession.status).toBe('CLOSED');
  expect(Number(closedSession.expectedAmount)).toBeCloseTo(openingExpected + 3.88, 2);
  expect(Number(closedSession.differenceAmount)).toBe(0);
});
