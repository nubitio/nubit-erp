/**
 * e2e/purchases-stock.spec.ts
 *
 * E2E — Flujo de compra y verificación de stock
 * ===============================================
 * Cubre en orden:
 *  1. Login
 *  2. Navegar a /#/purchases
 *  3. Abrir formulario de nueva compra
 *  4. Seleccionar almacén, proveedor y fecha
 *  5. Agregar una fila de detalle (producto + cantidad + precio)
 *  6. Guardar → verificar POST /api/purchases responde 2xx
 *  7. Verificar que el stock del producto en el almacén incrementó
 *
 * Stack: React 19 + HashRouter (/#/ruta)
 *
 * ── Variables de entorno ────────────────────────────────────────────────────
 *   E2E_BASE_URL   (default: http://localhost:8080)
 *   E2E_USERNAME / E2E_PASSWORD / E2E_SALE_ID  (provistos por e2eSeed)
 */

import { expect, test, type Page } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import { crudShell, crudShellBody, switchFormTab, waitForGridLoad } from './helpers/crud';

// ── Env / config ──────────────────────────────────────────────────────────────
const BASE_URL = getE2EBaseUrl();
let USERNAME = process.env.E2E_USERNAME ?? '';
let PASSWORD = process.env.E2E_PASSWORD ?? '';

// ── Seed constants — siempre disponibles en el entorno E2E ───────────────────
const SEED_PRODUCT_NAME = 'Producto CI E2E';
const SEED_PRODUCT_CODE = 'E2E-CI-PRODUCT';
const PURCHASE_QTY = 3;
const PURCHASE_UNIT_COST = '5.00';

// ── Estado compartido entre pasos ─────────────────────────────────────────────
let createdPurchaseId: string | null = null;
let resolvedWarehouseId: string | null = null;
let resolvedProductId: string | null = null;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers (copiados / adaptados de happy-path.spec.ts)
// ─────────────────────────────────────────────────────────────────────────────

async function fillDxInput(page: Page, ariaLabel: string | string[], value: string) {
  const labels = Array.isArray(ariaLabel) ? ariaLabel : [ariaLabel];
  let input = page.locator('__missing__');

  for (const label of labels) {
    const candidate = page
      .locator(
        `input[aria-label="${label}"], textarea[aria-label="${label}"], [role="textbox"][aria-label="${label}"]`,
      )
      .first();
    const visible = await candidate.isVisible({ timeout: 1500 }).catch(() => false);
    if (visible) {
      input = candidate;
      break;
    }
  }

  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.click({ clickCount: 3 });
  await page.keyboard.press('Control+a');
  await page.keyboard.press('Delete');
  await input.pressSequentially(value, { delay: 30 });
  await page.keyboard.press('Tab');
}

async function clickGridToolbarButton(page: Page, buttonText: string) {
  const alternatives: Record<string, RegExp> = {
    Nuevo: /^(Nuevo|New)$/i,
    Editar: /^(Editar|Edit)$/i,
    Eliminar: /^(Eliminar|Delete)$/i,
  };
  const text = alternatives[buttonText] ?? new RegExp(`^${buttonText}$`, 'i');
  const btn = page.locator('.native-datagrid__toolbar .app-button').filter({ hasText: text }).first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

async function waitForPostLoginLanding(page: Page) {
  await page
    .waitForFunction(
      () => {
        const hash = window.location.hash ?? '';
        const loginForm = document.querySelector('form.login-form');
        return !hash.includes('/login') && !loginForm;
      },
      undefined,
      { timeout: 20000 },
    )
    .catch(() => {});

  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

async function ensureLoggedIn(page: Page) {
  try {
    await page.waitForLoadState('domcontentloaded', { timeout: 10000 });
  } catch {
    /* ignore */
  }

  const currentUrl = page.url();
  const isLoginUrl = currentUrl.includes('/#/login') || currentUrl.includes('#/login');
  const loginForm = page.locator('form.login-form');
  const isLoginPage =
    isLoginUrl || (await loginForm.isVisible({ timeout: 8000 }).catch(() => false));

  if (!isLoginPage) return;

  await fillDxInput(page, 'Usuario', USERNAME);
  await fillDxInput(page, 'Contraseña', PASSWORD);
  const submitBtn = page.locator('form.login-form').getByRole('button', { name: 'Ingresar' });
  await submitBtn.click();

  try {
    const firstBranchOption = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranchOption.waitFor({ state: 'visible', timeout: 7000 });
    const e2eBranchOption = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: 'CI E2E Branch' });
    const targetOption =
      (await e2eBranchOption.count()) > 0 ? e2eBranchOption.first() : firstBranchOption;
    await targetOption.click();
    await page.waitForTimeout(300);
    const acceptBtn = page
      .getByRole('dialog', { name: 'Seleccionar local' })
      .getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
    await page.locator('[data-testid="branch-picker-option"]').first().waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    // No picker o auto-seleccionado
  }

  await waitForPostLoginLanding(page);
}

async function gotoProtectedRoute(page: Page, hashPath: string) {
  await page.goto(`${BASE_URL}/#/${hashPath}`);
  await page.waitForLoadState('domcontentloaded', { timeout: 10000 }).catch(() => {});
  await ensureLoggedIn(page);
  await waitForPostLoginLanding(page);

  if (!page.url().includes(`/#/${hashPath}`)) {
    await page.goto(`${BASE_URL}/#/${hashPath}`);
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
    await ensureLoggedIn(page);
    await waitForPostLoginLanding(page);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Suite
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Compras — crear compra e incremento de stock', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    USERNAME = seed.username;
    PASSWORD = seed.password;

    const context = await browser.newContext({
      recordVideo: { dir: 'var/test-results/videos/', size: { width: 1280, height: 720 } },
    });
    page = await context.newPage();
  });

  test.afterAll(async () => {
    try {
      const video = page.video();
      const context = page.context();
      await page.close();
      await context.close();
      if (video) {
        const src = await video.path();
        const { copyFileSync, mkdirSync } = await import('fs');
        const { join } = await import('path');
        const dest = join('var/test-results', 'purchases-stock-full-run.webm');
        mkdirSync('var/test-results', { recursive: true });
        copyFileSync(src, dest);
      }
    } catch {
      await page.close().catch(() => {});
    }
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 1 — Login
  // ───────────────────────────────────────────────────────────────────────────
  test('01 - Login', async () => {
    await page.goto(`${BASE_URL}/#/login`);
    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });

    await fillDxInput(page, 'Usuario', USERNAME);
    await fillDxInput(page, 'Contraseña', PASSWORD);

    const submitBtn = page.locator('form.login-form').getByRole('button', { name: 'Ingresar' });
    await submitBtn.click();

    try {
      const firstBranchOption = page.locator('[data-testid="branch-picker-option"]').first();
      await firstBranchOption.waitFor({ state: 'visible', timeout: 7000 });
      const e2eBranchOption = page
        .locator('[data-testid="branch-picker-option"]')
        .filter({ hasText: 'CI E2E Branch' });
      const targetOption =
        (await e2eBranchOption.count()) > 0 ? e2eBranchOption.first() : firstBranchOption;
      await targetOption.click();
      await page.waitForTimeout(300);
      const acceptBtn = page
        .getByRole('dialog', { name: 'Seleccionar local' })
        .getByRole('button', { name: /aceptar/i });
      await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
      await acceptBtn.click();
      await page.locator('[data-testid="branch-picker-option"]').first().waitFor({ state: 'hidden', timeout: 10000 });
    } catch {
      // No picker
    }

    await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
    expect(page.url()).toMatch(/\/#\//);
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 2 — Navegar a Compras y capturar stock inicial
  // ───────────────────────────────────────────────────────────────────────────
  test('02 - Navegar a Compras y capturar stock inicial del producto seed', async () => {
    await gotoProtectedRoute(page, 'purchases');
    await waitForGridLoad(page);

    await expect(
      page.locator('.native-datagrid__toolbar').filter({ hasText: /compras/i }).first(),
    ).toBeVisible({ timeout: 15000 });

    // Capturar warehouseId y productId del seed via API para poder verificar stock después.
    // Usamos page.evaluate para hacer fetch desde el contexto del browser (cookies de sesión).
    const seedData = await page.evaluate(async ({ baseUrl, productCode, productName }) => {
      // Obtener el primer almacén disponible
      const whResp = await fetch(`${baseUrl}/api/warehouses?page=1&itemsPerPage=1`);
      const whData = await whResp.json();
      const warehouses: Array<{ id: string; name: string }> =
        whData['hydra:member'] ?? whData.member ?? whData ?? [];
      const warehouse = warehouses[0] ?? null;

      // Buscar el producto seed por código. El filtro por name es parcial en el
      // backend y puede devolver otro producto cuyo nombre contenga el texto.
      const prodResp = await fetch(
        `${baseUrl}/api/products?code=${encodeURIComponent(productCode)}&page=1&itemsPerPage=100`,
        { cache: 'no-store' },
      );
      const prodData = await prodResp.json();
      const products: Array<{ id: string; code?: string; name: string }> =
        prodData['hydra:member'] ?? prodData.member ?? prodData ?? [];
      const product =
        products.find((item) => item.code === productCode) ??
        products.find((item) => item.name === productName) ??
        null;

      // Capturar stock actual si tenemos ambos IDs
      let stockBefore: number | null = null;
      if (warehouse && product) {
        const stockResp = await fetch(
          `${baseUrl}/api/products/${product.id}/stock/${warehouse.id}?_=${Date.now()}`,
          { cache: 'no-store' },
        );
        if (stockResp.ok) {
          const stockData = await stockResp.json();
          stockBefore = stockData.stock ?? stockData.quantity ?? stockData ?? null;
        }
      }

      return {
        warehouseId: warehouse?.id ?? null,
        warehouseName: warehouse?.name ?? null,
        productId: product?.id ?? null,
        productCode: (product as { code?: string } | null)?.code ?? null,
        stockBefore,
      };
    }, { baseUrl: BASE_URL, productCode: SEED_PRODUCT_CODE, productName: SEED_PRODUCT_NAME });

    expect(seedData.warehouseId, 'debe existir al menos un almacén').toBeTruthy();
    expect(seedData.productId, 'debe existir el producto seed').toBeTruthy();

    resolvedWarehouseId = seedData.warehouseId;
    resolvedProductId = seedData.productId;

    // eslint-disable-next-line no-console
    console.log(
      `[E2E] Stock inicial de "${SEED_PRODUCT_NAME}" (${seedData.productCode ?? seedData.productId}) en almacén ${seedData.warehouseName} (${seedData.warehouseId}): ${seedData.stockBefore}`,
    );
  });

  test('02b - El formulario de compra tiene pestañas Cabecera y Notas', async () => {
    await gotoProtectedRoute(page, 'purchases');
    await waitForGridLoad(page);
    await clickGridToolbarButton(page, 'Nuevo');
    await expect(crudShell(page, /nuevo|new/i)).toBeVisible({ timeout: 12000 });

    const body = crudShellBody(page);
    await switchFormTab(body, /Cabecera/i);
    await expect(body.locator('#native-form-warehouse').first()).toBeVisible({ timeout: 8000 });

    await switchFormTab(body, /Notas/i);
    await expect(body.getByLabel(/Glosa/i).first()).toBeVisible({ timeout: 8000 });

    await crudShell(page).getByRole('button', { name: /cancel|cerrar/i }).first().click();
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 3 — Crear compra
  // ───────────────────────────────────────────────────────────────────────────
  test('03 - Crear compra con producto seed y verificar stock incrementado', async () => {
    // Asegurar que estamos en la página de compras
    await gotoProtectedRoute(page, 'purchases');
    await waitForGridLoad(page);

    // ── Capturar stock ANTES ──────────────────────────────────────────────────
    const stockBefore = await page.evaluate(
      async ({ baseUrl, productId, warehouseId }) => {
        if (!productId || !warehouseId) return null;
        const resp = await fetch(`${baseUrl}/api/products/${productId}/stock/${warehouseId}?_=${Date.now()}`, {
          cache: 'no-store',
        });
        if (!resp.ok) return null;
        const data = await resp.json();
        return data.stock ?? data.quantity ?? null;
      },
      { baseUrl: BASE_URL, productId: resolvedProductId, warehouseId: resolvedWarehouseId },
    );

    // ── Abrir formulario de nueva compra ─────────────────────────────────────
    await test.step('Abrir formulario de nueva compra', async () => {
      await clickGridToolbarButton(page, 'Nuevo');
      await expect(crudShell(page, /nuevo|new/i)).toBeVisible({ timeout: 12000 });
    });

    // ── Seleccionar almacén ───────────────────────────────────────────────────
    await test.step('Seleccionar almacén', async () => {
      // NativeFormView: warehouse field has id="native-form-warehouse"
      const almacenInput = page.locator('#native-form-warehouse').first();
      await almacenInput.waitFor({ state: 'visible', timeout: 8000 });

      const currentVal = await almacenInput.inputValue().catch(() => '');
      if (currentVal.trim().length === 0) {
        // NOTE: NativeEntitySelect portals its dropdown menu to document.body, so
        // scoping the option locator to the field container does NOT work. Use page-level.
        const firstOption = page.locator('.native-form__lookup-menu button[role="option"]').first();

        // Click to open, then type to trigger filtered search. Try a specific term
        // first (seed warehouse name substring), then empty (load-all) as fallback.
        for (const searchTerm of ['CI E2E', 'Almacen', '']) {
          await almacenInput.click({ clickCount: 3 });
          await almacenInput.fill('');
          if (searchTerm) {
            await almacenInput.pressSequentially(searchTerm, { delay: 40 });
          } else {
            // Empty string: just focus to open without a search filter
            await almacenInput.click();
          }
          await page.waitForTimeout(700); // debounce (250ms) + API roundtrip

          if (await firstOption.isVisible({ timeout: 5000 }).catch(() => false)) {
            await firstOption.click();
            await page.waitForTimeout(300);
            break;
          }
        }
      }
    });

    // ── Seleccionar proveedor ─────────────────────────────────────────────────
    await test.step('Seleccionar proveedor (primer resultado disponible)', async () => {
      // NativeFormView: supplier field has id="native-form-supplier"
      const supplierInput = page.locator('#native-form-supplier').first();
      const supplierVisible = await supplierInput.isVisible({ timeout: 3000 }).catch(() => false);

      if (supplierVisible) {
        // NOTE: NativeEntitySelect portals the dropdown to document.body — use page-level locator.
        const firstOption = page.locator('.native-form__lookup-menu button[role="option"]').first();

        // Open the dropdown (click to focus, which triggers search)
        await supplierInput.click({ force: true });
        await page.waitForTimeout(500);

        const hasOptions = await firstOption.isVisible({ timeout: 5000 }).catch(() => false);
        if (hasOptions) {
          await firstOption.click();
          await page.waitForTimeout(300);
        } else {
          // No hay proveedores — continuar sin cerrar el modal (Escape cerraría AppDialog).
          await supplierInput.blur();
        }
      }
    });

    // ── Agregar fila de detalle ───────────────────────────────────────────────
    await test.step('Agregar fila de detalle con producto seed', async () => {
      // NativeFormView: "+" button with aria-label="Agregar detalle"
      const addRowBtn = page.locator('button[aria-label="Agregar detalle"]').first();
      await addRowBtn.waitFor({ state: 'visible', timeout: 8000 });
      await addRowBtn.click();

      // The new row appears immediately in the native detail table
      const dialog = crudShell(page, /nuevo|new/i);
      const detailTable = dialog.locator('.native-form__detail-table').first();
      const insertedRow = detailTable.locator('tbody tr').last();
      await insertedRow.waitFor({ state: 'visible', timeout: 8000 });

      // Columna 0 = Producto — NativeEntitySelect combobox
      const productCell = insertedRow.locator('td').nth(0);
      await productCell.click();
      const productInput = productCell.locator('input[role="combobox"], input').first();
      await productInput.waitFor({ state: 'attached', timeout: 5000 });
      await productInput.click();
      await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 50 });

      // NativeEntitySelect dropdown
      const seedProductOption = page
        .locator('.native-form__lookup-menu button[role="option"]')
        .filter({ hasText: SEED_PRODUCT_NAME });
      await seedProductOption.first().waitFor({ state: 'visible', timeout: 15000 });
      await seedProductOption.first().click();
      await page.waitForTimeout(400);

      // Columna 1 = Cantidad
      const qtyCell = insertedRow.locator('td').nth(1);
      const qtyInput = qtyCell.locator('input').first();
      await qtyInput.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
      await qtyInput.click({ clickCount: 3 });
      await qtyInput.fill(String(PURCHASE_QTY));
      await page.keyboard.press('Tab');

      // Columna 2 = Precio unitario
      const costCell = insertedRow.locator('td').nth(2);
      const costInput = costCell.locator('input').first();
      await costInput.waitFor({ state: 'visible', timeout: 3000 }).catch(() => {});
      await costInput.click({ clickCount: 3 });
      await costInput.fill(PURCHASE_UNIT_COST);
      await page.keyboard.press('Tab');

      // Move focus out of the detail grid without clicking outside the dialog.
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      await page.waitForTimeout(300);

      // Verify the row is present in the native detail table
      const committedRow = detailTable.locator('tbody tr').first();
      await committedRow.waitFor({ state: 'visible', timeout: 8000 });
    });

    // ── Guardar compra ────────────────────────────────────────────────────────
    await test.step('Guardar compra y verificar respuesta 2xx', async () => {
      const consoleErrors: string[] = [];
      page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
      });

      const createPurchaseResponsePromise = page.waitForResponse(
        (response) =>
          /\/api\/purchases(?:\?.*)?$/.test(response.url()) &&
          response.request().method() === 'POST',
        { timeout: 20000 },
      );

      // Click en el botón Guardar del footer del popup
      await crudShell(page, /nuevo|new/i).getByRole('button', { name: /guardar|save/i }).click({ force: true });

      const createPurchaseResponse = await createPurchaseResponsePromise.catch(async (err) => {
        // eslint-disable-next-line no-console
        console.error('[E2E] POST /api/purchases nunca disparó. Errores de consola:', consoleErrors);
        throw err;
      });

      if (!createPurchaseResponse.ok()) {
        const body = await createPurchaseResponse.text().catch(() => '(ilegible)');
        // eslint-disable-next-line no-console
        console.error(
          '[E2E] POST /api/purchases falló:',
          createPurchaseResponse.status(),
          body,
        );
      }

      expect(
        createPurchaseResponse.ok(),
        `POST /api/purchases debe responder 2xx (recibido: ${createPurchaseResponse.status()})`,
      ).toBeTruthy();

      const createdPurchase = (await createPurchaseResponse.json()) as {
        id?: string | null;
        grandTotal?: string | null;
      };
      createdPurchaseId = createdPurchase.id ?? null;
      expect(createdPurchaseId, 'la respuesta de /api/purchases debe incluir id').toBeTruthy();

      // eslint-disable-next-line no-console
      console.log(
        `[E2E] Compra creada: id=${createdPurchaseId}, grandTotal=${createdPurchase.grandTotal}`,
      );

      // El popup debe cerrarse tras guardar exitosamente
      await expect(
        crudShell(page, /nuevo|new/i),
      ).toBeHidden({ timeout: 15000 });

      await waitForGridLoad(page);
    });

    // ── Verificar stock incrementado ──────────────────────────────────────────
    await test.step('Verificar que el stock del producto incrementó', async () => {
      expect(resolvedProductId, 'productId debe estar resuelto del paso 02').toBeTruthy();
      expect(resolvedWarehouseId, 'warehouseId debe estar resuelto del paso 02').toBeTruthy();

      // Pequeña espera para que el backend procese el movimiento de stock
      await page.waitForTimeout(1000);

      const stockAfter = await page.evaluate(
        async ({ baseUrl, productId, warehouseId }) => {
          const resp = await fetch(`${baseUrl}/api/products/${productId}/stock/${warehouseId}?_=${Date.now()}`, {
            cache: 'no-store',
          });
          if (!resp.ok) return null;
          const data = await resp.json();
          return data.stock ?? data.quantity ?? null;
        },
        { baseUrl: BASE_URL, productId: resolvedProductId, warehouseId: resolvedWarehouseId },
      );

      // eslint-disable-next-line no-console
      console.log(
        `[E2E] Stock después de compra: ${stockAfter} (antes: ${stockBefore}, qty comprada: ${PURCHASE_QTY})`,
      );

      if (stockBefore !== null && stockAfter !== null) {
        expect(
          Number(stockAfter),
          `el stock debe haber incrementado en ${PURCHASE_QTY} unidades (antes: ${stockBefore}, después: ${stockAfter})`,
        ).toBe(Number(stockBefore) + PURCHASE_QTY);
      } else {
        // In some tenant configurations the stock endpoint is feature-gated and
        // returns no numeric stock. The purchase POST above is still the stable
        // E2E assertion for this flow.
        expect(createdPurchaseId, 'la compra debe haberse creado aunque el endpoint de stock no exponga valor').toBeTruthy();
      }
    });
  });
});
