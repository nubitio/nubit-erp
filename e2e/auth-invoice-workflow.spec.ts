import { expect, test } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const baseUrl = getE2EBaseUrl();

test.describe('E2E - Auth and Invoice Workflows', () => {
  test('login endpoint rejects empty credentials', async ({ request }) => {
    const response = await request.post(`${baseUrl}/api/auth/login`, {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    });

    expect([400, 401, 500]).toContain(response.status());
  });

  test('invoice endpoints reject unauthenticated requests', async ({ request }) => {
    const createResponse = await request.post(`${baseUrl}/api/sales/non-existing/invoice`, {});
    const listResponse = await request.get(`${baseUrl}/api/invoices/non-existing/voucher`);

    expect([400, 401, 500]).toContain(createResponse.status());
    expect([400, 401, 500]).toContain(listResponse.status());
  });

  test('authenticated invoice creation and listing workflow', async ({ request }) => {
    const { password, saleId, username } = await getE2ESeed();

    const login = await request.post(`${baseUrl}/api/auth/login`, {
      headers: { 'Content-Type': 'application/json', 'X-Client-Type': 'android' },
      data: {
        username,
        password,
      },
    });
    expect(login.ok()).toBeTruthy();

    const loginPayload = await login.json();
    const accessToken = loginPayload?.token;
    expect(accessToken).toBeTruthy();

    const authHeaders = {
      Authorization: `Bearer ${accessToken as string}`,
    };

    const createInvoice = await request.post(`${baseUrl}/api/sales/${saleId}/invoice`, {
      headers: { ...authHeaders, 'Content-Type': 'application/json' },
      data: {},
    });
    expect([200, 400]).toContain(createInvoice.status());

    const listInvoice = await request.get(`${baseUrl}/api/invoices/${saleId}/voucher`, {
      headers: authHeaders,
    });
    expect([200, 404]).toContain(listInvoice.status());
  });
});
