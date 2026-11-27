import { expect, type APIRequestContext } from '@playwright/test';
import { E2E_SEED } from '../support/seedConstants';

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
  const loginBody = await login.text();
  expect(
    login.ok(),
    `login API debe responder 2xx (status ${login.status()} ${loginBody})`,
  ).toBeTruthy();

  const payload = JSON.parse(loginBody) as { token?: string };
  const accessToken = payload.token;
  expect(accessToken, 'login debe devolver token').toBeTruthy();

  const session = {
    accessToken: accessToken as string,
    headers: {
      Authorization: `Bearer ${accessToken as string}`,
      'Content-Type': 'application/json',
    },
  };
  const response = await request.get(`${baseUrl}/api/auth/operating-branches`, {
    headers: session.headers,
  });
  expect(response.ok(), 'cargar sucursales operativas').toBeTruthy();
  const branches = (await response.json()) as { id: string; name: string }[];
  const branch = branches.find((option) => option.name === E2E_SEED.branchName) ?? branches[0];
  expect(branch, 'el fixture debe tener una sucursal operativa').toBeDefined();

  return selectBranchViaApi(request, baseUrl, session, branch.id);
}

export async function loginAtSeedBranchViaApi(
  request: APIRequestContext,
  baseUrl: string,
  username: string,
  password: string,
): Promise<AuthSession> {
  const session = await loginViaApi(request, baseUrl, username, password);
  const response = await request.get(`${baseUrl}/api/auth/operating-branches`, {
    headers: session.headers,
  });
  expect(response.ok(), 'cargar sucursales operativas').toBeTruthy();
  const branches = (await response.json()) as { id: string; name: string }[];
  const branch = branches.find((option) => option.name === E2E_SEED.branchName);
  if (!branch) throw new Error(`Falta la sucursal E2E ${E2E_SEED.branchName}`);

  return selectBranchViaApi(request, baseUrl, session, branch.id);
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

  return (await response.json()) as Array<{
    id?: string;
    sunatStatus?: string | null;
    cdrPath?: string | null;
  }>;
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

export function buildReceiptPdfUrl(
  baseUrl: string,
  voucherType: string,
  taxDocumentId: string,
): string {
  const payload = Buffer.from(
    JSON.stringify({ type: voucherType, id: taxDocumentId }),
    'utf8',
  ).toString('base64');

  return `${baseUrl}/receipt/${payload}/pdf`;
}

export async function selectBranchViaApi(
  request: APIRequestContext,
  baseUrl: string,
  session: AuthSession,
  branchId: string,
): Promise<AuthSession> {
  const response = await request.post(`${baseUrl}/api/auth/select-branch`, {
    headers: { ...session.headers, 'X-Client-Type': 'android' },
    data: { branchId, response_mode: 'json' },
  });
  expect(
    response.ok(),
    `seleccionar sucursal debe responder 2xx (${response.status()})`,
  ).toBeTruthy();
  const { token } = (await response.json()) as { token: string };
  expect(token).toBeTruthy();

  return {
    accessToken: token,
    headers: { ...session.headers, Authorization: `Bearer ${token}` },
  };
}
