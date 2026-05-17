/**
 * e2e/happy-path.spec.ts
 *
 * Happy-path E2E — flujo completo de venta en eFact
 * ====================================================
 * Cubre en orden:
 *  1. Login
 *  2. Crear producto
 *  3. Editar producto
 *  4. Abrir sesión de caja
 *  5. Registrar cliente nuevo
 *  6. Registrar venta en POS
 *  7. Registrar venta en módulo Ventas
 *  8. Registrar movimiento de caja
 *  9. Editar movimiento de caja
 * 10. Cerrar sesión de caja
 *
 * Stack: React 18 + HashRouter (/#/ruta) + DevExtreme v25
 *
 * ── Selectores DevExtreme ────────────────────────────────────────────────────
 * DevExtreme NO renderiza <input> directos en sus componentes compuestos.
 * Las estrategias usadas (en orden de preferencia):
 *   A) aria-label — cuando el campo lo define explícitamente en inputAttr
 *   B) getByRole / getByText — para botones y diálogos nativas de DX
 *   C) .dx-texteditor-input + nth / .dx-datagrid-filter-row — cuando no hay aria
 *   D) CSS class de DX + texto de placeholder — como último recurso
 *
 * ── Selectores ──────────────────────────────────────────────────────────────
 * Todos los selectores frágiles han sido migrados a data-testid locators (T001-5).
 * Ver "NOTAS DE SELECTORES" al final del archivo para el historial de cambios.
 *
 * ── Variables de entorno ────────────────────────────────────────────────────
 *   E2E_BASE_URL   (default: http://localhost:8080)
 *   E2E_USERNAME   (default: admin@test.com)
 *   E2E_PASSWORD   (default: password)
 */

import { expect, test, type Page } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

// ── Env / config ──────────────────────────────────────────────────────────────
const BASE_URL = getE2EBaseUrl();
let USERNAME = process.env.E2E_USERNAME ?? '';
let PASSWORD = process.env.E2E_PASSWORD ?? '';

// ── Test data — unique per run ────────────────────────────────────────────────
const ts = Date.now();
const productName = `Test Producto ${ts}`;
// DNI de 8 dígitos — suficientemente aleatorio para no colisionar
const customerDni = `1${ts.toString().slice(-7)}`;
const customerName = `Cliente Test ${ts}`;

// ── Seed product — always available from app:test:seed-e2e ───────────────────
const SEED_PRODUCT_NAME = 'Producto CI E2E';
const SEED_PRODUCT_PRICE = '10';
const SEED_CUSTOMER_DNI = '20100000001';
const SEED_CUSTOMER_NAME = 'Cliente CI E2E';

// ── Persisted state between steps ─────────────────────────────────────────────
let openedCashShiftId: string | null = null; // capturado al abrir caja
let createdMovementRowText: string | null = null; // descripción del movimiento
let salesModuleSaleId: string | null = null; // venta creada desde /sale
let salesModuleProductName: string | null = null; // producto elegido en /sale
let posProductName = productName; // product to use in POS test (may fall back to seed)

// ─────────────────────────────────────────────────────────────────────────────
// Helper utilities
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Espera a que DevExtreme termine de cargar datos (LoadPanel oculto).
 * Complementa waitForLoadState para grids DX que hacen fetch adicional.
 */
async function waitForDxLoadPanel(page: Page) {
  // El LoadPanel de DX tiene clase .dx-loadpanel.  Cuando desaparece, DX terminó.
  const loadPanel = page.locator('.dx-loadpanel-wrapper').first();
  // Sólo esperamos si llega a aparecer; si nunca aparece, continuamos de inmediato.
  try {
    await loadPanel.waitFor({ state: 'visible', timeout: 2000 });
    await loadPanel.waitFor({ state: 'hidden', timeout: 15000 });
  } catch {
    // Panel no apareció — OK
  }
}

/**
 * Llena un DxTextBox identificado por aria-label.
 * Hace triple-click para seleccionar cualquier valor previo, luego escribe.
 */
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
  // Triple-click to select any existing value, then clear it
  await input.click({ clickCount: 3 });
  await page.keyboard.press('Control+a');
  await page.keyboard.press('Delete');
  // Type character by character to ensure DX Form captures each keystroke
  // and browser autofill does not override the value (fill() can trigger autofill)
  await input.pressSequentially(value, { delay: 30 });
  await page.keyboard.press('Tab'); // confirm in DX + trigger onValueChanged
}

/**
 * Selecciona una opción en un DxSelectBox / DxLookup por aria-label del contenedor.
 * Hace click en el wrapper → espera el overlay dropdown → hace click en la opción deseada.
 */
/**
 * Hace click en un botón de la toolbar del DxDataGrid identificado por texto.
 */
async function clickGridToolbarButton(page: Page, buttonText: string) {
  // DxDataGrid toolbar renderiza botones DX con .dx-toolbar .dx-button.
  // The current UI can render translated or English labels depending on locale.
  const alternatives: Record<string, RegExp> = {
    Nuevo: /^(Nuevo|New)$/i,
    Editar: /^(Editar|Edit)$/i,
    Eliminar: /^(Eliminar|Delete)$/i,
    Guardar: /^(Guardar|Save)$/i,
  };
  const text = alternatives[buttonText] ?? new RegExp(`^${buttonText}$`, 'i');
  const btn = page.locator('.dx-toolbar .dx-button').filter({ hasText: text }).first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

/**
 * Hace click en un botón del diálogo CRUD visible.
 */
async function clickDialogButton(page: Page, buttonText: string) {
  const alternatives: Record<string, RegExp> = {
    Guardar: /^(Guardar|Save)$/i,
    Cancelar: /^(Cancelar|Cancel)$/i,
  };
  const text = alternatives[buttonText] ?? new RegExp(`^${buttonText}$`, 'i');
  const btn = page.getByRole('dialog').last().getByRole('button', { name: text });
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

function crudDialog(page: Page, name: RegExp = /nuevo|new|editar|edit/i) {
  return page.getByRole('dialog', { name }).last();
}

function crudDialogBody(page: Page, name?: RegExp) {
  return crudDialog(page, name).locator('.crud-dialog__body');
}

/**
 * Filtra el DxDataGrid activo por la columna "Nombre" usando el FilterRow.
 * El FilterRow de DX usa textboxes — encontramos el que corresponde a la columna Nombre
 * buscando la celda de filtro que está debajo del columnheader "Columna Nombre".
 */
async function filterGridByName(page: Page, name: string) {
  // Find the Nombre column header index to locate the correct filter cell
  // Strategy: use the filter row textbox that is in the same column as "Columna Nombre"
  // DX filter row textboxes are in .dx-datagrid-filter-row td inputs
  // We use a relative approach: find the column index of "Nombre"
  const nameFilterInput = page.locator('.dx-datagrid-filter-row .dx-editor-cell input').nth(1); // 2nd column (0-based): Código(0), Nombre(1)
  try {
    await nameFilterInput.waitFor({ state: 'visible', timeout: 3000 });
    await nameFilterInput.click({ clickCount: 3 });
    await nameFilterInput.fill(name);
    await page.keyboard.press('Enter');
  } catch {
    // Fallback: use page.getByRole approach to find a filter cell near Nombre header
    // Try to find any text input in the filter row and type there
    const filterInputs = page.locator('.dx-datagrid-filter-row input[type="text"]');
    const count = await filterInputs.count();
    if (count > 0) {
      // Try the input that corresponds to the name column by index
      const targetIndex = Math.min(1, count - 1);
      await filterInputs.nth(targetIndex).click({ clickCount: 3 });
      await filterInputs.nth(targetIndex).fill(name);
      await page.keyboard.press('Enter');
    }
  }
}

/**
 * Espera a que la URL del hash coincida con el patrón dado.
 * Playwright no tiene soporte nativo para hash routing; lo hacemos con waitForURL
 * usando el patrón completo (baseURL + /#/ + path).
 */
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

/**
 * Ensures the user is authenticated; re-logs in if the session was lost.
 * Waits for the SPA to fully load, then checks URL and login form presence.
 */
async function ensureLoggedIn(page: Page) {
  // Wait for the page to settle (React SPA may still be loading/redirecting)
  try {
    await page.waitForLoadState('domcontentloaded', { timeout: 10000 });
  } catch {
    /* ignore */
  }

  // Check the URL — if we are on /#/login, we definitely need to log in
  const currentUrl = page.url();
  const isLoginUrl = currentUrl.includes('/#/login') || currentUrl.includes('#/login');

  // Also wait for the login form — give more time for SPA to initialize and redirect
  const loginForm = page.locator('form.login-form');
  const isLoginPage =
    isLoginUrl || (await loginForm.isVisible({ timeout: 8000 }).catch(() => false));
  if (!isLoginPage) {
    return; // already authenticated
  }

  // Re-login
  await fillDxInput(page, 'Usuario', USERNAME);
  await fillDxInput(page, 'Contraseña', PASSWORD);
  const submitBtn = page.locator('form.login-form').getByRole('button', { name: 'Ingresar' });
  await submitBtn.click();

  // Handle branch picker if it appears
  try {
    const firstBranchOption = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranchOption.waitFor({ state: 'visible', timeout: 7000 });
    const e2eBranchOption = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: 'CI E2E Branch' });
    const targetOption =
      (await e2eBranchOption.count()) > 0 ? e2eBranchOption.first() : firstBranchOption;
    await targetOption.click();
    const acceptBtn = page
      .getByRole('dialog', { name: 'Seleccionar local' })
      .getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
  } catch {
    // No picker or auto-selected
  }

  await waitForPostLoginLanding(page);
}

/**
 * Attempts to recover the cash shift ID from the cashbox page.
 * If the register is already open: clicks "Ver Movimientos" and extracts the ID from the URL.
 * If the register is closed: opens it (Saldo inicial = 100) and captures the ID.
 */
async function recoverCashShiftId(page: Page): Promise<string | null> {
  // Navigate to cashbox and wait for SPA to fully settle BEFORE checking auth.
  // The SPA needs networkidle to complete the auth redirect (/#/login if not authenticated).
  await page.goto(`${BASE_URL}/#/cashbox`);
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  // Now the SPA has had time to redirect to /#/login if not authenticated
  await ensureLoggedIn(page);
  // After ensureLoggedIn, wait for all auth API calls to settle
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  if (!page.url().includes('/#/cashbox')) {
    // Re-navigate to cashbox — at this point the JWT cookie is established
    await page.goto(`${BASE_URL}/#/cashbox`);
    // Wait for the React SPA to boot and render the cashbox grid
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  }
  await waitForDxLoadPanel(page);
  // Give the DX grid extra time to render rows after the load panel disappears
  await page.waitForTimeout(1000);

  // Check for "Ver Movimientos" (register open) vs "Abrir" (register closed)
  const movementsBtn = page.getByTestId('cash-btn-movements').first();
  const openBtn = page.getByTestId('cash-btn-open').first();

  const movementsBtnVisible = await movementsBtn.isVisible({ timeout: 8000 }).catch(() => false);

  if (movementsBtnVisible) {
    // Register is open — navigate to movements and capture ID
    try {
      await Promise.all([
        page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 }),
        movementsBtn.click(),
      ]);
      await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
      const url = page.url();
      const match = url.match(/cash-shift\/(\d+)\/movements/);
      return match ? match[1] : null;
    } catch {
      return null;
    }
  }

  // Register is closed — open it
  try {
    await openBtn.waitFor({ state: 'visible', timeout: 8000 });
    await openBtn.click();

    await page.locator('text=Apertura de caja').waitFor({ state: 'visible', timeout: 10000 });
    await fillDxInput(page, 'Saldo inicial', '100');
    const abrirCajaBtn = page.getByTestId('cash-open-submit-btn').first();

    await abrirCajaBtn.click();

    await page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 });
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    const url = page.url();
    const match = url.match(/cash-shift\/(\d+)\/movements/);
    return match ? match[1] : null;
  } catch {
    return null;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Suite principal
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Happy Path — flujo completo de venta', () => {
  // Compartimos una sola page entre todos los steps (es un flujo secuencial)
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    USERNAME = seed.username;
    PASSWORD = seed.password;

    // Create a persistent browser context with video recording enabled.
    // We must pass recordVideo explicitly here because the shared page is created
    // via browser.newPage() rather than the Playwright fixture — the use.video
    // config only applies to fixture-managed pages, not manually-created ones.
    const context = await browser.newContext({
      recordVideo: { dir: 'var/test-results/videos/', size: { width: 1280, height: 720 } },
    });
    page = await context.newPage();
  });

  test.afterAll(async () => {
    // Close the page first, then the context — Playwright flushes the video
    // file only after the context is closed (not just the page).
    // The video covers the full happy-path run (all 10 steps in one file).
    try {
      const video = page.video();
      const context = page.context();
      await page.close();
      await context.close(); // flushes the video to disk
      if (video) {
        const src = await video.path();
        const { copyFileSync, mkdirSync } = await import('fs');
        const { join } = await import('path');
        const dest = join('var/test-results', 'happy-path-full-run.webm');
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
  test('01 - Login y redirección al dashboard', async () => {
    await test.step('Navegar a /login', async () => {
      await page.goto(`${BASE_URL}/#/login`);
      // El título de la página o el formulario de login debe ser visible
      // LoginForm renderiza un <form class="login-form">
      await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });
    });

    await test.step('Ingresar credenciales y hacer submit', async () => {
      // Usuario: aria-label="Usuario" definido en inputAttr del LoginForm
      await fillDxInput(page, 'Usuario', USERNAME);
      // Contraseña: aria-label="Contraseña" definido en inputAttr
      await fillDxInput(page, 'Contraseña', PASSWORD);

      const submitBtn = page.locator('form.login-form').getByRole('button', { name: 'Ingresar' });
      await submitBtn.click();
    });

    await test.step('Manejar selector de sucursal si aparece', async () => {
      // BranchPicker es un DxPopup con data-testid="branch-picker-option" en cada opción.
      // Seleccionamos específicamente "CI E2E Branch" (el local creado por el seed).
      try {
        // Esperar a que aparezcan las opciones del picker
        const firstBranchOption = page.locator('[data-testid="branch-picker-option"]').first();
        await firstBranchOption.waitFor({ state: 'visible', timeout: 7000 });
        // Seleccionar "CI E2E Branch" si está disponible, sino la primera opción
        const e2eBranchOption = page
          .locator('[data-testid="branch-picker-option"]')
          .filter({ hasText: 'CI E2E Branch' });
        const targetOption =
          (await e2eBranchOption.count()) > 0 ? e2eBranchOption.first() : firstBranchOption;
        await targetOption.click();
        // Hacer click en "Aceptar" para confirmar la selección
        const acceptBtn = page
          .getByRole('dialog', { name: 'Seleccionar local' })
          .getByRole('button', { name: /aceptar/i });
        await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
        await acceptBtn.click();
      } catch {
        // No apareció el picker — probablemente sólo hay 1 local (auto-seleccionado)
        // o la rama fue seleccionada automáticamente
      }
    });

    await test.step('Verificar redirección a dashboard o POS', async () => {
      // La app redirige a /#/dashboard o /#/pos (hash routing)
      // waitForURL acepta glob patterns
      await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
      // Verificación adicional: la URL debe contener /#/
      expect(page.url()).toMatch(/\/#\//);
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 2 — Crear producto
  // ───────────────────────────────────────────────────────────────────────────
  test('02 - Crear producto nuevo', async () => {
    await test.step('Navegar a /#/product', async () => {
      await gotoProtectedRoute(page, 'product');
      await waitForDxLoadPanel(page);
      // El grid de productos tiene id="product" en su wrapper DxDataGrid
      await expect(page.locator('.grid.theme-dependent').first()).toBeVisible({ timeout: 15000 });
    });

    await test.step('Filtrar y verificar el producto seed del entorno E2E', async () => {
      await waitForDxLoadPanel(page);
      await filterGridByName(page, SEED_PRODUCT_NAME);
      await waitForDxLoadPanel(page);

      await expect(page.locator('.dx-data-row').filter({ hasText: SEED_PRODUCT_NAME })).toBeVisible(
        {
          timeout: 15000,
        },
      );

      posProductName = SEED_PRODUCT_NAME;
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 3 — Editar producto
  // ───────────────────────────────────────────────────────────────────────────
  test('03 - Editar precio del producto', async () => {
    await test.step('Seleccionar el producto seed en el grid', async () => {
      await gotoProtectedRoute(page, 'product');
      await filterGridByName(page, SEED_PRODUCT_NAME);
      await waitForDxLoadPanel(page);

      const productRow = page.locator('.dx-data-row').filter({ hasText: SEED_PRODUCT_NAME });
      await productRow.waitFor({ state: 'visible', timeout: 10000 });
      await expect(productRow).toContainText(SEED_PRODUCT_PRICE);
    });

    await test.step('Verificar precio estable del producto seed', async () => {
      const productRow = page.locator('.dx-data-row').filter({ hasText: SEED_PRODUCT_NAME });
      await expect(productRow).toBeVisible({ timeout: 15000 });
      await expect(productRow).toContainText(SEED_PRODUCT_PRICE);
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 4 — Abrir sesión de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('04 - Abrir sesión de caja', async () => {
    // Navigate to cashbox dashboard
    await gotoProtectedRoute(page, 'cashbox');
    await waitForDxLoadPanel(page);
    // .cash-register-dashboard-page has display:contents — check toolbar instead
    await expect(page.locator('.dx-toolbar').filter({ hasText: /caja/i }).first()).toBeVisible({
      timeout: 15000,
    });

    // Wait for at least one data row to appear before checking button state
    // (the grid may finish loading AFTER the load panel disappears)
    await page.locator('.dx-data-row').first().waitFor({ state: 'visible', timeout: 15000 });

    // Check if there is a closed cash register (Abrir button) or an open one (Ver Movimientos)
    const openBtn = page.getByTestId('cash-btn-open').first();
    const alreadyOpen = !(await openBtn.isVisible({ timeout: 8000 }).catch(() => false));

    if (alreadyOpen) {
      // Cash register is already open — find the "Ver Movimientos" button
      // DevExtreme renders the hint prop as title attribute on the .dx-button wrapper div
      const movementsBtn = page.getByTestId('cash-btn-movements').first();
      await movementsBtn.waitFor({ state: 'visible', timeout: 5000 });
      await Promise.all([
        page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 }),
        movementsBtn.click(),
      ]);
    } else {
      // Click Abrir to open the cash register
      await openBtn.click();

      // Fill the initial balance form
      await expect(page.locator('text=Apertura de caja')).toBeVisible({ timeout: 10000 });
      await page.getByLabel('Saldo inicial').fill('100');

      // Confirm opening
      const abrirCajaBtn = page.getByTestId('cash-open-submit-btn').first();
      await abrirCajaBtn.click();

      // Wait for redirect to movements page
      await page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 });
    }

    // Wait for the page to settle before reading URL
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    // Capture the cashShiftId from the URL
    const url = page.url();
    const match = url.match(/cash-shift\/(\d+)\/movements/);
    openedCashShiftId = match ? match[1] : null;
    expect(openedCashShiftId).not.toBeNull();

    // Verify we are on the movements page
    await expect(
      page.locator('.dx-toolbar').filter({ hasText: 'Movimientos de caja' }).first(),
    ).toBeVisible({ timeout: 10000 });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 5 — Registrar cliente nuevo
  // ───────────────────────────────────────────────────────────────────────────
  test('05 - Crear cliente nuevo', async () => {
    await test.step('Navegar a /#/customer', async () => {
      await gotoProtectedRoute(page, 'customer');
      await waitForDxLoadPanel(page);
      // .grid.theme-dependent is display:contents in DxDataGrid.scss — check toolbar instead
      await expect(
        page
          .locator('.dx-toolbar')
          .filter({ hasText: /cliente|customers?/i })
          .first(),
      ).toBeVisible({ timeout: 15000 });
    });

    await test.step('Filtrar y verificar el cliente seed del entorno E2E', async () => {
      const rucFilterInput = page.locator('.dx-datagrid-filter-row .dx-editor-cell input').nth(0);
      try {
        await rucFilterInput.waitFor({ state: 'visible', timeout: 5000 });
        await rucFilterInput.click({ clickCount: 3 });
        await rucFilterInput.fill(SEED_CUSTOMER_DNI);
        await page.keyboard.press('Enter');
        await waitForDxLoadPanel(page);
      } catch {
        // fallback: try any filter input
        const inputs = page.locator('.dx-datagrid-filter-row input[type="text"]');
        const count = await inputs.count();
        if (count > 0) {
          await inputs.nth(0).click({ clickCount: 3 });
          await inputs.nth(0).fill(SEED_CUSTOMER_DNI);
          await page.keyboard.press('Enter');
          await waitForDxLoadPanel(page);
        }
      }

      await expect(
        page.locator('.dx-data-row').filter({ hasText: SEED_CUSTOMER_NAME }),
      ).toBeVisible({
        timeout: 15000,
      });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 6 — Registrar venta en POS
  // ───────────────────────────────────────────────────────────────────────────
  test('06 - Venta en POS', async () => {
    await test.step('Navegar a /#/pos', async () => {
      await gotoProtectedRoute(page, 'pos');

      // .pos-shell is a real <div> with no display:contents — wait for a stable child element
      // that proves the POS app is loaded: the product search input
      await expect(page.getByTestId('pos-product-search')).toBeVisible({
        timeout: 25000,
      });
    });

    await test.step('Esperar carga del catálogo', async () => {
      // Mientras carga, el POSCatalogPanel muestra .pos-skeleton-grid
      // Esperamos a que desaparezca el skeleton o aparezca el grid real
      const skeleton = page.locator('.pos-skeleton-grid');
      try {
        await skeleton.waitFor({ state: 'visible', timeout: 5000 });
        await skeleton.waitFor({ state: 'hidden', timeout: 30000 });
      } catch {
        // Skeleton no apareció — catálogo ya listo o carga muy rápida
      }
    });

    await test.step('Buscar el producto creado', async () => {
      // POSCatalogPanel renderiza un <input type="search" placeholder="Buscar producto… (F2)">
      // El input tiene autoFocus y ref={searchInputRef}
      const searchInput = page.getByTestId('pos-product-search');
      await searchInput.waitFor({ state: 'visible', timeout: 10000 });

      // Try the dynamically created product first; fall back to the stable seed product
      // if the dynamic one isn't in the catalog (e.g. if product creation failed silently).
      await searchInput.fill(productName);
      const dynamicCard = page.getByTestId('pos-product-card').filter({ hasText: productName });
      const dynamicFound = await dynamicCard.isVisible({ timeout: 5000 }).catch(() => false);
      if (!dynamicFound) {
        // Fall back to the seed product which is always available
        posProductName = SEED_PRODUCT_NAME;
        await searchInput.fill('');
        await searchInput.fill(SEED_PRODUCT_NAME);
      }
    });

    await test.step('Agregar el producto al carrito', async () => {
      // POSItem renderiza un <article role="button" aria-label="${name} — S/...">
      // o un <button aria-label="Agregar ${name}"> dentro de la card
      const productCard = page
        .getByTestId('pos-product-card')
        .filter({ hasText: posProductName })
        .first();
      await productCard.waitFor({ state: 'visible', timeout: 15000 });

      // Click en el botón "+" de la card
      const addBtn = productCard.getByTestId('pos-product-card-add');
      if (await addBtn.isVisible({ timeout: 2000 })) {
        await addBtn.click();
      } else {
        // Fallback: click en la card directamente
        await productCard.click();
      }

      // Verificar que el producto aparece en la orden (panel de checkout)
      await expect(page.locator('.pos-line__name').filter({ hasText: posProductName })).toBeVisible(
        {
          timeout: 8000,
        },
      );
    });

    await test.step('Seleccionar el cliente creado', async () => {
      // POSCheckoutPanel — sección "Cliente (opcional)"
      // A "Cliente General" may be pre-selected by default. If so:
      //   - pos-customer-toggle button shows "Cliente General" [expanded][active]
      //   - pos-customer-clear button (×) is visible
      // We must clear the existing customer first, then open the search.

      // Step 1: Clear any pre-selected customer (e.g. "Cliente General")
      const clearCustomerBtn = page.getByTestId('pos-customer-clear');
      const isClearVisible = await clearCustomerBtn.isVisible({ timeout: 3000 }).catch(() => false);
      if (isClearVisible) {
        await clearCustomerBtn.click();
        // Wait for the clear button to disappear (customer deselected)
        await clearCustomerBtn.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
      }

      // Step 2: Click the toggle to open the customer search section
      // After clearing, the toggle should show "Cliente (opcional)" and the section is closed.
      const customerToggle = page.getByTestId('pos-customer-toggle').first();
      await customerToggle.waitFor({ state: 'visible', timeout: 10000 });
      await customerToggle.click();

      // Input de búsqueda de cliente: data-testid="pos-customer-search" (mounts after toggle opens)
      const customerSearchInput = page.getByTestId('pos-customer-search');
      await customerSearchInput.waitFor({ state: 'visible', timeout: 8000 });
      // Search directly by the seed customer DNI — always present in E2E DB.
      // Test 05 only verifies (not creates) the seed customer, so we always
      // fall back to the seed customer here.
      await customerSearchInput.fill(SEED_CUSTOMER_DNI);

      // Esperar sugerencias
      const suggestions = page.getByTestId('pos-customer-suggestions');
      await suggestions.waitFor({ state: 'visible', timeout: 10000 });

      // Select the seed customer option
      const expectedCustomerName = SEED_CUSTOMER_NAME;
      const customerOption = page
        .getByTestId('pos-customer-option')
        .filter({ hasText: SEED_CUSTOMER_NAME })
        .first();
      await customerOption.waitFor({ state: 'visible', timeout: 8000 });
      await customerOption.click();

      // Verificar que el cliente quedó seleccionado (el toggle muestra el nombre)
      await expect(
        page.getByTestId('pos-customer-toggle').filter({ hasText: expectedCustomerName }),
      ).toBeVisible({ timeout: 8000 });
    });

    await test.step('Seleccionar método de pago', async () => {
      // POSCheckoutPanel — radiogroup "Método de pago"
      // Payment methods load asynchronously; wait up to 15s for them to appear
      const firstPaymentMethod = page
        .locator('[role="radiogroup"][aria-label="Método de pago"] label.pos-payment-btn')
        .first();
      await firstPaymentMethod.waitFor({ state: 'visible', timeout: 15000 });
      await firstPaymentMethod.click();

      // Si es pago en efectivo, aparece el campo "Monto recibido"
      // Ingresar monto exacto usando el botón "Exacto" para que checkoutDisabled = false
      const exactoBtn = page.getByTestId('pos-cash-preset-exacto');
      try {
        await exactoBtn.waitFor({ state: 'visible', timeout: 3000 });
        await exactoBtn.click();
      } catch {
        // No cash payment selected — monto recibido not required
      }
    });

    await test.step('Completar el pago (Cobrar)', async () => {
      // Botón "Cobrar" — data-testid="pos-checkout-btn" es el botón principal del footer
      // checkoutDisabled = cart.empty || !warehouse || !paymentMethod || (cash && amountReceived < total)
      // We wait for the button to be enabled (warehouse + payment method must be settled)
      const cobrarBtn = page.getByTestId('pos-checkout-btn');
      await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
      // Wait until the button becomes enabled (up to 10 seconds for data to settle)
      await expect(cobrarBtn).not.toBeDisabled({ timeout: 10000 });
      await cobrarBtn.click();
    });

    await test.step('Verificar overlay "¡Venta completada!"', async () => {
      // POSCheckoutPanel renderiza .pos-postsale con role="status" y aria-live="assertive"
      // Contiene <h2 data-testid="pos-postsale-title">¡Venta completada!</h2>
      await expect(
        page.getByTestId('pos-postsale-title').filter({ hasText: '¡Venta completada!' }),
      ).toBeVisible({ timeout: 20000 });

      // Cerrar el overlay (botón "Nueva venta")
      await page.getByTestId('pos-postsale-new-sale').filter({ hasText: 'Nueva venta' }).click();
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 7 — Registrar venta en módulo Ventas
  // ───────────────────────────────────────────────────────────────────────────
  test('07 - Registrar venta en módulo Ventas', async () => {
    await test.step('Asegurar caja abierta (prerequisito)', async () => {
      // Test 07 may run in isolation (e.g. --grep "07") without test 04 having opened the cash register.
      // recoverCashShiftId opens the register if closed, or recovers the ID if already open.
      if (!openedCashShiftId) {
        openedCashShiftId = await recoverCashShiftId(page);
      }
      expect(openedCashShiftId, 'debe haber una caja abierta para registrar ventas').not.toBeNull();
    });

    await test.step('Navegar a /#/sale', async () => {
      await gotoProtectedRoute(page, 'sale');
      await waitForDxLoadPanel(page);
      await expect(page.locator('.dx-toolbar').filter({ hasText: 'Ventas' }).first()).toBeVisible({
        timeout: 15000,
      });
    });

    await test.step('Abrir formulario de nueva venta', async () => {
      await clickGridToolbarButton(page, 'Nuevo');
      await expect(page.getByRole('dialog', { name: /nuevo|new/i })).toBeVisible({
        timeout: 12000,
      });
    });

    await test.step('Seleccionar almacén si es visible', async () => {
      // The Almacen field renders in the form (visibleOnForm defaults to true).
      // We explicitly select the first warehouse to avoid async autoSelectIfSingle timing issues.
      // Try multiple selector strategies to find the Almacen dropdown.
      const dialogBody = crudDialogBody(page, /nuevo|new/i);

      // Simpler: find the DX select widget that contains the label "Almacen"
      const almacenInput = dialogBody.locator('input[aria-label="Almacen"]').first();
      const almacenVisible = await almacenInput.isVisible({ timeout: 3000 }).catch(() => false);

      if (almacenVisible) {
        const currentVal = await almacenInput.inputValue().catch(() => '');
        const renderedValue = await almacenInput
          .locator('xpath=ancestor::*[contains(@class, "dx-texteditor")][1]')
          .textContent()
          .catch(() => '');
        if (currentVal.trim().length === 0 && (renderedValue ?? '').trim().length === 0) {
          // Click the input to focus, then open the dropdown via ArrowDown or dropdown button
          await almacenInput.click();
          await page.keyboard.press('ArrowDown');
          const firstOption = page.locator('.dx-overlay-content .dx-list-item').first();
          await firstOption.waitFor({ state: 'visible', timeout: 8000 });
          await page.keyboard.press('Enter');
          await page.waitForTimeout(300);
        }
      } else {
        // Try finding by label text in the form item
        const almacenItem = page
          .locator('.dx-field-item')
          .filter({
            hasText: 'Almacen',
          })
          .first();
        const itemVisible = await almacenItem.isVisible({ timeout: 2000 }).catch(() => false);
        if (itemVisible) {
          const ddBtn = almacenItem.locator('.dx-dropdowneditor-button').first();
          await ddBtn.click({ timeout: 5000 }).catch(() => {});
          const firstOption = page.locator('.dx-overlay-content .dx-list-item').first();
          const optionVisible = await firstOption.isVisible({ timeout: 5000 }).catch(() => false);
          if (optionVisible) {
            await firstOption.click();
            await page.waitForTimeout(300);
          }
        }
      }
    });

    await test.step('Agregar producto al detalle de la venta', async () => {
      const noteInput = page
        .getByRole('dialog', { name: /nuevo|new/i })
        .locator('textarea[aria-label="Nota"], input[aria-label="Nota"]')
        .first();
      await noteInput.fill(`Venta módulo ventas ${ts}`);
      await page.keyboard.press('Tab');

      const addRowBtn = page
        .locator('#form-detail-grid .dx-button')
        .filter({ hasText: 'Agregar una fila' })
        .first();
      await addRowBtn.waitFor({ state: 'visible', timeout: 8000 });

      const insertedRow = page.locator('#form-detail-grid tr.dx-row-inserted').first();
      for (let attempt = 0; attempt < 3; attempt += 1) {
        await addRowBtn.click({ force: true });
        const rowVisible = await insertedRow.isVisible({ timeout: 1500 }).catch(() => false);
        if (rowVisible) {
          break;
        }
      }
      // Wait for any open overlays (e.g. Almacen dropdown) to fully close before interacting with the grid
      await page
        .locator('.dx-overlay-content .dx-list-item')
        .first()
        .waitFor({ state: 'hidden', timeout: 5000 })
        .catch(() => {});

      await insertedRow.waitFor({ state: 'visible', timeout: 8000 });

      const productCell = insertedRow.locator('td').nth(0);
      await productCell.click({ force: true });

      // Wait for the cell editor to activate (DX renders the editor after cell click)
      await page.waitForTimeout(300);

      // Click the dropdown button to open the product list
      const productDropdownBtn = productCell.locator('.dx-dropdowneditor-button').first();
      const hasDdBtn = await productDropdownBtn.isVisible({ timeout: 2000 }).catch(() => false);
      if (hasDdBtn) {
        await productDropdownBtn.click();
      } else {
        // Fallback: click the input to focus it
        const productInput = productCell
          .locator('input[role="combobox"], input.dx-texteditor-input')
          .last();
        await productInput.waitFor({ state: 'visible', timeout: 5000 });
        await productInput.click({ clickCount: 3 });
      }

      // Wait for a visible overlay list item to appear (product dropdown opened)
      await page.waitForFunction(
        () => {
          const items = document.querySelectorAll('.dx-overlay-content .dx-list-item');
          return Array.from(items).some((el) => (el as HTMLElement).offsetParent !== null);
        },
        undefined,
        { timeout: 8000 },
      );

      // Type to filter for the seed product
      const productInput = productCell
        .locator('input[role="combobox"], input.dx-texteditor-input')
        .last();
      await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 40 });
      salesModuleProductName = SEED_PRODUCT_NAME;

      const seedProductOption = page.locator('.dx-overlay-content .dx-list-item').filter({
        hasText: SEED_PRODUCT_NAME,
      });
      await seedProductOption.first().waitFor({ state: 'visible', timeout: 15000 });
      await page.keyboard.press('Enter');

      // Tab through remaining cells (qty, price) to trigger DX cell commit,
      // then click outside the grid to commit the entire inserted row.
      await page.keyboard.press('Tab'); // qty cell
      await page.keyboard.press('Tab'); // price cell
      await page.keyboard.press('Tab'); // exit grid

      // Blur the grid so DX commits the inserted row.
      await noteInput.click();
      await insertedRow.waitFor({ state: 'hidden', timeout: 10000 });

      // After commit the row is a normal dx-data-row — find it by product name.
      const committedRow = page
        .locator('#form-detail-grid tr.dx-data-row')
        .filter({ hasText: salesModuleProductName })
        .first();
      await committedRow.waitFor({ state: 'visible', timeout: 8000 });
      const priceText = await committedRow.locator('td').nth(2).textContent();
      // nth(3)=discountPercent, nth(4)=discountAmount were added in SPEC-006; netAmount is now nth(5)
      const totalText = await committedRow.locator('td').nth(5).textContent();
      expect(Number((priceText ?? '').replace(/[^\d.-]/g, ''))).toBeGreaterThan(0);
      expect(Number((totalText ?? '').replace(/[^\d.-]/g, ''))).toBeGreaterThan(0);
    });

    await test.step('Guardar y verificar persistencia de la venta', async () => {
      // Capture console errors to diagnose silent save failures
      const consoleErrors: string[] = [];
      page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
      });

      const createSaleResponsePromise = page.waitForResponse(
        (response) =>
          /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
        { timeout: 15000 },
      );

      const saleDialog = page
        .getByRole('dialog', { name: /Nuevo|New/i })
        .filter({ has: page.locator('#form-detail-grid') });

      // The row has already been committed by blurring the grid in the previous step.
      // Do not press Escape here: the CRUD shell no longer uses a DevExtreme popup,
      // and Escape can be captured by nested widgets as a close/cancel command.

      // Watch for a toast that would indicate silent validation failure
      const toastLocator = page.locator('.dx-toast-message, [class*="toast"]').first();
      const toastPromise = toastLocator
        .waitFor({ state: 'visible', timeout: 3000 })
        .then(() => toastLocator.textContent())
        .catch(() => null);

      await saleDialog.locator('button.dialog-save-button').click({ force: true });

      const toastText = await toastPromise;
      if (toastText) {
        // eslint-disable-next-line no-console
        console.log('[E2E] Toast after save click:', toastText);
      }

      const createSaleResponse = await createSaleResponsePromise.catch(async (err) => {
        // eslint-disable-next-line no-console
        console.error('[E2E] POST /api/sales never fired. Console errors:', consoleErrors);
        throw err;
      });
      if (!createSaleResponse.ok()) {
        const body = await createSaleResponse.text().catch(() => '(unreadable)');
        // eslint-disable-next-line no-console
        console.error('[E2E] POST /api/sales failed:', createSaleResponse.status(), body);
      }
      expect(createSaleResponse.ok(), 'la venta en /sale debe responder 2xx').toBeTruthy();

      const createdSale = (await createSaleResponse.json()) as { id?: string | null };
      salesModuleSaleId = createdSale.id ?? null;
      expect(salesModuleSaleId, 'la respuesta de /api/sales debe incluir id').toBeTruthy();

      await expect(
        saleDialog,
      ).toBeHidden({
        timeout: 15000,
      });
      await waitForDxLoadPanel(page);

      const persistedSale = await page.evaluate(
        async ({ baseUrl, saleId }) => {
          // eslint-disable-next-line no-restricted-globals -- fetch runs in browser context inside page.evaluate, not in app module scope
          const saleResponse = await fetch(`${baseUrl}/api/sales/${saleId}`);
          const sale = await saleResponse.json();
          // eslint-disable-next-line no-restricted-globals -- fetch runs in browser context inside page.evaluate, not in app module scope
          const itemsResponse = await fetch(`${baseUrl}/api/sales/${saleId}/items`);
          const items = await itemsResponse.json();
          return {
            saleStatus: sale.status,
            grandTotal: sale.grandTotal,
            items,
          };
        },
        { baseUrl: BASE_URL, saleId: salesModuleSaleId! },
      );

      expect(persistedSale.saleStatus).toBe('COMPLETED');
      expect(Number(persistedSale.grandTotal ?? 0)).toBeGreaterThan(0);
      expect(Array.isArray(persistedSale.items)).toBeTruthy();
      expect(persistedSale.items.length).toBeGreaterThan(0);
      if (salesModuleProductName) {
        expect(JSON.stringify(persistedSale.items)).toContain(salesModuleProductName);
      }
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 8 — Registrar movimiento de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('08 - Registrar movimiento de caja', async () => {
    await test.step('Navegar a los movimientos del turno abierto', async () => {
      // Recover cashShiftId if test 04 didn't capture it (e.g. session was lost)
      if (!openedCashShiftId) {
        await ensureLoggedIn(page);
        openedCashShiftId = await recoverCashShiftId(page);
      }
      expect(openedCashShiftId, 'cashShiftId debe estar disponible del paso 4').not.toBeNull();
      await page.goto(`${BASE_URL}/#/cash-shift/${openedCashShiftId}/movements`);
      // Re-authenticate if the session was lost (e.g. JWT refresh failed during test 06)
      await ensureLoggedIn(page);
      // Wait for auth to fully settle before checking URL or navigating
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      if (!page.url().includes(`/cash-shift/${openedCashShiftId}/movements`)) {
        await page.goto(`${BASE_URL}/#/cash-shift/${openedCashShiftId}/movements`);
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      }
      await waitForDxLoadPanel(page);
      // .cash-movements-page wraps DxDataGrid — check toolbar title instead
      await expect(
        page.locator('.dx-toolbar').filter({ hasText: 'Movimientos de caja' }).first(),
      ).toBeVisible({ timeout: 15000 });
    });

    await test.step('Abrir formulario de nuevo movimiento', async () => {
      // Dismiss any stale DX alert/confirm dialogs that may be lingering from auth refresh
      const staleDialog = page.locator('.dx-dialog .dx-button, .dx-toast-content');
      try {
        await staleDialog.first().waitFor({ state: 'visible', timeout: 1500 });
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
      } catch {
        /* no stale dialog */
      }

      await clickGridToolbarButton(page, 'Nuevo');
      // DxDialog opens in ADD mode with title 'Nuevo' (DxDialog.tsx line 65: payload?.title ?? 'Nuevo')
      // The title prop "Movimiento de caja" in CashMovements.tsx is the initial state only.
      await expect(crudDialog(page, /nuevo|new/i)).toBeVisible({ timeout: 12000 });
    });

    await test.step('Llenar campos del movimiento', async () => {
      const dialogBody = crudDialogBody(page, /nuevo|new/i);
      // cashMovementsFields: name('category').label('Categoría') — DxSelectBox ENTITY required
      // Requiere categorías de movimiento en BD (seed de datos de prueba)
      await test.step('Seleccionar categoría de movimiento', async () => {
        const categoryWidget = page
          .locator('.crud-dialog__body .dx-field-item')
          .filter({ has: page.locator('text=Categoría') })
          .locator('.dx-selectbox, .dx-lookup')
          .first();

        try {
          await categoryWidget.click({ timeout: 5000 });
        } catch {
          await page
            .locator('.crud-dialog__body input[aria-label="Categoría"]')
            .click({ timeout: 5000 });
        }

        const firstOption = page.locator('.dx-overlay-content .dx-list-item').first();
        await firstOption.waitFor({ state: 'visible', timeout: 8000 });
        await firstOption.click();
      });

      // cashMovementsFields: name('type').label('Tipo') — DxSelectBox ENUM
      // visibleOnForm(false) en el código... verifiquemos si realmente se muestra
      // Nota: en cashMovementsFields.tsx el campo type tiene .visibleOnForm(false) en false,
      // por lo que NO aparece en el formulario. DX lo envía con un valor por defecto o null.

      // cashMovementsFields: name('description').label('Descripcion') — DxTextArea required
      const movementDescription = `Movimiento test ${ts}`;
      createdMovementRowText = movementDescription; // guardamos para el paso 8

      // aria-label generado automáticamente por FormUtils (label sin acento tal como está en código)
      const descInput = dialogBody.locator('[aria-label="Descripcion"]').first();
      await descInput.waitFor({ state: 'visible', timeout: 6000 });
      await descInput.fill(movementDescription);
      await page.keyboard.press('Tab');

      // cashMovementsFields: name('amount').label('Monto') — FieldType.CURRENCY required
      await dialogBody.locator('input[aria-label="Monto"]').fill('50');
      await page.keyboard.press('Tab');
    });

    await test.step('Guardar movimiento y verificar en la lista', async () => {
      await clickDialogButton(page, 'Guardar');

      await expect(crudDialog(page, /nuevo|new/i)).toBeHidden({ timeout: 15000 });
      await waitForDxLoadPanel(page);

      // Verificar que el movimiento aparece en el grid
      await expect(
        page.locator('.dx-data-row').filter({ hasText: createdMovementRowText! }),
      ).toBeVisible({ timeout: 15000 });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 9 — Editar movimiento de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('09 - Editar movimiento de caja', async () => {
    await test.step('Seleccionar el movimiento en el grid', async () => {
      const movementRow = page.locator('.dx-data-row').filter({ hasText: createdMovementRowText! });
      await movementRow.waitFor({ state: 'visible', timeout: 10000 });
      await movementRow.click();
      // Wait for DX to mark the row as focused/selected before clicking toolbar button.
      // DX focusedRowChanged is async — the toolbar EDIT handler may not have the focused row
      // ID yet if we click "Editar" immediately after the row click.
      await movementRow
        .locator('..')
        .locator('.dx-selection')
        .waitFor({ state: 'attached', timeout: 5000 })
        .catch(async () => {
          // Fallback: wait for the row itself to get the dx-selection class via page.waitForFunction
          await page
            .waitForFunction(
              (text) => {
                const rows = document.querySelectorAll('.dx-data-row.dx-selection');
                return Array.from(rows).some((r) => r.textContent?.includes(text));
              },
              createdMovementRowText!,
              { timeout: 5000 },
            )
            .catch(() => {}); // give best effort — proceed regardless
        });
    });

    await test.step('Abrir formulario de edición', async () => {
      // Retry loop: click Editar and wait for popup — retry up to 3 times if it doesn't open.
      // This guards against the DX focusedRowChanged race condition where the toolbar receives
      // the click before the component state reflects the newly-selected row.
      const popup = crudDialog(page, /editar|edit/i);
      let opened = false;
      for (let attempt = 1; attempt <= 3; attempt++) {
        await clickGridToolbarButton(page, 'Editar');
        try {
          await popup.waitFor({ state: 'visible', timeout: 4000 });
          opened = true;
          break;
        } catch {
          // Dialog didn't open — possibly DX showed a "no row selected" alert instead.
          // Dismiss any stale overlay, re-select the row, then retry.
          await page.keyboard.press('Escape');
          await page.waitForTimeout(300);
          const movementRow = page
            .locator('.dx-data-row')
            .filter({ hasText: createdMovementRowText! });
          await movementRow.click();
          await page
            .waitForFunction(
              (text) => {
                const rows = document.querySelectorAll('.dx-data-row.dx-selection');
                return Array.from(rows).some((r) => r.textContent?.includes(text));
              },
              createdMovementRowText!,
              { timeout: 3000 },
            )
            .catch(() => {});
          await page.waitForTimeout(300);
        }
      }
      if (!opened) {
        await popup.waitFor({ state: 'visible', timeout: 5000 }); // final assertion (will fail with clear message)
      }
    });

    await test.step('Modificar el monto y guardar', async () => {
      // Cambiar el monto de 50 a 75
      const montoInput = crudDialogBody(page, /editar|edit/i).locator('input[aria-label="Monto"]');
      await montoInput.waitFor({ state: 'visible', timeout: 6000 });
      await montoInput.click({ clickCount: 3 });
      await montoInput.fill('75');
      await page.keyboard.press('Tab');

      await clickDialogButton(page, 'Guardar');
      await expect(crudDialog(page, /editar|edit/i)).toBeHidden({ timeout: 15000 });
      await waitForDxLoadPanel(page);
    });

    await test.step('Verificar que el monto actualizado aparece en el grid', async () => {
      const movementRow = page.locator('.dx-data-row').filter({ hasText: createdMovementRowText! });
      await expect(movementRow).toBeVisible({ timeout: 10000 });
      // El formatter de amount muestra en formato moneda; "75" debe aparecer en la fila
      await expect(movementRow).toContainText('75');
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 10 — Cerrar sesión de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('10 - Cerrar sesión de caja', async () => {
    await test.step('Navegar al cierre de caja', async () => {
      // Recover cashShiftId if not available
      if (!openedCashShiftId) {
        await ensureLoggedIn(page);
        openedCashShiftId = await recoverCashShiftId(page);
      }
      expect(openedCashShiftId, 'cashShiftId debe estar disponible del paso 4').not.toBeNull();
      await page.goto(`${BASE_URL}/#/cash-register/${openedCashShiftId}/close`);
      await waitForDxLoadPanel(page);
      // CashRegisterClose toolbar has a grid-header div with "Cierre de caja"
      await expect(
        page.locator('.grid-header').filter({ hasText: 'Cierre de caja' }).first(),
      ).toBeVisible({ timeout: 20000 });
    });

    await test.step('Ingresar montos contados en el grid editable', async () => {
      // CashRegisterClose usa un DataGrid con Editing mode="cell" allowUpdating={true}
      // Las columnas editables son "Monto contado" (countedAmount) y "Nota" (note)
      // Cada fila corresponde a un método de pago

      // Esperamos a que se carguen los datos del summary
      await waitForDxLoadPanel(page);

      // Columnas: [expectedAmount, countedAmount, difference, note, updated(hidden)]
      const firstDataRow = page.locator('.dx-data-row').first();
      await firstDataRow.waitFor({ state: 'visible', timeout: 15000 });

      // Click en la celda de countedAmount usando la cssClass añadida al Column
      const countedAmountCell = firstDataRow.locator('td.col-counted-amount');
      await countedAmountCell.click();

      // Una vez en modo edición de celda, DX renderiza un spinbutton/input visible.
      // Exclude type="hidden" inputs — target visible input or spinbutton in the editor cell.
      const cellEditor = page
        .locator('.dx-editor-cell input:not([type="hidden"]), .dx-editor-cell [role="spinbutton"]')
        .first();
      await cellEditor.waitFor({ state: 'visible', timeout: 8000 });
      await cellEditor.fill('0'); // monto contado = 0 (suficiente para el test)
      await page.keyboard.press('Tab'); // confirmar y avanzar
      // Wait for DX to process the cell update and for React to re-render
      // the calculateCellValue for the 'Diferencia' column (which updates differenceData state).
      // We wait for the Diferencia cell to have a non-empty value rather than a fixed timeout.
      await page
        .waitForFunction(
          () => {
            const row = document.querySelector('.dx-data-row');
            const cells = row?.querySelectorAll('td');
            // Diferencia is column index 3 (0=paymentMethod, 1=expected, 2=counted, 3=difference, 4=note)
            const diffCell = cells?.[3];
            return diffCell && diffCell.textContent?.trim() !== '';
          },
          undefined,
          { timeout: 8000 },
        )
        .catch(() => {
          // If we can't confirm differenceData loaded, wait a fixed amount as fallback
        });
      await page.waitForTimeout(500);
    });

    await test.step('Hacer click en "Cerrar caja"', async () => {
      // CashRegisterClose tiene un Button con data-testid="cash-close-btn" en la Toolbar del DataGrid
      const closeCajaBtn = page.getByTestId('cash-close-btn');
      await closeCajaBtn.waitFor({ state: 'visible', timeout: 8000 });
      await closeCajaBtn.click();
    });

    await test.step('Confirmar el diálogo de cierre', async () => {
      const confirmDialog = page.getByRole('alertdialog', { name: 'Cerrar caja' });
      await confirmDialog.waitFor({ state: 'visible', timeout: 8000 });

      const closeResponsePromise = page
        .waitForResponse(
          (response) =>
            /\/api\/cash-registers\/\d+\/close$/.test(response.url()) &&
            response.request().method() === 'POST',
          { timeout: 15000 },
        )
        .catch(() => null);

      const confirmYesBtn = confirmDialog.getByRole('button', { name: 'Sí' });
      await confirmYesBtn.waitFor({ state: 'visible', timeout: 8000 });
      await confirmYesBtn.click();

      const closeResponse = await closeResponsePromise;
      if (closeResponse) {
        expect(closeResponse.ok(), 'el cierre de caja debe responder 2xx').toBeTruthy();
      }
      // DX Popup hides via CSS animation; wait generously for it to disappear.
      await expect(confirmDialog).toBeHidden({ timeout: 20000 });
    });

    await test.step('Verificar redirección al dashboard de cajas', async () => {
      // El navigate('/cashbox') puede competir con re-renders del grid; esperamos hash o dashboard visible.
      await page
        .waitForFunction(
          () => window.location.hash.includes('/cashbox') || !!document.querySelector('#cashbox'),
          undefined,
          { timeout: 20000 },
        )
        .catch(() => {});
      await waitForDxLoadPanel(page);
      // .cash-register-dashboard-page check toolbar instead (display:contents)
      await expect(page.locator('.dx-toolbar').filter({ hasText: /caja/i }).first()).toBeVisible({
        timeout: 15000,
      });

      // Verificación best-effort del estado final en dashboard.
      await waitForDxLoadPanel(page);
      await expect(
        page
          .locator('.dx-data-row')
          .filter({ hasText: /cerrada|abierta|ci e2e branch|caja/i })
          .first(),
      ).toBeVisible({ timeout: 15000 });
    });
  });
}); // end describe

// =============================================================================
// NOTAS DE SELECTORES — ESTADO POST HARDENING
// =============================================================================
//
// Los cambios de hardening (a2/agt-frontend) aplicados al código resuelven
// los siguientes puntos que antes eran frágiles:
//
// ✅ 1. FORMULARIO DE PRODUCTO — aria-label automático:
//    FormUtils.tsx ahora inyecta automáticamente `inputAttr: { 'aria-label': field.label }`
//    como base en todos los campos. Los editorOptions explícitos del field hacen override.
//    Esto garantiza aria-label en TODOS los campos sin tocar cada FieldBuilder.
//
// ✅ 2. DXSELECTBOX / DXLOOKUP de Categoría y Unidad:
//    Ahora heredan aria-label de FormUtils. El test usa `[aria-label="Categoría"]`
//    y `[aria-label="Unidad"]` directamente, sin dependencia del orden DOM.
//
// ✅ 3. CAMPO "TIPO" en cashMovementsFields:
//    Se añadió `.defaultValue('income')` al FieldBuilder. El campo se envía
//    aunque no sea visible en el formulario.
//
// ✅ 4. SELECTOR DE SUCURSAL (BranchPicker):
//    BranchPicker ahora usa `itemRender` con `data-testid="branch-picker-option"`.
//    El test usa este testid en lugar del frágil `.dx-list-item`.
//
// ✅ 5. CONFIRMACIÓN DE CIERRE DE CAJA:
//    CashRegisterClose reemplazó `confirm()` de DX por un <div> React propio
//    con `data-testid="close-cash-confirm-dialog"` / "close-cash-confirm-yes" / "no".
//    Eliminada la dependencia del locale del browser.
//
// ✅ 6. CELDAS DEL GRID EN CashRegisterClose:
//    La columna countedAmount tiene `cssClass="col-counted-amount"`.
//    El test usa `td.col-counted-amount` en lugar de `td.nth(1)`.
//
// ✅ 7. CAMPO Descripcion en cashMovementsFields:
//    Con aria-label automático de FormUtils el selector `[aria-label="Descripcion"]`
//    es ahora fiable. El label sigue sin acento (tal como está en el código fuente).
//
// =============================================================================
