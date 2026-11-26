import { expect, test } from '@playwright/test';
import { strFromU8, unzipSync } from 'fflate';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import { loginViaApi } from './helpers/fiscal';
import { parseApiCollection } from './helpers/api';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('cobro dirigido y FIFO se concilian con un abono global en el EECC', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const sessionId = await ensureOpenCashSession(page, BASE_URL);
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);
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
  const cash = parseApiCollection<{ id: number; name?: string; settlementBehavior?: string }>(
    await methodsResponse.json(),
  ).find((item) => item.settlementBehavior === 'COLLECTED' && /efectivo/i.test(item.name ?? ''));
  expect(product).toBeTruthy();
  expect(warehouse).toBeTruthy();
  expect(cash).toBeTruthy();

  const customerResponse = await request.post(`${BASE_URL}/api/customers`, {
    headers: auth.headers,
    data: {
      businessName: `COBRO FIFO ${Date.now()}`,
      ruc: String(Date.now()).slice(-8),
      isActive: true,
    },
  });
  expect(customerResponse.ok(), await customerResponse.text()).toBeTruthy();
  const customer = (await customerResponse.json()) as { id: number; businessName: string };
  const now = new Date();
  const createSale = async () => {
    const response = await request.post(`${BASE_URL}/api/sales`, {
      headers: auth.headers,
      data: {
        issuedAt: now.toISOString(),
        issuedDate: now.toISOString().slice(0, 10),
        dueDate: now.toISOString().slice(0, 10),
        customer: `/api/customers/${customer.id}`,
        warehouse: `/api/warehouses/${warehouse!.id}`,
        settlements: [{ type: 'RECEIVABLE', amount: '10.00' }],
        emitInvoice: true,
        currency: 'PEN',
        grandTotal: '10.00',
        writeItems: [
          {
            product: `/api/products/${product!.id}`,
            qty: 1,
            unitPrice: '10.00',
            netAmount: '10.00',
          },
        ],
      },
    });
    expect(response.ok(), await response.text()).toBeTruthy();
    return (await response.json()) as { id: string; balanceDue: string };
  };
  const oldest = await createSale();
  const newest = await createSale();

  const directed = await request.post(`${BASE_URL}/api/sales/${newest.id}/collect`, {
    headers: auth.headers,
    data: { id: newest.id, amount: '2.00', paymentMethodId: cash!.id, reference: 'DIRIGIDO-E2E' },
  });
  expect(directed.ok(), await directed.text()).toBeTruthy();
  const excess = await request.post(`${BASE_URL}/api/sales/collect-on-account`, {
    headers: auth.headers,
    data: { customerId: customer.id, amount: '19.00', paymentMethodId: cash!.id },
  });
  expect(excess.status()).toBe(422);

  const before = await page.request.get(`${BASE_URL}/api/cash-sessions/${sessionId}/summary`);
  const beforeRows = (await before.json()) as Array<{
    paymentMethodId: number;
    expectedAmount: string;
  }>;
  const opening = Number(
    beforeRows.find((row) => row.paymentMethodId === cash!.id)?.expectedAmount,
  );
  const global = await request.post(`${BASE_URL}/api/sales/collect-on-account`, {
    headers: auth.headers,
    data: {
      customerId: customer.id,
      amount: '15.00',
      paymentMethodId: cash!.id,
      reference: 'FIFO-E2E',
    },
  });
  expect(global.ok(), await global.text()).toBeTruthy();

  const [oldResponse, newResponse] = await Promise.all([
    request.get(`${BASE_URL}/api/sales/${oldest.id}`, { headers: auth.headers }),
    request.get(`${BASE_URL}/api/sales/${newest.id}`, { headers: auth.headers }),
  ]);
  const oldSale = (await oldResponse.json()) as { balanceDue: string };
  const newSale = (await newResponse.json()) as { balanceDue: string };
  expect(Number(oldSale.balanceDue)).toBe(0);
  expect(Number(newSale.balanceDue)).toBe(3);

  const xlsx = await page.request.get(`${BASE_URL}/api/sales/reports/customer-statement`, {
    params: { customer: String(customer.id), format: 'xlsx' },
  });
  expect(xlsx.ok(), await xlsx.text()).toBeTruthy();
  const workbook = unzipSync(new Uint8Array(await xlsx.body()));
  const sharedXml = strFromU8(workbook['xl/sharedStrings.xml']);
  const worksheet = strFromU8(workbook['xl/worksheets/sheet1.xml']);
  const sharedValues = [...sharedXml.matchAll(/<si>(.*?)<\/si>/gs)].map((match) =>
    match[1].replace(/<[^>]+>/g, ''),
  );
  const collectionLabelIndex = sharedValues.indexOf('Cobro a cuenta');
  expect(collectionLabelIndex).toBeGreaterThanOrEqual(0);
  const collectionLabelRef = `<v>${collectionLabelIndex}</v>`;
  const collectionRows = [...worksheet.matchAll(/<row\b[^>]*>.*?<\/row>/gs)].filter((row) =>
    row[0].includes(collectionLabelRef),
  );
  expect(collectionRows).toHaveLength(1);

  const after = await page.request.get(`${BASE_URL}/api/cash-sessions/${sessionId}/summary`);
  const afterRows = (await after.json()) as Array<{
    paymentMethodId: number;
    expectedAmount: string;
  }>;
  expect(
    Number(afterRows.find((row) => row.paymentMethodId === cash!.id)?.expectedAmount),
  ).toBeCloseTo(opening + 17, 2);
});
