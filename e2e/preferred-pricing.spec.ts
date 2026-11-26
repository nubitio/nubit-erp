import { expect, test, type APIResponse } from '@playwright/test';
import { login } from './helpers/auth';
import { loginViaApi } from './helpers/fiscal';
import { selectPosCustomer } from './helpers/pos';
import { crudShellBody, dismissDevToolbar, waitForGridLoad } from './helpers/crud';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

function members<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[];
  const object = payload as { member?: T[]; 'hydra:member'?: T[] };
  return object.member ?? object['hydra:member'] ?? [];
}

async function json(response: APIResponse): Promise<unknown> {
  expect(response.ok(), `${response.url()} debe responder 2xx`).toBeTruthy();
  return response.json();
}

test('la tarifa del cliente se aplica en POS y en el módulo Ventas', async ({ page, request }) => {
  const seed = await getE2ESeed();
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

  const products = members<{ id: string; code?: string; salePrice: string }>(
    await json(
      await request.get(`${BASE_URL}/api/products?itemsPerPage=100`, { headers: auth.headers }),
    ),
  );
  const customers = members<{ id: number; ruc?: string; priceList?: unknown }>(
    await json(
      await request.get(`${BASE_URL}/api/customers?itemsPerPage=100`, { headers: auth.headers }),
    ),
  );
  const product = products.find((item) => item.code === E2E_SEED.productCode);
  const customer = customers.find((item) => item.ruc === E2E_SEED.customerDni);
  if (!product || !customer) throw new Error('No se encontraron el producto y cliente E2E');

  const listResponse = await request.post(`${BASE_URL}/api/price-lists`, {
    headers: auth.headers,
    data: { name: `Tarifa E2E ${Date.now()}`, multiplier: '0.5000' },
  });
  const createdList = (await json(listResponse)) as { id: number };
  const listIri = `/api/price-lists/${createdList.id}`;
  const patchHeaders = { ...auth.headers, 'Content-Type': 'application/merge-patch+json' };

  try {
    await json(
      await request.patch(`${BASE_URL}/api/customers/${customer.id}`, {
        headers: patchHeaders,
        data: { priceList: listIri },
      }),
    );
    const updatedCustomer = await json(
      await request.get(`${BASE_URL}/api/customers/${customer.id}`, { headers: auth.headers }),
    );
    expect(JSON.stringify(updatedCustomer)).toContain(listIri);
    const appliedPrices = members<{ id: string; price: string }>(
      await json(
        await request.get(`${BASE_URL}/api/price-lists/${createdList.id}/apply`, {
          headers: auth.headers,
        }),
      ),
    );
    expect(Number(appliedPrices.find((item) => item.id === product.id)?.price)).toBe(5);

    await login(page, seed.username, seed.password);

    await test.step('POS usa el precio preferencial', async () => {
      await page.goto(`${BASE_URL}/pos`);
      await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25000 });
      const applyResponsePromise = page.waitForResponse(
        (response) => response.url().includes(`/api/price-lists/${createdList.id}/apply`),
        { timeout: 15000 },
      );
      await selectPosCustomer(page, { name: E2E_SEED.customerName });
      const browserApplyResponse = await applyResponsePromise;
      expect(browserApplyResponse.ok()).toBeTruthy();
      const browserPrices = members<{ id: string; price: string }>(
        await browserApplyResponse.json(),
      );
      expect(Number(browserPrices.find((item) => item.id === product.id)?.price)).toBe(5);
      await page.getByTestId('pos-product-search').fill(E2E_SEED.productName);

      const card = page
        .getByTestId('pos-product-card')
        .filter({ hasText: E2E_SEED.productName })
        .filter({ hasNotText: E2E_SEED.product2Name })
        .first();
      await expect(card).toContainText(/S\/\s?5\.00/, { timeout: 15000 });
      const addButton = card.getByTestId('pos-product-card-add');
      if (await addButton.isVisible({ timeout: 1500 }).catch(() => false)) {
        await addButton.click();
      } else {
        await card.click();
      }
      await expect(page.locator('.pos-line__unit').first()).toContainText(/S\/\s*5\.00/);
    });

    await test.step('Ventas usa el mismo precio preferencial', async () => {
      await page.goto(`${BASE_URL}/billing/sales`);
      await dismissDevToolbar(page);
      await waitForGridLoad(page);
      await page.getByRole('button', { name: 'Nueva venta' }).click();
      await expect(page).toHaveURL(/\/billing\/sales\/new$/);
      const customerInput = page.getByPlaceholder('Busca por nombre o RUC');
      await customerInput.fill(E2E_SEED.customerName);
      await page
        .locator('[role="option"]')
        .filter({ hasText: E2E_SEED.customerName })
        .first()
        .click();

      const productInput = page.getByPlaceholder('Selecciona un producto o servicio');
      await productInput.click();
      await productInput.pressSequentially(E2E_SEED.productName, { delay: 30 });
      await page
        .locator('[role="option"]')
        .filter({ hasText: E2E_SEED.productName })
        .first()
        .click();

      await expect(page.getByLabel('Precio de la línea 1')).toHaveValue(/^5(?:\.0+)?$/, {
        timeout: 10000,
      });
    });
  } finally {
    await request.patch(`${BASE_URL}/api/customers/${customer.id}`, {
      headers: patchHeaders,
      data: { priceList: customer.priceList ?? null },
    });
    await request.delete(`${BASE_URL}/api/price-lists/${createdList.id}`, {
      headers: auth.headers,
    });
  }
});

test('la tarifa es un selector en clientes y permite asignar varios desde listas de precios', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);
  const allCustomers = members<{
    id: number;
    ruc?: string;
    businessName: string;
    priceList?: unknown;
  }>(
    await json(
      await request.get(`${BASE_URL}/api/customers?itemsPerPage=100`, { headers: auth.headers }),
    ),
  );
  const targets = [
    allCustomers.find((item) => item.ruc === '00000000'),
    allCustomers.find((item) => item.ruc === E2E_SEED.customerDni),
  ].filter((item): item is NonNullable<typeof item> => item !== undefined);
  expect(targets).toHaveLength(2);

  const listName = `Tarifa masiva E2E ${Date.now()}`;
  const list = (await json(
    await request.post(`${BASE_URL}/api/price-lists`, {
      headers: auth.headers,
      data: { name: listName, multiplier: '0.9000' },
    }),
  )) as { id: number };
  const patchHeaders = { ...auth.headers, 'Content-Type': 'application/merge-patch+json' };

  try {
    await login(page, seed.username, seed.password);

    await test.step('el formulario de cliente muestra Tarifa como selector', async () => {
      await page.goto(`${BASE_URL}/customer`);
      await waitForGridLoad(page);
      const row = page.getByRole('row').filter({ hasText: targets[0].businessName }).first();
      await row.getByRole('button', { name: 'Acciones' }).click();
      await page.getByText('Editar', { exact: true }).click();
      const body = crudShellBody(page);
      await expect(body.getByRole('combobox', { name: 'Tarifa' })).toBeVisible();
    });

    await page.goto(`${BASE_URL}/catalog/prices`);
    await waitForGridLoad(page);
    const priceListRow = page.getByRole('row').filter({ hasText: listName }).first();
    await expect(priceListRow).toBeVisible({ timeout: 15000 });
    await priceListRow.click();
    await page.getByRole('button', { name: 'Acciones (1)' }).click();
    await page.getByText('Asignar clientes', { exact: true }).click();

    const dialog = page.getByRole('dialog', { name: `Asignar clientes a ${listName}` });
    await expect(dialog).toBeVisible();
    for (const target of targets) {
      const row = dialog.getByRole('row').filter({ hasText: target.businessName }).first();
      await expect(row).toBeVisible({ timeout: 15000 });
      await row.getByRole('checkbox').check();
    }

    let patchCount = 0;
    const patchesFinished = new Promise<void>((resolve) => {
      page.on('response', (response) => {
        if (
          response.request().method() === 'PATCH' &&
          /\/api\/customers\/\d+/.test(response.url())
        ) {
          patchCount += 1;
          if (patchCount === targets.length) resolve();
        }
      });
    });
    await dialog.getByRole('button', { name: /guardar/i }).click();
    await patchesFinished;
    await expect(dialog).toBeHidden();

    for (const target of targets) {
      const updated = await json(
        await request.get(`${BASE_URL}/api/customers/${target.id}`, { headers: auth.headers }),
      );
      expect(JSON.stringify(updated)).toContain(`/api/price-lists/${list.id}`);
    }
  } finally {
    await Promise.all(
      targets.map((target) =>
        request.patch(`${BASE_URL}/api/customers/${target.id}`, {
          headers: patchHeaders,
          data: { priceList: target.priceList ?? null },
        }),
      ),
    );
    await request.delete(`${BASE_URL}/api/price-lists/${list.id}`, { headers: auth.headers });
  }
});
