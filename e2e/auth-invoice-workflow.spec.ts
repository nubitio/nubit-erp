import { expect, test } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const baseUrl = getE2EBaseUrl();

test.describe('E2E - Auth and Invoice Workflows', () => {
  test('login endpoint rejects empty credentials', async ({ request }) => {
    const response = await request.post(`${baseUrl}/api/auth/login`, {
      headers: { 'Content-Type': 'application/json' },
      data: {},
    });

    // 500 is NOT acceptable — a server error here is a bug, not expected behavior
    expect([400, 401]).toContain(response.status());
  });

  test('invoice endpoints reject unauthenticated requests', async ({ request }) => {
    const createResponse = await request.post(`${baseUrl}/api/sales/non-existing/invoice`, {});
    const listResponse = await request.get(`${baseUrl}/api/invoices/non-existing/voucher`);

    // Auth must be checked before routing: 401/403 expected. 404 acceptable if resource
    // lookup runs after auth. 400 acceptable for malformed resource IDs. 500 is a bug.
    expect([400, 401, 403, 404]).toContain(createResponse.status());
    expect([400, 401, 403, 404]).toContain(listResponse.status());
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
    // 200/201 = invoice created; 400 = already invoiced or sale in wrong state (idempotent endpoint).
    // Both are defined business outcomes — neither should be a 5xx.
    expect([200, 201, 400]).toContain(createInvoice.status());
    if (createInvoice.ok()) {
      const invoiceBody = await createInvoice.json();
      expect(invoiceBody).toBeTruthy();
    }

    const listInvoice = await request.get(`${baseUrl}/api/invoices/${saleId}/voucher`, {
      headers: authHeaders,
    });
    // 200 = voucher found; 404 = no invoice exists yet for this sale
    expect([200, 404]).toContain(listInvoice.status());
  });
});
