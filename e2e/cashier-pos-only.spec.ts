import {
  expect,
  request as playwrightRequest,
  test,
  type APIRequestContext,
} from '@playwright/test';
import { login as uiLogin } from './helpers/auth';
import { completePosCashPayment, waitForPosCheckoutReady } from './helpers/pos';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import { E2E_SEED } from './support/seedConstants';

/**
 * The stock Cajero role is someone who sells: it lands on the POS, the sidebar has nothing else, the POS
 * works end to end (sell, print, e-mail, SUNAT state) and the branch's sales ledger stays closed.
 */
test.describe.configure({ mode: 'serial' });

const BASE = getE2EBaseUrl();
// A fixed name: the tenant caps its users, and a cashier who has sold cannot be deleted, so a retry reuses it.
const cashierName = 'cajero.e2e';
const registerName = 'Caja del cajero e2e';
const CASHIER_PASSWORD = 'Cajero-pass-12345';
const CASHIER_ROLE = 3;

async function newApi(): Promise<APIRequestContext> {
  return playwrightRequest.newContext({ baseURL: BASE });
}
async function csrf(api: APIRequestContext): Promise<Record<string, string>> {
  const token = (await api.storageState()).cookies.find((c) => c.name === 'CSRF_TOKEN')?.value;
  return token ? { 'X-CSRF-Token': decodeURIComponent(token) } : {};
}

let admin: APIRequestContext;
let adminSaleId = '';

test.beforeAll(async () => {
  admin = await newApi();
  const seed = await getE2ESeed();
  adminSaleId = seed.saleId;
  const signedIn = await admin.post('/api/auth/login', {
    data: { username: seed.username, password: seed.password },
  });
  expect(signedIn.status()).toBe(200);

  const members = async <T>(path: string): Promise<T[]> => {
    const response = await admin.get(path, { headers: { Accept: 'application/json' } });
    const body = (await response.json()) as T[] | { member?: T[] };
    return Array.isArray(body) ? body : (body.member ?? []);
  };

  const existing = (
    await members<{ id: number; username: string }>('/api/users?itemsPerPage=100')
  ).find((user) => user.username === cashierName);
  if (!existing) {
    const created = await admin.post('/api/users', {
      data: {
        username: cashierName,
        plainPassword: CASHIER_PASSWORD,
        role: `/api/roles/${CASHIER_ROLE}`,
      },
      headers: await csrf(admin),
    });
    expect(created.status(), await created.text()).toBe(201);
  }

  // The seeded registers are held by the e2e user: give the cashier a free one in every branch.
  const taken = new Set(
    (
      await members<{ name: string; branch?: { id: string } }>(
        '/api/cash-registers?itemsPerPage=100',
      )
    )
      .filter((register) => register.name === registerName)
      .map((register) => register.branch?.id),
  );
  for (const branch of await members<{ id: string }>('/api/branches?itemsPerPage=100')) {
    if (taken.has(branch.id)) continue;
    const register = await admin.post('/api/cash-registers', {
      data: { name: registerName, branch: `/api/branches/${branch.id}`, isActive: true },
      headers: await csrf(admin),
    });
    expect(register.status(), await register.text()).toBe(201);
  }
});

test.afterAll(async () => {
  // Not deleted: a cashier who has sold is referenced by their sales, and the e2e stack is disposable.
  await admin.dispose();
});

test('el cajero entra al POS, vende y solo ve sus propios comprobantes', async ({ page }) => {
  const forbidden: string[] = [];
  page.on('response', (response) => {
    if (response.status() === 403 && response.url().includes('/api/')) {
      forbidden.push(`${response.request().method()} ${new URL(response.url()).pathname}`);
    }
  });

  await uiLogin(page, cashierName, CASHIER_PASSWORD);

  await test.step('aterriza en el POS y no hay a dónde salir', async () => {
    await expect(page).toHaveURL(/\/pos/);
    await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25_000 });
    await expect(page.getByTestId('pos-caja-badge')).not.toContainText('Verificando', {
      timeout: 30_000,
    });
    await expect(page.getByRole('button', { name: 'Salir del POS' })).toHaveCount(0);

    const menu = await page.request.get(`${BASE}/api/menu/${CASHIER_ROLE}`, {
      headers: { Accept: 'application/json' },
    });
    const entries = (await menu.json()) as Array<{ path?: string; items?: unknown[] }>;
    expect(entries.map((entry) => entry.path)).toEqual(['/pos']);
  });

  let saleId = '';
  let taxDocumentId = '';
  await test.step('abre su caja y cobra una venta sin recibir un solo 403', async () => {
    await page.getByRole('button', { name: 'Abrir caja' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Abrir caja' });
    await dialog.getByLabel(/Efectivo inicial/).fill('50');
    await dialog.getByTestId('cash-open-submit-btn').click();
    await expect(page.getByTestId('pos-caja-badge')).toContainText('Abierta', { timeout: 30_000 });

    await page
      .getByTestId('pos-product-card')
      .filter({ hasText: E2E_SEED.productName })
      .filter({ hasNotText: E2E_SEED.product2Name })
      .first()
      .click();
    await completePosCashPayment(page);
    await waitForPosCheckoutReady(page);
    const sold = page.waitForResponse(
      (response) =>
        /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
    );
    await page.getByTestId('pos-checkout-btn').click();
    const response = await sold;
    expect(response.ok(), await response.text()).toBeTruthy();
    const body = (await response.json()) as { id?: string | number; taxDocumentId?: string };
    saleId = String(body.id);
    taxDocumentId = String(body.taxDocumentId ?? '');
    await page.getByRole('button', { name: /Nueva venta/ }).click();
  });

  await test.step('imprime, consulta y envía por correo su propio comprobante', async () => {
    const print = await page.request.get(`${BASE}/api/sales/${saleId}/print-invoice`);
    expect(print.status(), 'imprimir su venta').toBe(200);
    if (taxDocumentId !== '') {
      const consult = await page.request.get(`${BASE}/api/tax-documents/${taxDocumentId}/consult`);
      expect(consult.status(), 'consultar el estado de su comprobante').not.toBe(403);
      expect(consult.status()).toBeLessThan(500);
      const mail = await page.request.post(`${BASE}/api/tax-documents/${taxDocumentId}/email`, {
        data: { email: 'cliente@example.pe' },
        headers: await csrf(page.request),
      });
      // Whether the mail goes out is the mailer's business; what matters here is that it is not refused.
      expect(mail.status(), 'enviar su comprobante por correo').not.toBe(403);
      expect(mail.status()).toBeLessThan(500);
    }
    expect(forbidden, 'ninguna llamada del POS puede dar 403').toEqual([]);
  });

  await test.step('no lee las ventas de otros ni el libro de ventas', async () => {
    const others = await page.request.get(`${BASE}/api/sales/${adminSaleId}/print-invoice`);
    expect(others.status(), 'imprimir la venta de otro').toBe(403);
    expect((await page.request.get(`${BASE}/api/sales`)).status()).toBe(403);
    expect((await page.request.get(`${BASE}/api/sales/${saleId}`)).status()).toBe(403);
    const void_ = await page.request.post(`${BASE}/api/sales/${saleId}/void`, {
      data: { reason: 'prueba' },
      headers: await csrf(page.request),
    });
    expect(void_.status(), 'anular').toBe(403);
  });
});
