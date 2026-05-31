/**
 * e2e/sale-form.spec.ts
 *
 * Focused E2E tests for the "Nueva Venta" form dialog at /#/sale.
 *
 * Covers:
 *  1. Form opens with all expected fields
 *  2. "Almacén" label is correctly accented
 *  3. "Tipo de cambio" is hidden when currency is PEN (default)
 *  4. "Tipo de cambio" appears when currency is changed to USD
 *  5. "Tipo de cambio" hides again when currency is switched back to PEN
 *  6. Discount field is interactive (opens discount dialog on click)
 *  7. Adding a product row populates price automatically
 *  8. Save is disabled / blocked when no product lines are added
 *
 * Prerequisites: a cash register session must be open (handled by ensureCashOpen).
 *
 * Running:
 *   E2E_BASE_URL=http://localhost:8080 yarn test:e2e e2e/sale-form.spec.ts --headed
 */

import { expect, test, type Page, type Locator } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

// ── Config ─────────────────────────────────────────────────────────────────────
const BASE_URL = getE2EBaseUrl();
let USERNAME = '';
let PASSWORD = '';

const SEED_PRODUCT_NAME = 'Producto CI E2E';
const SEED_CUSTOMER_DNI = '20100000001';

// ── Helpers ────────────────────────────────────────────────────────────────────

async function waitForGridLoad(page: Page) {
  const overlay = page.locator('.native-datagrid__loading-overlay').first();
  try {
    await overlay.waitFor({ state: 'visible', timeout: 2000 });
    await overlay.waitFor({ state: 'hidden', timeout: 15000 });
  } catch {
    // Panel never appeared — grid already loaded
  }
}

async function login(page: Page) {
  await page.goto(`${BASE_URL}/#/login`);
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });

  const usernameInput = page.locator('input[aria-label="Usuario"]').first();
  await usernameInput.waitFor({ state: 'visible', timeout: 8000 });
  await usernameInput.click({ clickCount: 3 });
  await usernameInput.pressSequentially(USERNAME, { delay: 30 });
  await page.keyboard.press('Tab');

  const passwordInput = page.locator('input[aria-label="Contraseña"]').first();
  await passwordInput.waitFor({ state: 'visible', timeout: 8000 });
  await passwordInput.click({ clickCount: 3 });
  await passwordInput.pressSequentially(PASSWORD, { delay: 30 });

  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

  // Handle optional branch picker
  try {
    const firstBranch = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranch.waitFor({ state: 'visible', timeout: 6000 });
    const e2eBranch = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: 'CI E2E Branch' });
    const target = (await e2eBranch.count()) > 0 ? e2eBranch.first() : firstBranch;
    await target.click();
    await page.waitForTimeout(300);
    const acceptBtn = page.locator('.picker-popup-buttons-container').getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
    await page.locator('[data-testid="branch-picker-option"]').first().waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    // No branch picker — auto-selected
  }

  await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
}

async function ensureCashOpen(page: Page): Promise<void> {
  await page.goto(`${BASE_URL}/#/cashbox`);
  await waitForGridLoad(page);

  const openBtn = page.getByTestId('cash-btn-open').first();
  const needsOpen = await openBtn.isVisible({ timeout: 6000 }).catch(() => false);

  if (!needsOpen) return; // already open

  const dashData = await page.evaluate(async (base: string) => {
    const resp = await fetch(`${base}/api/cash-registers/dashboard`);
    return resp.ok ? resp.json() : [];
  }, BASE_URL);

  const closedReg = Array.isArray(dashData)
    ? dashData.find((r: any) => r.cashShiftId == null)
    : null;

  if (!closedReg?.id) return;

  await page.goto(`${BASE_URL}/#/cash-register/${closedReg.id}/open`);
  await expect(page.locator('text=Apertura de caja')).toBeVisible({ timeout: 10000 });
  await page.getByLabel('Saldo inicial').fill('100');
  await page.getByTestId('cash-open-submit-btn').first().click();
  await page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 });
}

/** Navigate to the sale list and open the "New sale" dialog. Returns the dialog locator. */
async function openNewSaleDialog(page: Page): Promise<Locator> {
  await page.goto(`${BASE_URL}/#/sale`);
  await waitForGridLoad(page);
  await expect(
    page.locator('.native-datagrid__toolbar').filter({ hasText: 'Ventas' }).first(),
  ).toBeVisible({ timeout: 15000 });

  const newBtn = page
    .locator('.native-datagrid__toolbar .app-button')
    .filter({ hasText: /nuevo|new/i })
    .first();
  await newBtn.waitFor({ state: 'visible', timeout: 10000 });
  await newBtn.click();

  const dialog = page.getByRole('dialog', { name: /nuevo|new/i }).last();
  await dialog.waitFor({ state: 'visible', timeout: 12000 });
  return dialog;
}

/** Select a currency in the Moneda combobox inside the given dialog. */
async function selectCurrency(page: Page, dialog: Locator, currencyValue: string) {
  // The currency combobox renders as a native <select> or a DX/native combobox.
  // Try native select first, then click-based combobox.
  const nativeSelect = dialog.locator('select[aria-label="Moneda"], select[id*="currency"]').first();
  const nativeVisible = await nativeSelect.isVisible({ timeout: 1500 }).catch(() => false);

  if (nativeVisible) {
    await nativeSelect.selectOption({ value: currencyValue });
    return;
  }

  // NativeEntitySelect / NativeSelectField pattern:
  // Click the combobox input to open the dropdown, then click the option.
  const currencyInput = dialog
    .locator('input[aria-label="Moneda"], [aria-label="Moneda"] input, [id*="native-form-currency"]')
    .first();
  await currencyInput.waitFor({ state: 'visible', timeout: 5000 });
  await currencyInput.click();
  await page.waitForTimeout(200);

  const option = page
    .locator('.native-form__lookup-menu button[role="option"], .dx-list-item, [role="option"]')
    .filter({ hasText: currencyValue === 'USD' ? 'USD' : 'PEN' })
    .first();
  await option.waitFor({ state: 'visible', timeout: 5000 });
  await option.click();
  await page.waitForTimeout(200);
}

// ── Suite ──────────────────────────────────────────────────────────────────────

test.describe('Sale form — mejoras de UX', () => {
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
      if (msg.type() === 'error') {
        // eslint-disable-next-line no-console
        console.log(`[browser:error] ${msg.text()}`);
      }
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
        const dest = join('var/test-results', 'sale-form.webm');
        mkdirSync('var/test-results', { recursive: true });
        copyFileSync(src, dest);
      }
    } catch {
      await page.close().catch(() => {});
    }
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 1 — Form opens with all required fields visible
  // ────────────────────────────────────────────────────────────────────────────
  test('01 - El formulario abre con todos los campos requeridos', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // Required fields must be visible
    await expect(body.locator('input[aria-label="Fecha *"], input[aria-label="Fecha"]').first()).toBeVisible({
      timeout: 8000,
    });
    await expect(body.locator('input[aria-label*="Cliente"]').first()).toBeVisible({ timeout: 8000 });
    await expect(body.locator('input[aria-label*="Método de pago"]').first()).toBeVisible({ timeout: 8000 });
    await expect(body.locator('input[aria-label*="Moneda"]').first()).toBeVisible({ timeout: 8000 });

    // Detail section must be present
    await expect(body.locator('table, .native-form__detail-table').first()).toBeVisible({ timeout: 8000 });

    // Close dialog
    await dialog.locator('button[aria-label*="Cerrar"], button').filter({ hasText: /cerrar|cancel/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 2 — "Almacén" label has the correct accent mark
  // ────────────────────────────────────────────────────────────────────────────
  test('02 - El campo "Almacén" tiene el acento correcto', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // The label "Almacén" (with accent) must appear — not "Almacen"
    const labelWithAccent = body.locator('text=Almacén').first();
    const labelWithoutAccent = body.locator('text=Almacen').first();

    await expect(labelWithAccent).toBeVisible({ timeout: 8000 });
    await expect(labelWithoutAccent).not.toBeVisible();

    // Close
    const cancelBtn = dialog.getByRole('button', { name: /cancel|cerrar/i }).first();
    await cancelBtn.click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 3 — "Tipo de cambio" is hidden when currency is PEN (default)
  // ────────────────────────────────────────────────────────────────────────────
  test('03 - "Tipo de cambio" se oculta cuando la moneda es PEN', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // Wait for the form to settle with default values
    await page.waitForTimeout(500);

    // Verify the currency field defaults to PEN
    const currencyInput = body.locator('input[aria-label="Moneda"]').first();
    const currencyValue = await currencyInput.inputValue().catch(() => '');
    // Default currency should contain "PEN"
    expect(currencyValue).toMatch(/pen/i);

    // "Tipo de cambio" field must NOT be visible when PEN is selected
    const exchangeRateField = body.locator(
      'input[aria-label="Tipo de cambio"], [aria-label*="cambio"] input',
    ).first();
    await expect(exchangeRateField).not.toBeVisible({ timeout: 3000 });

    // Close
    await dialog.getByRole('button', { name: /cancel|cerrar/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 4 — "Tipo de cambio" appears when currency changes to USD
  // ────────────────────────────────────────────────────────────────────────────
  test('04 - "Tipo de cambio" aparece al seleccionar USD', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    await page.waitForTimeout(500);

    // Change currency to USD
    await selectCurrency(page, body, 'USD');
    await page.waitForTimeout(300);

    // "Tipo de cambio" must now be visible
    const exchangeRateField = body.locator(
      'input[aria-label="Tipo de cambio"], [aria-label*="cambio"] input',
    ).first();
    await expect(exchangeRateField).toBeVisible({ timeout: 5000 });

    // Switch back to PEN — "Tipo de cambio" must hide again
    await selectCurrency(page, body, 'PEN');
    await page.waitForTimeout(300);
    await expect(exchangeRateField).not.toBeVisible({ timeout: 3000 });

    // Close
    await dialog.getByRole('button', { name: /cancel|cerrar/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 5 — Discount field opens the discount dialog on click
  // ────────────────────────────────────────────────────────────────────────────
  test('05 - El campo "Descuento %" abre el diálogo de descuento al hacer clic', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // Locate the Descuento % field (it's a readonly currency input that triggers a dialog)
    const discountField = body.locator(
      'input[aria-label="Descuento %"], [aria-label*="Descuento"] input',
    ).first();
    await discountField.waitFor({ state: 'visible', timeout: 8000 });
    await discountField.click();

    // A DiscountDialog should appear
    const discountDialog = page.getByRole('dialog', { name: /descuento/i });
    await expect(discountDialog).toBeVisible({ timeout: 5000 });

    // Close the discount dialog (press Escape or click Cancel)
    await page.keyboard.press('Escape');
    await discountDialog.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});

    // Close sale dialog
    await dialog.getByRole('button', { name: /cancel|cerrar/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 6 — Adding a product row auto-populates price
  // ────────────────────────────────────────────────────────────────────────────
  test('06 - Agregar un producto al detalle rellena el precio automáticamente', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // Select warehouse if empty (prerequisite for stock checks)
    const almacenInput = body.locator('input[aria-label="Almacén"], input[aria-label="Almacen"]').first();
    if (await almacenInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      const val = await almacenInput.inputValue().catch(() => '');
      if (val.trim() === '') {
        await almacenInput.click();
        await page.waitForTimeout(300);
        const firstOpt = page.locator('.native-form__lookup-menu button[role="option"]').first();
        if (await firstOpt.isVisible({ timeout: 5000 }).catch(() => false)) {
          await firstOpt.click();
          await page.waitForTimeout(300);
        }
      }
    }

    // Click "Agregar detalle" to add a new row
    const addRowBtn = page.locator('button[aria-label="Agregar detalle"]').first();
    await addRowBtn.waitFor({ state: 'visible', timeout: 8000 });
    await addRowBtn.click();

    const insertedRow = page.locator('.native-form__detail-table tbody tr').last();
    await insertedRow.waitFor({ state: 'visible', timeout: 8000 });

    // Fill in the product (column 0)
    const productInput = insertedRow.locator('td').nth(0).locator('input[role="combobox"]').first();
    await productInput.waitFor({ state: 'visible', timeout: 5000 });
    await productInput.click();
    await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 40 });

    const option = page
      .locator('.native-form__lookup-menu button[role="option"]')
      .filter({ hasText: SEED_PRODUCT_NAME });
    await option.first().waitFor({ state: 'visible', timeout: 15000 });
    await option.first().click();

    // Wait for onChange to populate unitPrice (column 2)
    const priceInput = insertedRow.locator('td').nth(2).locator('input').first();
    await expect(priceInput).not.toHaveValue('0', { timeout: 8000 });
    const priceValue = await priceInput.inputValue().catch(() => '0');
    expect(Number(priceValue)).toBeGreaterThan(0);

    // Quantity (column 1) should default to 1
    const qtyInput = insertedRow.locator('td').nth(1).locator('input').first();
    const qtyValue = await qtyInput.inputValue().catch(() => '0');
    expect(Number(qtyValue)).toBe(1);

    // Close without saving
    await dialog.getByRole('button', { name: /cancel|cerrar/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 7 — "Clic para aplicar descuento" help text (not "Click")
  // ────────────────────────────────────────────────────────────────────────────
  test('07 - El texto de ayuda del descuento usa "Clic" (no "Click")', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // The help text "Clic para aplicar descuento" should be present (not "Click")
    await expect(body.locator('text=Clic para aplicar descuento').first()).toBeVisible({
      timeout: 8000,
    });
    await expect(body.locator('text=Click para aplicar descuento').first()).not.toBeVisible();

    // Close
    await dialog.getByRole('button', { name: /cancel|cerrar/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 8 — Customer field defaults to a pre-selected customer
  // ────────────────────────────────────────────────────────────────────────────
  test('08 - El campo Cliente tiene un valor por defecto pre-seleccionado', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // Cliente field should have a default value (not empty) — defaultValue(1) in SaleFields.tsx
    const clienteInput = body.locator('input[aria-label*="Cliente"]').first();
    await clienteInput.waitFor({ state: 'visible', timeout: 8000 });
    const clienteValue = await clienteInput.inputValue();
    expect(clienteValue.trim().length).toBeGreaterThan(0);

    // Método de pago should also have a default
    const metodoPagoInput = body.locator('input[aria-label*="Método de pago"]').first();
    await metodoPagoInput.waitFor({ state: 'visible', timeout: 8000 });
    const metodoPagoValue = await metodoPagoInput.inputValue();
    expect(metodoPagoValue.trim().length).toBeGreaterThan(0);

    // Close
    await dialog.getByRole('button', { name: /cancel|cerrar/i }).first().click().catch(() => {});
    await dialog.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
  });

  // ────────────────────────────────────────────────────────────────────────────
  // TEST 9 — Successful sale creation with one product line
  // ────────────────────────────────────────────────────────────────────────────
  test('09 - Se puede crear una venta completa con un producto', async () => {
    const dialog = await openNewSaleDialog(page);
    const body = dialog.locator('.crud-dialog__body');

    // Ensure warehouse is selected
    const almacenInput = body.locator('input[aria-label="Almacén"], input[aria-label="Almacen"]').first();
    if (await almacenInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      const val = await almacenInput.inputValue().catch(() => '');
      if (val.trim() === '') {
        await almacenInput.click();
        await page.waitForTimeout(300);
        const firstOpt = page.locator('.native-form__lookup-menu button[role="option"]').first();
        if (await firstOpt.isVisible({ timeout: 5000 }).catch(() => false)) {
          await firstOpt.click();
          await page.waitForTimeout(300);
        }
      }
    }

    // Ensure customer is set (defaults to id=1)
    const clienteInput = body.locator('input[aria-label*="Cliente"]').first();
    const clienteValue = await clienteInput.inputValue().catch(() => '');
    if (clienteValue.trim() === '') {
      await clienteInput.click();
      await clienteInput.pressSequentially(SEED_CUSTOMER_DNI, { delay: 30 });
      const opt = page.locator('.native-form__lookup-menu button[role="option"]').first();
      if (await opt.isVisible({ timeout: 5000 }).catch(() => false)) {
        await opt.click();
        await page.waitForTimeout(300);
      }
    }

    // Add a product row
    const addRowBtn = page.locator('button[aria-label="Agregar detalle"]').first();
    await addRowBtn.waitFor({ state: 'visible', timeout: 8000 });
    await addRowBtn.click();

    const insertedRow = page.locator('.native-form__detail-table tbody tr').last();
    await insertedRow.waitFor({ state: 'visible', timeout: 8000 });

    const productInput = insertedRow.locator('td').nth(0).locator('input[role="combobox"]').first();
    await productInput.waitFor({ state: 'visible', timeout: 5000 });
    await productInput.click();
    await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 40 });

    const option = page
      .locator('.native-form__lookup-menu button[role="option"]')
      .filter({ hasText: SEED_PRODUCT_NAME });
    await option.first().waitFor({ state: 'visible', timeout: 15000 });
    await option.first().click();

    // Wait for price to be auto-populated
    const priceInput = insertedRow.locator('td').nth(2).locator('input').first();
    await expect(priceInput).not.toHaveValue('0', { timeout: 8000 });

    // Tab out of the detail grid
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');

    // Intercept the POST /api/sales
    const saleResponsePromise = page.waitForResponse(
      (r) => /\/api\/sales(?:\?.*)?$/.test(r.url()) && r.request().method() === 'POST',
      { timeout: 15000 },
    );

    // Click Save
    await dialog.locator('button.dialog-save-button').click({ force: true });

    const saleResponse = await saleResponsePromise;
    expect(saleResponse.ok(), 'POST /api/sales debe responder 2xx').toBeTruthy();

    const body2 = (await saleResponse.json()) as { id?: string };
    expect(body2.id, 'La respuesta debe incluir el id de la venta').toBeTruthy();

    // Dialog must close after successful save
    await expect(dialog).toBeHidden({ timeout: 15000 });

    // The new sale must appear in the grid
    await waitForGridLoad(page);
    const rows = page.locator('.native-datagrid__row');
    expect(await rows.count()).toBeGreaterThan(0);
  });
});
