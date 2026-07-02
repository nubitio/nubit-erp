/**
 * E2E — Compra incrementa stock
 * Login → módulo Compras (UI) → POST /api/purchases → stock +N
 */

import { expect, test, type Page } from '@playwright/test';
import { parseApiCollection } from './helpers/api';
import { waitForGridLoad } from './helpers/crud';
import { loginViaApi } from './helpers/fiscal';
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
    expect(page.url()).toMatch(/\/#\//);
  });

  test('02 - Navegar a Compras y capturar stock inicial del producto seed', async () => {
    await gotoProtectedRoute(page, 'purchases', credentials);
    await waitForGridLoad(page);

    await expect(
      page.locator('.nb-datagrid__toolbar').filter({ hasText: /compras/i }).first(),
    ).toBeVisible({ timeout: 15000 });

    const seedData = await page.evaluate(
      async ({ baseUrl, productCode, productName, warehouseName }) => {
        const whResp = await fetch(`${baseUrl}/api/warehouses?page=1&itemsPerPage=20`);
        const warehouses = (await whResp.json()) as Array<{
          id: string;
          name: string;
          branch?: string | { id?: string };
        }>;
        const whList = Array.isArray(warehouses)
          ? warehouses
          : ((warehouses as { 'hydra:member'?: typeof warehouses })['hydra:member'] ?? []);
        const warehouse =
          whList.find((item) => item.name?.includes(warehouseName)) ?? whList[0] ?? null;

        const branchRef = warehouse?.branch;
        let branchId =
          typeof branchRef === 'string'
            ? (branchRef.match(/\/([^/]+)$/)?.[1] ?? null)
            : (branchRef?.id ?? null);

        if (!branchId) {
          const brResp = await fetch(`${baseUrl}/api/branches?page=1&itemsPerPage=20`);
          const branches = (await brResp.json()) as Array<{ id: string; name?: string }>;
          const brList = Array.isArray(branches)
            ? branches
            : ((branches as { 'hydra:member'?: typeof branches })['hydra:member'] ?? []);
          branchId = brList.find((b) => b.name?.includes('CI E2E'))?.id ?? brList[0]?.id ?? null;
        }

        const prodResp = await fetch(
          `${baseUrl}/api/products?code=${encodeURIComponent(productCode)}&page=1&itemsPerPage=100`,
          { cache: 'no-store' },
        );
        const prodPayload = await prodResp.json();
        const products = Array.isArray(prodPayload)
          ? prodPayload
          : (prodPayload['hydra:member'] ?? prodPayload.member ?? []);
        const product =
          products.find((item: { code?: string }) => item.code === productCode) ??
          products.find((item: { name?: string }) => item.name === productName) ??
          null;

        let stock: number | null = null;
        if (warehouse && product) {
          const stockResp = await fetch(
            `${baseUrl}/api/products/${product.id}/stock/${warehouse.id}?_=${Date.now()}`,
            { cache: 'no-store' },
          );
          if (stockResp.ok) {
            const stockData = await stockResp.json();
            stock = stockData.stock ?? stockData.quantity ?? null;
          }
        }

        return {
          warehouseId: warehouse?.id ?? null,
          warehouseName: warehouse?.name ?? null,
          branchId,
          productId: product?.id ?? null,
          productCode: product?.code ?? null,
          stockBefore: stock,
        };
      },
      {
        baseUrl: BASE_URL,
        productCode: E2E_SEED.productCode,
        productName: E2E_SEED.productName,
        warehouseName: 'CI E2E',
      },
    );

    expect(seedData.warehouseId).toBeTruthy();
    expect(seedData.productId).toBeTruthy();

    resolvedWarehouseId = seedData.warehouseId;
    resolvedBranchId = seedData.branchId;
    resolvedProductId = seedData.productId;
    stockBefore = seedData.stockBefore;

    console.log(
      `[E2E] Stock inicial de "${E2E_SEED.productName}" (${seedData.productCode ?? seedData.productId}) ` +
        `en almacén ${seedData.warehouseName} (${seedData.warehouseId}): ${seedData.stockBefore}`,
    );
  });

  test('03 - Crear compra y verificar stock incrementado', async ({ request }) => {
    const seed = await getE2ESeed();
    const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

    expect(resolvedProductId).toBeTruthy();
    expect(resolvedWarehouseId).toBeTruthy();

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

      console.log(
        `[E2E] Stock después de compra: ${stockAfter} (antes: ${stockBefore}, qty: ${PURCHASE_QTY})`,
      );

      expect(stockBefore, 'stock inicial debe estar disponible').not.toBeNull();
      expect(stockAfter, 'stock final debe estar disponible').not.toBeNull();
      expect(Number(stockAfter)).toBe(Number(stockBefore) + PURCHASE_QTY);
    });
  });
});
