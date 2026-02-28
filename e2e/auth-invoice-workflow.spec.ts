import { expect, test } from '@playwright/test';

const baseUrl = process.env.E2E_BASE_URL ?? 'http://web';
const hostHeader = process.env.E2E_HOST_HEADER ?? 'localhost';
const username = process.env.E2E_USERNAME;
const password = process.env.E2E_PASSWORD;
const saleId = process.env.E2E_SALE_ID;

const apiFetch = (path: string, init?: RequestInit) =>
  fetch(`${baseUrl}${path}`, {
    ...init,
    headers: {
      Host: hostHeader,
      ...(init?.headers ?? {}),
    },
  });

test.describe('E2E - Auth and Invoice Workflows', () => {
  test('login endpoint rejects empty credentials', async () => {
    const response = await apiFetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({}),
    });

    expect([400, 401]).toContain(response.status);
  });

  test('invoice endpoints reject unauthenticated requests', async () => {
    const createResponse = await apiFetch('/api/sales/non-existing/invoice', { method: 'POST' });
    const listResponse = await apiFetch('/api/invoices/non-existing/voucher');

    expect([400, 401]).toContain(createResponse.status);
    expect([400, 401]).toContain(listResponse.status);
  });

  test('authenticated invoice creation and listing workflow', async () => {
    test.skip(!username || !password || !saleId, 'E2E credentials/sale id are not configured.');

    const login = await apiFetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username,
        password,
      }),
    });
    expect(login.ok).toBeTruthy();

    const loginPayload = await login.json();
    const accessToken = loginPayload?.accessToken;
    expect(accessToken).toBeTruthy();

    const authHeaders = {
      Authorization: `Bearer ${accessToken}`,
    };

    const createInvoice = await apiFetch(`/api/sales/${saleId}/invoice`, {
      method: 'POST',
      headers: authHeaders,
    });
    expect([200, 400]).toContain(createInvoice.status);

    const listInvoice = await apiFetch(`/api/invoices/${saleId}/voucher`, {
      headers: authHeaders,
    });
    expect([200, 404]).toContain(listInvoice.status);
  });
});
