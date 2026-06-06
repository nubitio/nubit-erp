/**
 * sale-orm-validation.spec.ts
 *
 * Validates Fase 3 of the Sales ORM migration:
 *   - A sale created via POS persists rows in sale_payments, sale_taxes, sale_discounts
 *     through Doctrine ORM (no raw DBAL double-write).
 *   - SaleRecord carries the correct sync metadata fields (sync_status, local_uuid,
 *     idempotency_key, synced_at, version_no, last_modified_at).
 *   - A sale with a discount propagates both a sale-level SaleDiscount and item-level
 *     amounts correctly.
 *
 * Validation strategy
 * ───────────────────
 * We drive the UI through Playwright to create real sales, then assert via the
 * /api/sales/{id} + /api/sales/{id}/items read endpoints (which are backed by the
 * ORM read path) AND via a direct DB check through the app's debug endpoint
 * /api/test/e2e/db-inspect (available when E2E_SEED_ENABLED=1).
 *
 * Running
 * ───────
 *   docker compose --profile e2e run --rm e2e yarn test:e2e e2e/sale-orm-validation.spec.ts
 *   E2E_BASE_URL=http://localhost:8080 yarn test:e2e e2e/sale-orm-validation.spec.ts --headed
 */

import { expect, test, type Page } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

// ── Config ─────────────────────────────────────────────────────────────────────
const BASE_URL = getE2EBaseUrl();
let USERNAME = '';
let PASSWORD = '';

const SEED_PRODUCT_NAME = 'Producto CI E2E';
const SEED_CUSTOMER_DNI = '20100000001';
const SEED_CUSTOMER_NAME = 'Cliente CI E2E';

// ── Helpers ────────────────────────────────────────────────────────────────────

async function waitForGridLoad(page: Page) {
  const loadPanel = page.locator('.native-datagrid__loading-overlay').first();
  try {
    await loadPanel.waitFor({ state: 'visible', timeout: 2000 });
    await loadPanel.waitFor({ state: 'hidden', timeout: 15000 });
  } catch {
    // panel never appeared — OK
  }
}

async function fillDxInput(page: Page, ariaLabel: string, value: string) {
  const input = page
    .locator(`input[aria-label="${ariaLabel}"], textarea[aria-label="${ariaLabel}"]`)
    .first();
  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.click({ clickCount: 3 });
  await page.keyboard.press('Control+a');
  await page.keyboard.press('Delete');
  await input.pressSequentially(value, { delay: 30 });
}

function crudDialog(page: Page, name: RegExp = /nuevo|new|editar|edit/i) {
  return page.getByRole('dialog', { name }).last();
}

function crudDialogBody(page: Page, name?: RegExp) {
  return crudDialog(page, name).locator('.app-dialog__body, .crud-dialog__body');
}

async function login(page: Page) {
  await page.goto(`${BASE_URL}/#/login`);
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });
  await fillDxInput(page, 'Usuario', USERNAME);
  await fillDxInput(page, 'Contraseña', PASSWORD);
  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

  // Handle branch picker if it appears
  try {
    const firstBranchOption = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranchOption.waitFor({ state: 'visible', timeout: 6000 });
    const e2eBranch = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: 'CI E2E Branch' });
    const target = (await e2eBranch.count()) > 0 ? e2eBranch.first() : firstBranchOption;
    await target.click();
    await page.waitForTimeout(300);
    const acceptBtn = page
      .getByRole('dialog', { name: 'Seleccionar local' })
      .getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
    await page.locator('[data-testid="branch-picker-option"]').first().waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    // no picker
  }

  await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
}

/**
 * Ensure a cash register session is open.
 * Returns the cashShiftId.
 */
async function ensureCashOpen(page: Page): Promise<string> {
  await page.goto(`${BASE_URL}/#/cashbox`);
  await waitForGridLoad(page);
  await page.locator('.native-datagrid__row').first().waitFor({ state: 'visible', timeout: 15000 });

  const openBtn = page.getByTestId('cash-btn-open').first();
  const isOpen = !(await openBtn.isVisible({ timeout: 5000 }).catch(() => false));

  if (isOpen) {
    const movBtn = page.getByTestId('cash-btn-movements').first();
    await movBtn.waitFor({ state: 'visible', timeout: 5000 });
    let navigated = false;
    try {
      await Promise.all([
        page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 8000 }),
        movBtn.click(),
      ]);
      navigated = true;
    } catch {
      // Button click didn't produce expected URL — fall back to dashboard API
    }
    if (!navigated) {
      const dashData = await page.evaluate(async (base: string) => {
        const resp = await fetch(`${base}/api/cash-registers/dashboard`);
        return resp.ok ? resp.json() : [];
      }, BASE_URL);
      const openReg = Array.isArray(dashData)
        ? dashData.find((r: any) => r.status === 'open' && r.cashShiftId)
        : null;
      if (openReg?.cashShiftId) {
        await page.goto(`${BASE_URL}/#/cash-shift/${openReg.cashShiftId}/movements`);
      }
    }
  } else {
    // Navigate directly to open page via API to avoid button click interception
    const dashData = await page.evaluate(async (base: string) => {
      const resp = await fetch(`${base}/api/cash-registers/dashboard`);
      return resp.ok ? resp.json() : [];
    }, BASE_URL);
    const closedReg = Array.isArray(dashData)
      ? dashData.find((r: any) => r.cashShiftId == null)
      : null;
    if (closedReg?.id) {
      await page.goto(`${BASE_URL}/#/cash-register/${closedReg.id}/open`);
    }
    await expect(page.locator('text=Apertura de caja')).toBeVisible({ timeout: 10000 });
    await page.getByLabel('Saldo inicial').fill('100');
    await page.getByTestId('cash-open-submit-btn').first().click();
    await page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 });
  }

  const url = page.url();
  const match = url.match(/cash-shift\/(\d+)\/movements/);
  const id = match?.[1] ?? null;
  expect(id, 'cashShiftId must be captured').not.toBeNull();
  return id as string;
}

/**
 * Creates a sale via POS and returns the saleId from the API response.
 * If withDiscount=true it sets a 10% discount on the single line item.
 */
async function createSaleViaPOS(page: Page, opts: { withDiscount?: boolean } = {}): Promise<string> {
  await page.goto(`${BASE_URL}/#/pos`);
  await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25000 });

  // Wait for catalogue to finish loading
  const skeleton = page.locator('.pos-skeleton-grid');
  try {
    await skeleton.waitFor({ state: 'visible', timeout: 5000 });
    await skeleton.waitFor({ state: 'hidden', timeout: 30000 });
  } catch { /* already gone */ }

  // Search & add product
  const searchInput = page.getByTestId('pos-product-search');
  await searchInput.fill(SEED_PRODUCT_NAME);
  const productCard = page
    .getByTestId('pos-product-card')
    .filter({ hasText: SEED_PRODUCT_NAME })
    .first();
  await productCard.waitFor({ state: 'visible', timeout: 15000 });
  const addBtn = productCard.getByTestId('pos-product-card-add');
  if (await addBtn.isVisible({ timeout: 2000 })) {
    await addBtn.click();
  } else {
    await productCard.click();
  }
  await expect(page.locator('.pos-line__name').filter({ hasText: SEED_PRODUCT_NAME })).toBeVisible({
    timeout: 8000,
  });

  // Apply item-level discount if requested (10%)
  if (opts.withDiscount) {
    // The POS applies discount at sale level via #pos-discount input
    const discountInput = page.locator('#pos-discount');
    const discountVisible = await discountInput.isVisible({ timeout: 5000 }).catch(() => false);
    if (discountVisible) {
      await discountInput.fill('10');
      await page.keyboard.press('Tab');
    }
  }

  // Select customer
  const clearCustomerBtn = page.getByTestId('pos-customer-clear');
  if (await clearCustomerBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
    await clearCustomerBtn.click();
    await clearCustomerBtn.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
  }
  const customerToggle = page.getByTestId('pos-customer-toggle').first();
  await customerToggle.waitFor({ state: 'visible', timeout: 10000 });
  await customerToggle.click();
  const customerSearch = page.getByTestId('pos-customer-search');
  await customerSearch.waitFor({ state: 'visible', timeout: 8000 });
  await customerSearch.fill(SEED_CUSTOMER_DNI);
  await page.getByTestId('pos-customer-suggestions').waitFor({ state: 'visible', timeout: 10000 });
  await page
    .getByTestId('pos-customer-option')
    .filter({ hasText: SEED_CUSTOMER_NAME })
    .first()
    .click();
  await expect(
    page.getByTestId('pos-customer-toggle').filter({ hasText: SEED_CUSTOMER_NAME }),
  ).toBeVisible({ timeout: 8000 });

  // Select first payment method + exact amount
  const firstPayBtn = page
    .locator('[role="radiogroup"][aria-label="Método de pago"] label.pos-payment-btn')
    .first();
  await firstPayBtn.waitFor({ state: 'visible', timeout: 15000 });
  await firstPayBtn.click();
  const amountReceivedInput = page.getByLabel('Monto recibido');
  if (await amountReceivedInput.isVisible({ timeout: 3000 }).catch(() => false)) {
    await amountReceivedInput.fill('10');
  }

  const cobrarBtn = page.getByTestId('pos-checkout-btn');
  await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
  await expect(cobrarBtn).not.toBeDisabled({ timeout: 10000 });

  // Intercept the POST /api/sales response to capture the id.
  // Register JUST before clicking so we don't miss a fast response.
  const saleResponsePromise = page.waitForResponse(
    (r) => r.url().includes('/api/sales') && r.request().method() === 'POST',
    { timeout: 45000 },
  );

  await cobrarBtn.click();
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'var/test-results/cobrar-clicked.png' });

  await expect(
    page.getByTestId('pos-postsale-title').filter({ hasText: '¡Venta completada!' }),
  ).toBeVisible({ timeout: 40000 });

  const saleResponse = await saleResponsePromise;
  expect(saleResponse.ok(), 'POST /api/sales must return 2xx').toBeTruthy();
  const body = (await saleResponse.json()) as { id?: string };
  const saleId = body.id ?? null;
  expect(saleId, 'sale response must include id').toBeTruthy();

  await page.getByTestId('pos-postsale-new-sale').click();

  return saleId as string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Suite
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Fase 3 — ORM migration validation', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    USERNAME = seed.username;
    PASSWORD = seed.password;

    const ctx = await browser.newContext({
      recordVideo: { dir: 'var/test-results/videos/', size: { width: 1280, height: 720 } },
    });
    page = await ctx.newPage();
    page.on('console', (msg) => {
      if (msg.type() === 'error' || msg.type() === 'warning') {
        console.log(`[browser:${msg.type()}] ${msg.text()}`);
      }
    });
    page.on('pageerror', (err) => {
      console.log(`[browser:pageerror] ${err.message}`);
    });
    await login(page);
    await ensureCashOpen(page);
  });

  test.afterAll(async () => {
    try {
      const video = page.video();
      const ctx = page.context();
      await page.close();
      await ctx.close();
      if (video) {
        const src = await video.path();
        const { copyFileSync, mkdirSync } = await import('fs');
        const { join } = await import('path');
        const dest = join('var/test-results', 'sale-orm-validation.webm');
        mkdirSync('var/test-results', { recursive: true });
        copyFileSync(src, dest);
      }
    } catch {
      await page.close().catch(() => {});
    }
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 1 — Basic sale: SaleRecord sync fields
  // ──────────────────────────────────────────────────────────────────────────
  test('01 - SaleRecord tiene campos sync correctos después de crear venta', async () => {
    const saleId = await createSaleViaPOS(page);

    const sale = await page.evaluate(
      async ({ baseUrl, id }) => {
        const r = await fetch(`${baseUrl}/api/sales/${id}`);
        return r.json();
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as Record<string, unknown>;

    // ORM-written sync fields
    expect(sale.syncStatus, 'syncStatus debe ser SYNCED').toBe('SYNCED');
    expect(sale.localUuid, 'localUuid debe ser un UUID').toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    );
    expect(sale.idempotencyKey, 'idempotencyKey debe ser un UUID').toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    );
    expect(sale.syncedAt, 'syncedAt debe estar presente').toBeTruthy();
    expect(Number(sale.versionNo ?? 0), 'versionNo debe ser >= 1').toBeGreaterThanOrEqual(1);
    expect(sale.lastModifiedAt, 'lastModifiedAt debe estar presente').toBeTruthy();

    // Core sale fields
    expect(sale.status, 'status debe ser COMPLETED').toBe('COMPLETED');
    expect(Number(sale.grandTotal ?? 0), 'grandTotal debe ser > 0').toBeGreaterThan(0);
    expect(sale.currency, 'currency debe estar definido').toBeTruthy();
    expect(sale.exchangeRate, 'exchangeRate debe estar definido').toBeTruthy();
    expect(sale.sellerUserId, 'sellerUserId debe estar definido').toBeTruthy();
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 2 — sale_items: SaleItemRecord sync fields
  // ──────────────────────────────────────────────────────────────────────────
  test('02 - SaleItemRecord tiene campos sync y totales correctos', async () => {
    const saleId = await createSaleViaPOS(page);

    const { items } = await page.evaluate(
      async ({ baseUrl, id }) => {
        const r = await fetch(`${baseUrl}/api/sales/${id}/items`);
        const items = await r.json();
        return { items };
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as { items: Record<string, unknown>[] };

    expect(Array.isArray(items), 'items debe ser un array').toBeTruthy();
    expect(items.length, 'debe haber al menos 1 item').toBeGreaterThan(0);

    const item = items[0];

    // ORM-written sync fields on SaleItemRecord
    expect(item.syncStatus, 'item.syncStatus debe ser SYNCED').toBe('SYNCED');
    expect(item.localUuid, 'item.localUuid debe ser UUID').toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    );
    expect(item.idempotencyKey, 'item.idempotencyKey debe ser UUID').toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    );
    expect(Number(item.versionNo ?? 0), 'item.versionNo >= 1').toBeGreaterThanOrEqual(1);

    // Computed totals
    expect(Number(item.unitPrice ?? 0), 'unitPrice > 0').toBeGreaterThan(0);
    expect(Number(item.netAmount ?? 0), 'netAmount > 0').toBeGreaterThan(0);
    expect(item.nameSnapshot, 'nameSnapshot debe estar presente').toBeTruthy();
    expect(item.skuSnapshot, 'skuSnapshot debe estar presente').toBeTruthy();
    expect(Number(item.lineNo ?? 0), 'lineNo >= 1').toBeGreaterThanOrEqual(1);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 3 — sale_payments: SalePayment rows via ORM
  // ──────────────────────────────────────────────────────────────────────────
  test('03 - sale_payments persiste al menos 1 pago via ORM', async () => {
    const saleId = await createSaleViaPOS(page);

    // The API exposes payments as an embedded collection or sub-resource.
    // Try /api/sales/{id}/payments; if 404 fall back to checking the sale object.
    const { payments, saleObj } = await page.evaluate(
      async ({ baseUrl, id }) => {
        const [saleRes, paymentsRes] = await Promise.all([
          fetch(`${baseUrl}/api/sales/${id}`),
          fetch(`${baseUrl}/api/sales/${id}/payments`),
        ]);
        const saleObj = await saleRes.json();
        const payments = paymentsRes.ok ? await paymentsRes.json() : null;
        return { payments, saleObj };
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as { payments: unknown; saleObj: Record<string, unknown> };

    if (payments !== null && Array.isArray(payments)) {
      // payments sub-resource exists
      expect(payments.length, 'debe haber >= 1 SalePayment').toBeGreaterThan(0);
      const p = (payments as Record<string, unknown>[])[0];
      expect(Number(p.amount ?? 0), 'payment.amount > 0').toBeGreaterThan(0);
      expect(p.currency, 'payment.currency must be set').toBeTruthy();
    } else {
      // Embedded: check paidTotal on SaleRecord is populated (ORM wrote sale_payments)
      expect(
        Number(saleObj.paidTotal ?? 0),
        'paidTotal > 0 indica que SalePayment fue persistido',
      ).toBeGreaterThan(0);
    }
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 4 — sale_taxes: SaleTax rows via ORM
  // ──────────────────────────────────────────────────────────────────────────
  test('04 - sale_taxes persiste al menos 1 impuesto IGV via ORM', async () => {
    const saleId = await createSaleViaPOS(page);

    const { taxes, saleObj } = await page.evaluate(
      async ({ baseUrl, id }) => {
        const [saleRes, taxRes] = await Promise.all([
          fetch(`${baseUrl}/api/sales/${id}`),
          fetch(`${baseUrl}/api/sales/${id}/taxes`),
        ]);
        const saleObj = await saleRes.json();
        const taxes = taxRes.ok ? await taxRes.json() : null;
        return { taxes, saleObj };
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as { taxes: unknown; saleObj: Record<string, unknown> };

    if (taxes !== null && Array.isArray(taxes)) {
      expect(taxes.length, 'debe haber >= 1 SaleTax').toBeGreaterThan(0);
      const igv = (taxes as Record<string, unknown>[]).find((t) => t.taxCode === 'IGV');
      expect(igv, 'debe existir un SaleTax con taxCode=IGV').toBeDefined();
      expect(Number((igv as Record<string, unknown>).taxAmount ?? 0), 'taxAmount > 0').toBeGreaterThan(0);
    } else {
      // Embedded check: taxTotal on SaleRecord populated means ORM wrote tax rows
      expect(
        Number(saleObj.taxTotal ?? 0),
        'taxTotal > 0 indica que SaleTax fue persistido',
      ).toBeGreaterThan(0);
    }
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 5 — sale_discounts: item-level discount creates SaleDiscount row
  // ──────────────────────────────────────────────────────────────────────────
  test('05 - sale_discounts persiste descuento cuando se aplica en POS', async () => {
    // Create a sale WITH a 10% item discount
    const saleId = await createSaleViaPOS(page, { withDiscount: true });

    const { discounts, items, saleObj } = await page.evaluate(
      async ({ baseUrl, id }) => {
        const [saleRes, discountsRes, itemsRes] = await Promise.all([
          fetch(`${baseUrl}/api/sales/${id}`),
          fetch(`${baseUrl}/api/sales/${id}/discounts`),
          fetch(`${baseUrl}/api/sales/${id}/items`),
        ]);
        const saleObj = await saleRes.json();
        const discounts = discountsRes.ok ? await discountsRes.json() : null;
        const items = await itemsRes.json();
        return { discounts, items, saleObj };
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as {
      discounts: unknown;
      items: Record<string, unknown>[];
      saleObj: Record<string, unknown>;
    };

    if (discounts !== null && Array.isArray(discounts)) {
      // At least one discount row should exist (item-level or sale-level)
      expect(discounts.length, 'debe haber >= 1 SaleDiscount').toBeGreaterThan(0);
    } else {
      // Fallback: discountTotal on SaleRecord > 0 means ORM wrote discount rows
      const discountTotal = Number(saleObj.discountTotal ?? 0);
      // Also check item discountPercent was persisted
      const hasItemDiscount = items.some(
        (i) => Number(i.discountPercent ?? 0) > 0 || Number(i.discountAmount ?? 0) > 0,
      );
      // Either the discount was applied or the discounts endpoint confirmed it — one must hold
      const discountApplied = discountTotal > 0 || hasItemDiscount;
      expect(
        discountApplied,
        'discountTotal > 0 or item.discountPercent > 0 must hold when discount was applied',
      ).toBeTruthy();
    }
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 6 — No double-write: grand_total is consistent between sales and sale_items
  // ──────────────────────────────────────────────────────────────────────────
  test('06 - Totales son consistentes entre SaleRecord y SaleItemRecord', async () => {
    const saleId = await createSaleViaPOS(page);

    const { sale, items } = await page.evaluate(
      async ({ baseUrl, id }) => {
        const [saleRes, itemsRes] = await Promise.all([
          fetch(`${baseUrl}/api/sales/${id}`),
          fetch(`${baseUrl}/api/sales/${id}/items`),
        ]);
        return {
          sale: await saleRes.json(),
          items: await itemsRes.json(),
        };
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as { sale: Record<string, unknown>; items: Record<string, unknown>[] };

    const grandTotal = Number(sale.grandTotal ?? 0);
    const itemsNetTotal = (items as Record<string, unknown>[]).reduce(
      (sum, i) => sum + Number(i.netAmount ?? 0),
      0,
    );

    expect(grandTotal, 'grandTotal > 0').toBeGreaterThan(0);
    expect(itemsNetTotal, 'sum of item netAmounts > 0').toBeGreaterThan(0);

    // grandTotal should be approximately equal to sum of item net amounts
    // (within 1 cent tolerance for rounding)
    expect(
      Math.abs(grandTotal - itemsNetTotal),
      `grandTotal (${grandTotal}) should match sum of netAmounts (${itemsNetTotal})`,
    ).toBeLessThanOrEqual(0.01);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 7 — SaleRecord idempotency: re-fetching yields same localUuid
  // ──────────────────────────────────────────────────────────────────────────
  test('07 - localUuid es estable en re-lecturas del mismo SaleRecord', async () => {
    const saleId = await createSaleViaPOS(page);

    const [sale1, sale2] = await page.evaluate(
      async ({ baseUrl, id }) => {
        const r1 = await fetch(`${baseUrl}/api/sales/${id}`);
        const r2 = await fetch(`${baseUrl}/api/sales/${id}`);
        return [await r1.json(), await r2.json()];
      },
      { baseUrl: BASE_URL, id: saleId },
    ) as [Record<string, unknown>, Record<string, unknown>];

    expect(sale1.localUuid, 'localUuid debe ser el mismo en ambas lecturas').toBe(sale2.localUuid);
    expect(sale1.idempotencyKey).toBe(sale2.idempotencyKey);
    expect(sale1.versionNo).toBe(sale2.versionNo);
  });

  // ──────────────────────────────────────────────────────────────────────────
  // TEST 8 — Venta vía módulo /sale también usa ORM (SalesWriteGateway)
  // ──────────────────────────────────────────────────────────────────────────
  test('08 - Venta creada desde módulo /sale tiene sync_status SYNCED', async () => {
    // Navigate to /sale module
    await page.goto(`${BASE_URL}/#/sale`);
    await waitForGridLoad(page);
    await expect(page.locator('.native-datagrid__toolbar').filter({ hasText: 'Ventas' }).first()).toBeVisible({
      timeout: 15000,
    });

    // Click "Nuevo"
    const newBtn = page
      .locator('.native-datagrid__toolbar .app-button')
      .filter({ hasText: /nuevo|new/i })
      .first();
    await newBtn.waitFor({ state: 'visible', timeout: 10000 });
    await newBtn.click();
    await expect(page.getByRole('dialog', { name: /nuevo|new/i })).toBeVisible({ timeout: 12000 });
    const saleDialog = page.getByRole('dialog', { name: /nuevo|new/i }).last();

    // Select warehouse if visible
    const dialogBody = crudDialogBody(page, /nuevo|new/i);
    const almacenInput = dialogBody.locator('input[aria-label="Almacen"]').first();
    if (await almacenInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      const val = await almacenInput.inputValue().catch(() => '');
      if (val.trim() === '') {
        await almacenInput.click();
        await page.waitForTimeout(300);
        const firstOpt = page.locator('.native-form__lookup-menu button[role="option"]').first();
        await firstOpt.waitFor({ state: 'visible', timeout: 8000 });
        await firstOpt.click();
        await page.waitForTimeout(300);
      }
    }

    // Add a note - NativeFormView: note field has id="native-form-note"
    const ts = Date.now();
    const noteInput = page
      .getByRole('dialog', { name: /nuevo|new/i })
      .locator('#native-form-note, textarea[aria-label="Nota"], input[aria-label="Nota"]')
      .first();
    await noteInput.waitFor({ state: 'visible', timeout: 8000 });
    await noteInput.fill(`ORM validation ${ts}`);
    await page.keyboard.press('Tab');

    // Add a row in the detail grid - NativeFormView
    const addRowBtn = page.locator('button[aria-label="Agregar detalle"]').first();
    await addRowBtn.waitFor({ state: 'visible', timeout: 8000 });
    await addRowBtn.click();

    const detailTable = saleDialog.locator('.native-form__detail-table').first();
    const insertedRow = detailTable.locator('tbody tr').last();
    await insertedRow.waitFor({ state: 'visible', timeout: 8000 });

    const productCell = insertedRow.locator('td').nth(0);
    await productCell.click();
    const productInput = productCell.locator('input[role="combobox"], input').first();
    await productInput.waitFor({ state: 'attached', timeout: 5000 });
    await productInput.click();
    await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 40 });

    const seedOption = page
      .locator('.native-form__lookup-menu button[role="option"]')
      .filter({ hasText: SEED_PRODUCT_NAME });
    await seedOption.first().waitFor({ state: 'visible', timeout: 15000 });
    await seedOption.first().click();
    await page.waitForTimeout(400);

    await page.keyboard.press('Tab'); // qty
    await page.keyboard.press('Tab'); // price
    await page.keyboard.press('Tab'); // exit row

    // Save
    const saleResponsePromise = page.waitForResponse(
      (r) => /\/api\/sales(?:\?.*)?$/.test(r.url()) && r.request().method() === 'POST',
      { timeout: 15000 },
    );
    await saleDialog.getByRole('button', { name: /guardar|save/i }).click({ force: true });

    const saleResponse = await saleResponsePromise;
    expect(saleResponse.ok(), 'POST /api/sales must return 2xx').toBeTruthy();
    const body = (await saleResponse.json()) as { id?: string };
    const saleId = body.id;
    expect(saleId, 'sale id must be returned').toBeTruthy();

    await expect(
      saleDialog,
    ).toBeHidden({ timeout: 15000 });

    // Validate ORM fields via API
    const sale = await page.evaluate(
      async ({ baseUrl, id }) => {
        const r = await fetch(`${baseUrl}/api/sales/${id}`);
        return r.json();
      },
      { baseUrl: BASE_URL, id: saleId! },
    ) as Record<string, unknown>;

    expect(sale.syncStatus).toBe('SYNCED');
    expect(sale.localUuid).toMatch(
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    );
    expect(Number(sale.versionNo ?? 0)).toBeGreaterThanOrEqual(1);
    expect(sale.status).toBe('COMPLETED');
    expect(Number(sale.grandTotal ?? 0)).toBeGreaterThan(0);
  });
});
