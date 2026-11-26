/**
 * E2E — Compra incrementa stock
 * Login → módulo Compras (UI) → POST /api/purchases → stock +N
 */

import { expect, test, type Page } from '@playwright/test';
import { parseApiCollection } from './helpers/api';
import { waitForGridLoad } from './helpers/crud';
import { loginViaApi, selectBranchViaApi } from './helpers/fiscal';
import { gotoProtectedRoute } from './helpers/navigation';
import { login } from './helpers/auth';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();
const PURCHASE_QTY = 3;
const PURCHASE_UNIT_COST = '5.00';

let credentials = { username: '', password: '' };
let resolvedWarehouseId: string | null = null;
let resolvedProductId: string | null = null;
let resolvedBranchId: string | null = null;
let stockBefore: number | null = null;
let createdPurchaseId: string | null = null;

test.describe('Compras — crear compra e incremento de stock', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    credentials = { username: seed.username, password: seed.password };

    const context = await browser.newContext();
    page = await context.newPage();
  });

  test.afterAll(async () => {
    await page.context().close();
  });

  test('01 - Login', async () => {
    await login(page, credentials.username, credentials.password);
    await expect(page.locator('form.login-form')).toBeHidden({ timeout: 10000 });
    expect(new URL(page.url()).pathname).not.toBe('/login');
  });

  test('02 - Navegar a Compras y capturar stock inicial del producto seed', async ({ request }) => {
    await gotoProtectedRoute(page, 'purchases', credentials);
    await waitForGridLoad(page);
    await expect(page).toHaveURL(/\/purchases/);

    const session = await loginViaApi(
      request,
      BASE_URL,
      credentials.username,
      credentials.password,
    );
    const options = await request.get(`${BASE_URL}/api/auth/operating-branches`, {
      headers: session.headers,
    });
    expect(options.ok()).toBeTruthy();
    const branches = (await options.json()) as { id: string; name: string }[];
    const branch = branches.find((option) => option.name === E2E_SEED.branchName);
    expect(branch).toBeDefined();
    const auth = await selectBranchViaApi(request, BASE_URL, session, branch!.id);
    const warehousesResponse = await request.get(
      `${BASE_URL}/api/warehouses?page=1&itemsPerPage=20`,
      {
        headers: auth.headers,
      },
    );
    expect(warehousesResponse.ok()).toBeTruthy();
    const warehouses = parseApiCollection<{
      id: string;
      name: string;
      branch?: string | { id?: string };
    }>(await warehousesResponse.json());
    const warehouse =
      warehouses.find((item) => item.name?.includes('CI E2E')) ?? warehouses[0] ?? null;

    const branchRef = warehouse?.branch;
    let branchId =
      typeof branchRef === 'string'
        ? (branchRef.match(/\/([^/]+)$/)?.[1] ?? null)
        : (branchRef?.id ?? null);
    if (!branchId) {
      const branchesResponse = await request.get(
        `${BASE_URL}/api/branches?page=1&itemsPerPage=20`,
        {
          headers: auth.headers,
        },
      );
      expect(branchesResponse.ok()).toBeTruthy();
      const branches = parseApiCollection<{ id: string; name?: string }>(
        await branchesResponse.json(),
      );
      branchId =
        branches.find((item) => item.name?.includes('CI E2E'))?.id ?? branches[0]?.id ?? null;
    }

    const productsResponse = await request.get(
      `${BASE_URL}/api/products?code=${encodeURIComponent(E2E_SEED.productCode)}&page=1&itemsPerPage=100`,
      { headers: auth.headers },
    );
    expect(productsResponse.ok()).toBeTruthy();
    const products = parseApiCollection<{ id: string; code?: string; name?: string }>(
      await productsResponse.json(),
    );
    const product =
      products.find((item) => item.code === E2E_SEED.productCode) ??
      products.find((item) => item.name === E2E_SEED.productName) ??
      null;

    let initialStock: number | null = null;
    if (warehouse && product) {
      const stockResponse = await request.get(
        `${BASE_URL}/api/products/${product.id}/stock/${warehouse.id}?_=${Date.now()}`,
        { headers: auth.headers },
      );
      if (stockResponse.ok()) {
        const stockData = (await stockResponse.json()) as { stock?: number; quantity?: number };
        initialStock = stockData.stock ?? stockData.quantity ?? null;
      }
    }

    const seedData = {
      warehouseId: warehouse?.id ?? null,
      warehouseName: warehouse?.name ?? null,
      branchId,
      productId: product?.id ?? null,
      productCode: product?.code ?? null,
      stockBefore: initialStock,
    };

    expect(seedData.warehouseId).toBeTruthy();
    expect(seedData.productId).toBeTruthy();

    resolvedWarehouseId = seedData.warehouseId;
    resolvedBranchId = seedData.branchId;
    resolvedProductId = seedData.productId;
    stockBefore = seedData.stockBefore;
  });

  test('03 - Crear compra y verificar stock incrementado', async ({ request }) => {
    const seed = await getE2ESeed();
    const session = await loginViaApi(request, BASE_URL, seed.username, seed.password);

    expect(resolvedProductId).toBeTruthy();
    expect(resolvedWarehouseId).toBeTruthy();
    expect(resolvedBranchId).toBeTruthy();
    const auth = await selectBranchViaApi(request, BASE_URL, session, resolvedBranchId!);

    await test.step('Registrar compra vía API', async () => {
      const suppliersResponse = await request.get(`${BASE_URL}/api/suppliers?itemsPerPage=50`, {
        headers: auth.headers,
      });
      expect(suppliersResponse.ok()).toBeTruthy();

      const suppliers = parseApiCollection<{ id?: string; ruc?: string; businessName?: string }>(
        await suppliersResponse.json(),
      );
      const supplierId =
        suppliers.find((item) => item.ruc === E2E_SEED.supplierRuc)?.id ??
        suppliers.find((item) => item.businessName === E2E_SEED.supplierName)?.id ??
        suppliers[0]?.id ??
        null;

      expect(supplierId).toBeTruthy();

      const createResponse = await request.post(`${BASE_URL}/api/purchases`, {
        headers: auth.headers,
        data: {
          issuedAt: new Date().toISOString().slice(0, 10),
          branch: resolvedBranchId ? `/api/branches/${resolvedBranchId}` : undefined,
          warehouse: `/api/warehouses/${resolvedWarehouseId}`,
          supplier: `/api/suppliers/${supplierId}`,
          supplierDocType: '01',
          supplierDocSeries: 'E001',
          supplierDocNumber: String(Date.now()).slice(-8),
          items: [
            {
              product: `/api/products/${resolvedProductId}`,
              qty: PURCHASE_QTY,
              unitCost: PURCHASE_UNIT_COST,
            },
          ],
        },
      });

      const body = await createResponse.text();
      expect(createResponse.ok(), `POST /api/purchases: ${body}`).toBeTruthy();

      const createdPurchase = JSON.parse(body) as { id?: string | null };
      createdPurchaseId = createdPurchase.id ?? null;
      expect(createdPurchaseId).toBeTruthy();
    });

    await test.step('Verificar stock incrementado', async () => {
      const stockResponse = await request.get(
        `${BASE_URL}/api/products/${resolvedProductId}/stock/${resolvedWarehouseId}?_=${Date.now()}`,
        { headers: auth.headers },
      );
      expect(stockResponse.ok()).toBeTruthy();

      const stockPayload = (await stockResponse.json()) as {
        stock?: number | string;
        quantity?: number | string;
      };
      const stockAfter = stockPayload.stock ?? stockPayload.quantity ?? null;

      expect(stockBefore, 'stock inicial debe estar disponible').not.toBeNull();
      expect(stockAfter, 'stock final debe estar disponible').not.toBeNull();
      expect(Number(stockAfter)).toBe(Number(stockBefore) + PURCHASE_QTY);
    });
  });
});
