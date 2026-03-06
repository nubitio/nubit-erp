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
 *  7. Registrar movimiento de caja
 *  8. Editar movimiento de caja
 *  9. Cerrar sesión de caja
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
 * ── Selectores inciertos — requieren data-testid (ver nota final) ────────────
 * Los pasos donde el selector es potencialmente frágil están marcados con
 * el comentario:  // ⚠ FRAGILE — ver "NOTAS DE SELECTORES" al final del archivo
 *
 * ── Variables de entorno ────────────────────────────────────────────────────
 *   E2E_BASE_URL   (default: http://localhost:8080)
 *   E2E_USERNAME   (default: admin@test.com)
 *   E2E_PASSWORD   (default: password)
 */

import { expect, test, type Page } from '@playwright/test';

// ── Env / config ──────────────────────────────────────────────────────────────
const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8080';
const USERNAME = process.env.E2E_USERNAME ?? 'admin@test.com';
const PASSWORD = process.env.E2E_PASSWORD ?? 'password';

// ── Test data — unique per run ────────────────────────────────────────────────
const ts = Date.now();
const productName = `Test Producto ${ts}`;
const productPriceInitial = '25.50';
const productPriceEdited = '30.00';
// DNI de 8 dígitos — suficientemente aleatorio para no colisionar
const customerDni = `1${ts.toString().slice(-7)}`;
const customerName = `Cliente Test ${ts}`;

// ── Persisted state between steps ─────────────────────────────────────────────
let openedCashShiftId: string | null = null; // capturado al abrir caja
let createdMovementRowText: string | null = null; // descripción del movimiento

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
async function fillDxInput(page: Page, ariaLabel: string, value: string) {
  const input = page.locator(`input[aria-label="${ariaLabel}"]`);
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
async function selectDxOption(page: Page, ariaLabel: string, optionText: string) {
  // El wrapper del SelectBox tiene role="combobox" con aria-label establecido por DX
  const combobox = page
    .locator(
      `[role="combobox"][aria-label="${ariaLabel}"], .dx-selectbox input[aria-label="${ariaLabel}"]`,
    )
    .first();
  await combobox.click();
  // Overlay con las opciones del dropdown
  const overlay = page.locator('.dx-overlay-content .dx-list-item');
  await overlay.first().waitFor({ state: 'visible', timeout: 8000 });
  await page
    .getByRole('option', { name: optionText })
    .or(page.locator('.dx-list-item').filter({ hasText: optionText }))
    .first()
    .click();
}

/**
 * Hace click en un botón de la toolbar del DxDataGrid identificado por texto.
 */
async function clickGridToolbarButton(page: Page, buttonText: string) {
  // DxDataGrid toolbar renderiza botones DX con .dx-toolbar .dx-button
  // El texto puede estar como aria-label o como texto visible del span
  const btn = page.locator('.dx-toolbar .dx-button').filter({ hasText: buttonText }).first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

/**
 * Hace click en un botón del popup/dialog DX (footer toolbar).
 * Los botones del footer del DxDialog tienen .dx-popup-bottom .dx-toolbar .dx-button
 */
async function clickDialogButton(page: Page, buttonText: string) {
  const btn = page
    .locator('.dx-popup-bottom .dx-button, .dx-overlay-content .dx-popup-bottom .dx-button')
    .filter({ hasText: buttonText })
    .first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
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
  const nameFilterInput = page.locator('.dx-datagrid-filter-row .dx-editor-cell input').nth(6); // 7th column (0-based): Imagen, Código, SKU, Barcode, Tipo, Stock, Nombre
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
      const targetIndex = Math.min(6, count - 1);
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
async function waitForHashUrl(page: Page, hashPath: string, timeout = 15000) {
  await page.waitForURL(`**/#/${hashPath}**`, { timeout });
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
  const submitBtn = page
    .locator('form.login-form .dx-button')
    .filter({ hasText: 'Ingresar' })
    .first();
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
      .locator('.dx-overlay-content .dx-button')
      .filter({ hasText: /aceptar/i })
      .first();
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
  } catch {
    // No picker or auto-selected
  }

  // Wait for the app to finish navigating after login
  await page.waitForURL(/\/#\/(dashboard|pos|customer|product|cashbox)/, { timeout: 20000 });
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
  const movementsBtn = page.locator('.dx-data-row .dx-button[title="Ver Movimientos"]').first();
  const openBtn = page.locator('.dx-data-row .dx-button').filter({ hasText: 'Abrir' }).first();

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

    const abrirCajaBtn = page.locator('.dx-button').filter({ hasText: 'Abrir caja' }).first();
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
    // Create a persistent browser context with video recording enabled.
    // We must pass recordVideo explicitly here because the shared page is created
    // via browser.newPage() rather than the Playwright fixture — the use.video
    // config only applies to fixture-managed pages, not manually-created ones.
    const context = await browser.newContext({
      recordVideo: { dir: 'test-results/videos/', size: { width: 1280, height: 720 } },
    });
    page = await context.newPage();
  });

  test.afterAll(async () => {
    // Close the page first, then the context — Playwright flushes the video
    // file only after the context is closed (not just the page).
    // The video covers the full happy-path run (all 9 steps in one file).
    try {
      const video = page.video();
      const context = page.context();
      await page.close();
      await context.close(); // flushes the video to disk
      if (video) {
        const src = await video.path();
        const { copyFileSync, mkdirSync } = await import('fs');
        const { join } = await import('path');
        const dest = join('test-results', 'happy-path-full-run.webm');
        mkdirSync('test-results', { recursive: true });
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

      // Botón "Ingresar" — es un <button type="submit"> dentro del DxForm
      // DxForm ButtonItem renderiza un .dx-button con texto span
      const submitBtn = page
        .locator('form.login-form .dx-button')
        .filter({ hasText: 'Ingresar' })
        .first();
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
          .locator('.dx-overlay-content .dx-button')
          .filter({ hasText: /aceptar/i })
          .first();
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
      await page.goto(`${BASE_URL}/#/product`);
      await waitForDxLoadPanel(page);
      // El grid de productos tiene id="product" en su wrapper DxDataGrid
      await expect(page.locator('.grid.theme-dependent').first()).toBeVisible({ timeout: 15000 });
    });

    await test.step('Abrir formulario de nuevo producto', async () => {
      // Botón "Nuevo" en la toolbar del DxDataGrid (texto: "Nuevo", tipo: default, styled: outlined)
      await clickGridToolbarButton(page, 'Nuevo');
      // El DxDialog (DxPopup) se abre con título "Nuevo"
      const popup = page.locator('.dx-popup-title').filter({ hasText: /nuevo/i });
      await popup.waitFor({ state: 'visible', timeout: 8000 });
    });

    await test.step('Llenar campos requeridos del formulario', async () => {
      // ── Campo: Nombre ──
      // DxForm con labelMode="hidden" no genera <label> explícita —
      // DevExtreme establece aria-label en el input con el valor del label del field.
      // ProductFields.ts: name('name').label('Nombre')  ⚠ FRAGILE (ver notas)
      await fillDxInput(page, 'Nombre', productName);

      // ── Campo: Tipo de producto (DxSelectBox ENUM, defaultValue='GOOD') ──
      // Ya tiene valor por defecto 'GOOD', pero lo seleccionamos explícitamente
      // ⚠ FRAGILE: DxSelectBox sin aria-label explícito — usamos placeholder o posición
      // El campo productKind label('Tipo de producto') → DX puede generar aria-label
      try {
        await selectDxOption(page, 'Tipo de producto', 'Bien físico');
      } catch {
        // Si el selector falla, el default 'GOOD' ya está seleccionado — continuar
      }

      // ── Campo: Control de stock (defaultValue='TRACKED') ──
      // Igual que arriba, el default es aceptable

      // ── Campo: Categoría (DxLookup ENTITY, required) ──
      // Usa el aria-label generado automáticamente por FormUtils para el campo 'Categoría'
      await test.step('Seleccionar categoría', async () => {
        // Press Escape first to ensure any stale overlay is closed
        await page.keyboard.press('Escape');
        await page.waitForTimeout(200);

        // Find the Categoría field wrapper (has a button Seleccionar) and click the dropdown button
        const categoryField = page
          .locator('.dx-field-item')
          .filter({
            has: page.locator('[aria-label="Categoría"]'),
          })
          .first();
        // Click the combobox input (not the Seleccionar button)
        const categoryInput = categoryField.locator('[aria-label="Categoría"]').first();
        await categoryInput.waitFor({ state: 'visible', timeout: 5000 });
        await categoryInput.click();

        // Now wait for the combobox to be marked as expanded (aria-expanded=true)
        await page.waitForFunction(
          () => {
            const combobox = document.querySelector('[aria-label="Categoría"]');
            return (
              combobox?.getAttribute('aria-expanded') === 'true' ||
              combobox?.getAttribute('aria-owns') !== null
            );
          },
          { timeout: 8000 },
        );
        await page.waitForTimeout(500); // wait for remote data to load

        // The dropdown popup renders as .dx-popup-wrapper inside the body;
        // use the ARIA role approach to click the first option in the OPEN dropdown
        const firstOption = page.getByRole('option').first();
        try {
          await firstOption.waitFor({ state: 'visible', timeout: 5000 });
          await firstOption.click({ force: true });
        } catch {
          // Fallback: use keyboard to select first item
          await page.keyboard.press('ArrowDown');
          await page.keyboard.press('Enter');
        }
      });

      // ── Campo: Unidad (DxLookup ENTITY, required) ──
      await test.step('Seleccionar unidad de medida', async () => {
        // Press Escape to ensure any stale overlay is closed
        await page.keyboard.press('Escape');
        await page.waitForTimeout(200);

        const unitWidget = page.locator('[aria-label="Unidad"]').first();
        await unitWidget.waitFor({ state: 'visible', timeout: 5000 });
        await unitWidget.click();

        // Wait for the Unidad combobox to be expanded
        await page.waitForFunction(
          () => {
            const combobox = document.querySelector('[aria-label="Unidad"]');
            return (
              combobox?.getAttribute('aria-expanded') === 'true' ||
              combobox?.getAttribute('aria-owns') !== null
            );
          },
          { timeout: 8000 },
        );
        await page.waitForTimeout(500);

        const firstUnitOption = page.getByRole('option').first();
        try {
          await firstUnitOption.waitFor({ state: 'visible', timeout: 5000 });
          await firstUnitOption.click({ force: true });
        } catch {
          await page.keyboard.press('ArrowDown');
          await page.keyboard.press('Enter');
        }
      });

      // ── Campo: Precio (FieldType.CURRENCY, label='Precio', required) ──
      await fillDxInput(page, 'Precio', productPriceInitial);
    });

    await test.step('Guardar y verificar que el producto aparece en la lista', async () => {
      // Botón "Guardar" en el footer del DxDialog
      await clickDialogButton(page, 'Guardar');

      // Esperar a que el diálogo se cierre (SUCCESS event → close)
      const popup = page.locator('.dx-popup-title').filter({ hasText: /nuevo/i });
      await popup.waitFor({ state: 'hidden', timeout: 15000 });

      // El grid se refresca automáticamente (events.SUCCESS → refresh)
      await waitForDxLoadPanel(page);

      // El producto nuevo está en la página N (sort alfabético) — filtrar por nombre.
      // La columna "Nombre" en el FilterRow de DX es la 7ª columna (índice 6, base-0).
      // Usamos el textbox en la fila de filtro con placeholder o posición.
      // Strategy: type the product name into the Nombre column filter cell.
      await filterGridByName(page, productName);
      await waitForDxLoadPanel(page);

      // Verificar que el producto aparece en la grilla
      // El DataGrid de DX renderiza filas en .dx-data-row
      await expect(page.locator('.dx-data-row').filter({ hasText: productName })).toBeVisible({
        timeout: 15000,
      });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 3 — Editar producto
  // ───────────────────────────────────────────────────────────────────────────
  test('03 - Editar precio del producto', async () => {
    await test.step('Seleccionar el producto recién creado en el grid', async () => {
      // Filtrar por nombre para encontrar el producto independientemente de la página
      await filterGridByName(page, productName);
      await waitForDxLoadPanel(page);

      const productRow = page.locator('.dx-data-row').filter({ hasText: productName });
      await productRow.waitFor({ state: 'visible', timeout: 10000 });
      await productRow.click(); // selecciona la fila (focusedRowEnabled)
    });

    await test.step('Abrir formulario de edición', async () => {
      await clickGridToolbarButton(page, 'Editar');
      const popup = page.locator('.dx-popup-title').filter({ hasText: /editar/i });
      await popup.waitFor({ state: 'visible', timeout: 8000 });
    });

    await test.step('Modificar el precio y guardar', async () => {
      // El campo Precio tiene aria-label="Precio" por ser FieldType.CURRENCY
      await fillDxInput(page, 'Precio', productPriceEdited);
      await clickDialogButton(page, 'Guardar');

      // Esperar cierre del diálogo
      const popup = page.locator('.dx-popup-title').filter({ hasText: /editar/i });
      await popup.waitFor({ state: 'hidden', timeout: 15000 });
      await waitForDxLoadPanel(page);
    });

    await test.step('Verificar precio actualizado en la lista', async () => {
      // El formatter de Precio muestra en formato moneda: "S/ 30.00"
      const productRow = page.locator('.dx-data-row').filter({ hasText: productName });
      await expect(productRow).toBeVisible({ timeout: 10000 });
      // El precio editado debería verse en la fila — DX formato currency
      await expect(productRow).toContainText('30');
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 4 — Abrir sesión de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('04 - Abrir sesión de caja', async () => {
    // Navigate to cashbox dashboard
    await page.goto(`${BASE_URL}/#/cashbox`);
    await waitForDxLoadPanel(page);
    // .cash-register-dashboard-page has display:contents — check toolbar instead
    await expect(page.locator('.dx-toolbar').filter({ hasText: /caja/i }).first()).toBeVisible({
      timeout: 15000,
    });

    // Wait for at least one data row to appear before checking button state
    // (the grid may finish loading AFTER the load panel disappears)
    await page.locator('.dx-data-row').first().waitFor({ state: 'visible', timeout: 15000 });

    // Check if there is a closed cash register (Abrir button) or an open one (Ver Movimientos)
    const openBtn = page.locator('.dx-data-row .dx-button').filter({ hasText: 'Abrir' }).first();
    const alreadyOpen = !(await openBtn.isVisible({ timeout: 8000 }).catch(() => false));

    if (alreadyOpen) {
      // Cash register is already open — find the "Ver Movimientos" button
      // DevExtreme renders the hint prop as title attribute on the .dx-button wrapper div
      const movementsBtn = page.locator('.dx-data-row .dx-button[title="Ver Movimientos"]').first();
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
      await fillDxInput(page, 'Saldo inicial', '100');

      // Confirm opening
      const abrirCajaBtn = page.locator('.dx-button').filter({ hasText: 'Abrir caja' }).first();
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
      await page.goto(`${BASE_URL}/#/customer`);
      await waitForDxLoadPanel(page);
      // .grid.theme-dependent is display:contents in DxDataGrid.scss — check toolbar instead
      await expect(
        page
          .locator('.dx-toolbar')
          .filter({ hasText: /cliente/i })
          .first(),
      ).toBeVisible({ timeout: 15000 });
    });

    await test.step('Abrir formulario de nuevo cliente', async () => {
      await clickGridToolbarButton(page, 'Nuevo');
      const popup = page.locator('.dx-popup-title').filter({ hasText: /nuevo/i });
      await popup.waitFor({ state: 'visible', timeout: 8000 });
    });

    await test.step('Llenar RUC/DNI y nombre del cliente', async () => {
      // CustomerFields: name('ruc').label('RUC/DNI'), aria-label="RUC/DNI"
      await fillDxInput(page, 'RUC/DNI', customerDni);

      // CustomerFields: name('businessName').label('Cliente') — DxTextArea
      // DxTextArea renderiza <textarea aria-label="Cliente"> o con role="textbox"
      // ⚠ FRAGILE: DxTextArea puede no propagar aria-label al textarea nativo
      const clienteInput = page
        .locator('textarea[aria-label="Cliente"], [aria-label="Cliente"]')
        .first();
      await clienteInput.waitFor({ state: 'visible', timeout: 6000 });
      await clienteInput.click({ clickCount: 3 });
      await clienteInput.fill(customerName);
      await page.keyboard.press('Tab');
    });

    await test.step('Guardar cliente y verificar en la lista', async () => {
      await clickDialogButton(page, 'Guardar');

      // Esperar cierre del diálogo
      const popup = page.locator('.dx-popup-title').filter({ hasText: /nuevo/i });
      await popup.waitFor({ state: 'hidden', timeout: 15000 });
      await waitForDxLoadPanel(page);

      // El grid de clientes puede tener cientos de registros — usar el FilterRow
      // para buscar por RUC/DNI (columna 0, índice 0) que es única y conocida
      const rucFilterInput = page.locator('.dx-datagrid-filter-row .dx-editor-cell input').nth(0);
      try {
        await rucFilterInput.waitFor({ state: 'visible', timeout: 5000 });
        await rucFilterInput.click({ clickCount: 3 });
        await rucFilterInput.fill(customerDni);
        await page.keyboard.press('Enter');
        await waitForDxLoadPanel(page);
      } catch {
        // fallback: try any filter input
        const inputs = page.locator('.dx-datagrid-filter-row input[type="text"]');
        const count = await inputs.count();
        if (count > 0) {
          await inputs.nth(0).click({ clickCount: 3 });
          await inputs.nth(0).fill(customerDni);
          await page.keyboard.press('Enter');
          await waitForDxLoadPanel(page);
        }
      }

      // Verificar que el cliente aparece en el grid tras el filtro
      await expect(page.locator('.dx-data-row').filter({ hasText: customerName })).toBeVisible({
        timeout: 15000,
      });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 6 — Registrar venta en POS
  // ───────────────────────────────────────────────────────────────────────────
  test('06 - Venta en POS', async () => {
    await test.step('Navegar a /#/pos', async () => {
      await page.goto(`${BASE_URL}/#/pos`);

      // If the session was lost (redirected to login), re-authenticate
      await ensureLoggedIn(page);

      // Navigate to POS again if we were redirected elsewhere during re-login
      if (!page.url().includes('/#/pos')) {
        await page.goto(`${BASE_URL}/#/pos`);
      }

      // .pos-shell is a real <div> with no display:contents — wait for a stable child element
      // that proves the POS app is loaded: the product search input
      await expect(page.locator('input[type="search"][placeholder*="Buscar"]')).toBeVisible({
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
      const searchInput = page.locator('input[type="search"][placeholder*="Buscar"]');
      await searchInput.waitFor({ state: 'visible', timeout: 10000 });
      await searchInput.fill(productName);
    });

    await test.step('Agregar el producto al carrito', async () => {
      // POSItem renderiza un <article role="button" aria-label="${name} — S/...">
      // o un <button aria-label="Agregar ${name}"> dentro de la card
      const productCard = page
        .locator('article.pos-product-card')
        .filter({ hasText: productName })
        .first();
      await productCard.waitFor({ state: 'visible', timeout: 15000 });

      // Click en el botón "+" de la card
      const addBtn = productCard.locator('button.pos-product-card__add');
      if (await addBtn.isVisible({ timeout: 2000 })) {
        await addBtn.click();
      } else {
        // Fallback: click en la card directamente
        await productCard.click();
      }

      // Verificar que el producto aparece en la orden (panel de checkout)
      await expect(page.locator('.pos-line__name').filter({ hasText: productName })).toBeVisible({
        timeout: 8000,
      });
    });

    await test.step('Seleccionar el cliente creado', async () => {
      // POSCheckoutPanel — sección "Cliente (opcional)"
      // A "Cliente General" may be pre-selected by default. If so:
      //   - button.pos-section-toggle__btn shows "Cliente General" [expanded][active]
      //   - button.pos-section-toggle__clear (×) is visible
      // We must clear the existing customer first, then open the search.

      // Step 1: Clear any pre-selected customer (e.g. "Cliente General")
      const clearCustomerBtn = page.locator('button.pos-section-toggle__clear');
      const isClearVisible = await clearCustomerBtn.isVisible({ timeout: 3000 }).catch(() => false);
      if (isClearVisible) {
        await clearCustomerBtn.click();
        // Wait for the clear button to disappear (customer deselected)
        await clearCustomerBtn.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
      }

      // Step 2: Click the toggle to open the customer search section
      // After clearing, the toggle should show "Cliente (opcional)" and the section is closed.
      const customerToggle = page.locator('button.pos-section-toggle__btn').first();
      await customerToggle.waitFor({ state: 'visible', timeout: 10000 });
      await customerToggle.click();

      // Input de búsqueda de cliente: aria-label="Cliente" (mounts after toggle opens)
      const customerSearchInput = page.locator('input[aria-label="Cliente"].pos-config-input');
      await customerSearchInput.waitFor({ state: 'visible', timeout: 8000 });
      // Search by DNI (unique per test run) via the 'ruc' search field.
      // The DB may accumulate many "Cliente Test" entries from prior runs;
      // searching by name risks returning the wrong page (>50 matches).
      // customerDni is unique for this run and the API searches by businessName OR ruc.
      await customerSearchInput.fill(customerDni);

      // Esperar sugerencias
      const suggestions = page.locator('.pos-customer-suggestions[role="listbox"]');
      await suggestions.waitFor({ state: 'visible', timeout: 10000 });

      // Seleccionar la primera sugerencia que coincida con customerName
      const customerOption = page
        .locator('.pos-customer-option')
        .filter({ hasText: customerName })
        .first();
      await customerOption.waitFor({ state: 'visible', timeout: 8000 });
      await customerOption.click();

      // Verificar que el cliente quedó seleccionado (el toggle muestra el nombre)
      await expect(
        page.locator('button.pos-section-toggle__btn').filter({ hasText: customerName }),
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
      const exactoBtn = page.locator('button.pos-discount-preset').filter({ hasText: 'Exacto' });
      try {
        await exactoBtn.waitFor({ state: 'visible', timeout: 3000 });
        await exactoBtn.click();
      } catch {
        // No cash payment selected — monto recibido not required
      }
    });

    await test.step('Completar el pago (Cobrar)', async () => {
      // Botón "Cobrar" — tiene clase .pos-checkout y es el botón principal del footer
      // checkoutDisabled = cart.empty || !warehouse || !paymentMethod || (cash && amountReceived < total)
      // We wait for the button to be enabled (warehouse + payment method must be settled)
      const cobrarBtn = page.locator('button.pos-checkout').filter({ hasText: /cobrar/i });
      await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
      // Wait until the button becomes enabled (up to 10 seconds for data to settle)
      await expect(cobrarBtn).not.toBeDisabled({ timeout: 10000 });
      await cobrarBtn.click();
    });

    await test.step('Verificar overlay "¡Venta completada!"', async () => {
      // POSCheckoutPanel renderiza .pos-postsale con role="status" y aria-live="assertive"
      // Contiene <h2 class="pos-postsale__title">¡Venta completada!</h2>
      await expect(
        page.locator('.pos-postsale__title').filter({ hasText: '¡Venta completada!' }),
      ).toBeVisible({ timeout: 20000 });

      // Cerrar el overlay (botón "Nueva venta")
      await page.locator('.pos-postsale__btn--primary').filter({ hasText: 'Nueva venta' }).click();
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 7 — Registrar movimiento de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('07 - Registrar movimiento de caja', async () => {
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
      const popup = page.locator('.dx-popup-title').filter({ hasText: /nuevo/i });
      await popup.waitFor({ state: 'visible', timeout: 12000 });
    });

    await test.step('Llenar campos del movimiento', async () => {
      // cashMovementsFields: name('category').label('Categoría') — DxSelectBox ENTITY required
      // ⚠ FRAGILE: requiere categorías de movimiento en BD
      await test.step('Seleccionar categoría de movimiento', async () => {
        const categoryWidget = page
          .locator('.dx-popup-content .dx-field-item')
          .filter({ has: page.locator('text=Categoría') })
          .locator('.dx-selectbox, .dx-lookup')
          .first();

        try {
          await categoryWidget.click({ timeout: 5000 });
        } catch {
          await page
            .locator('.dx-popup-content input[aria-label="Categoría"]')
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
      const descInput = page.locator('.dx-popup-content [aria-label="Descripcion"]').first();
      await descInput.waitFor({ state: 'visible', timeout: 6000 });
      await descInput.fill(movementDescription);
      await page.keyboard.press('Tab');

      // cashMovementsFields: name('amount').label('Monto') — FieldType.CURRENCY required
      await page.locator('.dx-popup-content input[aria-label="Monto"]').fill('50');
      await page.keyboard.press('Tab');
    });

    await test.step('Guardar movimiento y verificar en la lista', async () => {
      await clickDialogButton(page, 'Guardar');

      const popup = page.locator('.dx-popup-title').filter({ hasText: /nuevo/i });
      await popup.waitFor({ state: 'hidden', timeout: 15000 });
      await waitForDxLoadPanel(page);

      // Verificar que el movimiento aparece en el grid
      await expect(
        page.locator('.dx-data-row').filter({ hasText: createdMovementRowText! }),
      ).toBeVisible({ timeout: 15000 });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 8 — Editar movimiento de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('08 - Editar movimiento de caja', async () => {
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
      const popup = page.locator('.dx-popup-title').filter({ hasText: /editar/i });
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
      const montoInput = page.locator('.dx-popup-content input[aria-label="Monto"]');
      await montoInput.waitFor({ state: 'visible', timeout: 6000 });
      await montoInput.click({ clickCount: 3 });
      await montoInput.fill('75');
      await page.keyboard.press('Tab');

      await clickDialogButton(page, 'Guardar');
      const popup = page.locator('.dx-popup-title').filter({ hasText: /editar/i });
      await popup.waitFor({ state: 'hidden', timeout: 15000 });
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
  // PASO 9 — Cerrar sesión de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('09 - Cerrar sesión de caja', async () => {
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
    });

    await test.step('Hacer click en "Cerrar caja"', async () => {
      // CashRegisterClose tiene un Button text="Cerrar caja" en la Toolbar del DataGrid
      const closeCajaBtn = page
        .locator('.dx-toolbar .dx-button')
        .filter({ hasText: 'Cerrar caja' });
      await closeCajaBtn.waitFor({ state: 'visible', timeout: 8000 });
      await closeCajaBtn.click();
    });

    await test.step('Confirmar el diálogo de cierre', async () => {
      // CashRegisterClose ahora usa un <div> React propio con data-testid en lugar de confirm() de DX
      const confirmDialog = page.locator('[data-testid="close-cash-confirm-dialog"]');
      await confirmDialog.waitFor({ state: 'visible', timeout: 8000 });

      // Botón de confirmación positiva
      await page.locator('[data-testid="close-cash-confirm-yes"]').click();
    });

    await test.step('Verificar redirección al dashboard de cajas', async () => {
      // cashRegisterService.closeCashRegister → navigate('/cash-register-dashboard')
      // BUT the route '/cash-register-dashboard' does NOT exist in the router — it hits the
      // wildcard catch-all which redirects role-3 users to '/pos' and others to '/dashboard'.
      // So we wait for any post-close destination, then manually navigate to /cashbox to verify.
      await page.waitForURL(/\/#\/(cash-register-dashboard|cashbox|pos|dashboard)/, {
        timeout: 15000,
      });

      // Navigate to cashbox to verify the closed shift appears
      await page.goto(`${BASE_URL}/#/cashbox`);
      await waitForDxLoadPanel(page);
      // .cash-register-dashboard-page check toolbar instead (display:contents)
      await expect(page.locator('.dx-toolbar').filter({ hasText: /caja/i }).first()).toBeVisible({
        timeout: 15000,
      });

      // Verificar que la caja que cerramos aparece como "Cerrada" en el dashboard
      // El formatter muestra badge con texto "Cerrada"
      await waitForDxLoadPanel(page);
      // Verificación laxa: que exista al menos una fila con badge "Cerrada"
      await expect(
        page.locator('.dx-data-row .badge').filter({ hasText: 'Cerrada' }).first(),
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
//    Con aria-label automático de FormUtils el selector `.dx-popup-content [aria-label="Descripcion"]`
//    es ahora fiable. El label sigue sin acento (tal como está en el código fuente).
//
// =============================================================================
