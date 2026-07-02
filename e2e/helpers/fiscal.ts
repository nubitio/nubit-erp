import { expect, type APIRequestContext } from '@playwright/test';

export type AuthSession = {
  accessToken: string;
  headers: Record<string, string>;
};

export async function loginViaApi(
  request: APIRequestContext,
  baseUrl: string,
  username: string,
  password: string,
): Promise<AuthSession> {
  const login = await request.post(`${baseUrl}/api/auth/login`, {
    headers: { 'Content-Type': 'application/json', 'X-Client-Type': 'android' },
    data: { username, password },
  });
  expect(login.ok(), 'login API debe responder 2xx').toBeTruthy();

  const payload = (await login.json()) as { token?: string };
  const accessToken = payload.token;
  expect(accessToken, 'login debe devolver token').toBeTruthy();

  return {
    accessToken: accessToken as string,
    headers: {
      Authorization: `Bearer ${accessToken as string}`,
      'Content-Type': 'application/json',
    },
  };
}

export async function createInvoiceForSale(
  request: APIRequestContext,
  baseUrl: string,
  auth: AuthSession,
  saleId: string,
) {
  const response = await request.post(`${baseUrl}/api/sales/${saleId}/invoice`, {
    headers: auth.headers,
    data: {},
  });
  expect([200, 201, 400]).toContain(response.status());

  return response;
}

export async function listTaxDocumentsForSale(
  request: APIRequestContext,
  baseUrl: string,
  auth: AuthSession,
  saleId: string,
) {
  const response = await request.get(`${baseUrl}/api/sales/${saleId}/tax-documents`, {
    headers: auth.headers,
  });
  expect(response.ok(), 'listar tax-documents debe responder 2xx').toBeTruthy();

  return (await response.json()) as Array<{ id?: string; sunatStatus?: string | null; cdrPath?: string | null }>;
}

export async function sendTaxDocument(
  request: APIRequestContext,
  baseUrl: string,
  auth: AuthSession,
  taxDocumentId: string,
) {
  const response = await request.post(`${baseUrl}/api/tax-documents/${taxDocumentId}/send`, {
    headers: auth.headers,
    data: {},
  });
  const responseText = await response.text();
  expect(
    response.ok(),
    `envío SUNAT debe responder 2xx con stub E2E: ${response.status()} ${responseText}`,
  ).toBeTruthy();

  const body = JSON.parse(responseText) as { success?: boolean; message?: string };
  expect(body.success, `SUNAT send debe ser exitoso: ${body.message ?? ''}`).toBeTruthy();

  return body;
}

export async function getTaxDocument(
  request: APIRequestContext,
  baseUrl: string,
  auth: AuthSession,
  taxDocumentId: string,
) {
  const response = await request.get(`${baseUrl}/api/tax-documents/${taxDocumentId}`, {
    headers: auth.headers,
  });
  expect(response.ok(), 'GET tax-document debe responder 2xx').toBeTruthy();

  return (await response.json()) as {
    id?: string;
    sunatStatus?: string | null;
    sunatMessage?: string | null;
    cdrPath?: string | null;
    series?: string;
    number?: string;
    documentType?: string;
  };
}

export async function getVoucherForSale(
  request: APIRequestContext,
  baseUrl: string,
  auth: AuthSession,
  saleId: string,
) {
  const response = await request.get(`${baseUrl}/api/invoices/${saleId}/voucher`, {
    headers: auth.headers,
  });
  expect(response.ok(), 'voucher de venta debe existir').toBeTruthy();

  return (await response.json()) as {
    voucher_type?: string;
    voucher_series?: string;
    voucher_number?: string;
    voucher_sale_amount?: string;
    public_receipt_url?: string;
  };
}

export function buildReceiptPdfUrl(baseUrl: string, voucherType: string, taxDocumentId: string): string {
  const payload = Buffer.from(
    JSON.stringify({ type: voucherType, id: taxDocumentId }),
    'utf8',
  ).toString('base64');

  return `${baseUrl}/receipt/${payload}/pdf`;
}
