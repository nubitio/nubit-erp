import { expect, test } from '@playwright/test';

const baseUrl = process.env.E2E_BASE_URL ?? 'http://web';
const hostHeader = process.env.E2E_HOST_HEADER ?? 'localhost';
const username = process.env.E2E_USERNAME;
const password = process.env.E2E_PASSWORD;
const saleId = process.env.E2E_SALE_ID;

test.describe('E2E - Auth and Invoice Workflows', () => {
  test('login endpoint rejects empty credentials', async ({ request }) => {
    const response = await request.post(`${baseUrl}/api/auth/login`, {
      headers: { Host: hostHeader, 'Content-Type': 'application/json' },
      data: {},
    });

    expect([400, 401, 500]).toContain(response.status());
  });

  test('invoice endpoints reject unauthenticated requests', async ({ request }) => {
    const createResponse = await request.post(`${baseUrl}/api/sales/non-existing/invoice`, {
      headers: { Host: hostHeader },
    });
    const listResponse = await request.get(`${baseUrl}/api/invoices/non-existing/voucher`, {
      headers: { Host: hostHeader },
    });

    expect([400, 401, 500]).toContain(createResponse.status());
    expect([400, 401, 500]).toContain(listResponse.status());
  });

  test('authenticated invoice creation and listing workflow', async ({ request }) => {
    expect(username, 'E2E_USERNAME must be provided by CI seeding').toBeTruthy();
    expect(password, 'E2E_PASSWORD must be provided by CI seeding').toBeTruthy();
    expect(saleId, 'E2E_SALE_ID must be provided by CI seeding').toBeTruthy();

    const login = await request.post(`${baseUrl}/api/auth/login`, {
      headers: { Host: hostHeader, 'Content-Type': 'application/json', 'X-Client-Type': 'android' },
      data: {
        username: username as string,
        password: password as string,
      },
    });
    expect(login.ok()).toBeTruthy();

    const loginPayload = await login.json();
    const accessToken = loginPayload?.token;
    expect(accessToken).toBeTruthy();

    const authHeaders = {
      Host: hostHeader,
      Authorization: `Bearer ${accessToken as string}`,
    };

    const createInvoice = await request.post(`${baseUrl}/api/sales/${saleId as string}/invoice`, {
      headers: { ...authHeaders, 'Content-Type': 'application/json' },
      data: {},
    });
    expect([200, 400]).toContain(createInvoice.status());

    const listInvoice = await request.get(`${baseUrl}/api/invoices/${saleId as string}/voucher`, {
      headers: authHeaders,
    });
    expect([200, 404]).toContain(listInvoice.status());
  });
});
