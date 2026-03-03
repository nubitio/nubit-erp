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
 *   E2E_BASE_URL   (default: http://web)
 *   E2E_HOST_HEADER (default: localhost)
 *   E2E_USERNAME   (default: admin@test.com)
 *   E2E_PASSWORD   (default: password)
 */

import { expect, test, type Page } from '@playwright/test';

// ── Env / config ──────────────────────────────────────────────────────────────
const BASE_URL = process.env.E2E_BASE_URL ?? 'http://web';
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
let openedCashShiftId: string | null = null;   // capturado al abrir caja
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
  await input.click({ clickCount: 3 });
  await input.fill(value);
  await page.keyboard.press('Tab'); // confirmar en DX
}

/**
 * Selecciona una opción en un DxSelectBox / DxLookup por aria-label del contenedor.
 * Hace click en el wrapper → espera el overlay dropdown → hace click en la opción deseada.
 */
async function selectDxOption(page: Page, ariaLabel: string, optionText: string) {
  // El wrapper del SelectBox tiene role="combobox" con aria-label establecido por DX
  const combobox = page
    .locator(`[role="combobox"][aria-label="${ariaLabel}"], .dx-selectbox input[aria-label="${ariaLabel}"]`)
    .first();
  await combobox.click();
  // Overlay con las opciones del dropdown
  const overlay = page.locator('.dx-overlay-content .dx-list-item');
  await overlay.first().waitFor({ state: 'visible', timeout: 8000 });
  await page.getByRole('option', { name: optionText }).or(
    page.locator('.dx-list-item').filter({ hasText: optionText })
  ).first().click();
}

/**
 * Hace click en un botón de la toolbar del DxDataGrid identificado por texto.
 */
async function clickGridToolbarButton(page: Page, buttonText: string) {
  // DxDataGrid toolbar renderiza botones DX con .dx-toolbar .dx-button
  // El texto puede estar como aria-label o como texto visible del span
  const btn = page
    .locator('.dx-toolbar .dx-button')
    .filter({ hasText: buttonText })
    .first();
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
 * Espera a que la URL del hash coincida con el patrón dado.
 * Playwright no tiene soporte nativo para hash routing; lo hacemos con waitForURL
 * usando el patrón completo (baseURL + /#/ + path).
 */
async function waitForHashUrl(page: Page, hashPath: string, timeout = 15000) {
  await page.waitForURL(`**/#/${hashPath}**`, { timeout });
}

// ─────────────────────────────────────────────────────────────────────────────
// Suite principal
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Happy Path — flujo completo de venta', () => {

  // Compartimos una sola page entre todos los steps (es un flujo secuencial)
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    // Aseguramos que el Host header del proyecto está activo
    await page.setExtraHTTPHeaders({
      Host: process.env.E2E_HOST_HEADER ?? 'localhost',
    });
  });

  test.afterAll(async () => {
    await page.close();
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 1 — Login
  // ───────────────────────────────────────────────────────────────────────────
  test('01 - Login y redirección al dashboard', async () => {

    await test.step('Navegar a /login', async () => {
      await page.goto(`${BASE_URL}/login`);
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
      // BranchPicker es un DxPopup — esperar hasta 5 segundos a que aparezca
      const branchPopup = page.locator('.dx-overlay-content').filter({
        has: page.locator('text=Seleccionar local, text=Seleccionar sucursal').first(),
      });

      try {
        await branchPopup.waitFor({ state: 'visible', timeout: 5000 });
        // Seleccionar la primera opción disponible en la lista
        const firstBranchOption = page.locator('.dx-list-item').first();
        await firstBranchOption.waitFor({ state: 'visible', timeout: 5000 });
        await firstBranchOption.click();
        // Si hay botón de confirmar, clickearlo
        const confirmBtn = page.locator('.dx-overlay-content .dx-button').filter({ hasText: /confirmar|aceptar|seleccionar/i });
        if (await confirmBtn.isVisible({ timeout: 2000 })) {
          await confirmBtn.click();
        }
      } catch {
        // No apareció el picker — probablemente sólo hay 1 local (auto-seleccionado)
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
      // ⚠ FRAGILE: DxLookup/DxSelectBox — necesita una categoría existente en BD
      // Usamos el primer ítem del dropdown
      await test.step('Seleccionar categoría', async () => {
        const categoryInput = page
          .locator('input[aria-label="Categoría"], .dx-field-item input')
          .first();
        // Para DxLookup hacemos click en el widget (no en el input directamente)
        const categoryWidget = page.locator('.dx-field-item')
          .filter({ has: page.locator('text=Categoría') })
          .locator('.dx-selectbox, .dx-lookup')
          .first();

        // Fallback: buscar el input y hacer click en el wrapper
        try {
          await categoryWidget.click({ timeout: 5000 });
        } catch {
          await categoryInput.click({ timeout: 5000 });
        }

        const firstOption = page.locator('.dx-overlay-content .dx-list-item').first();
        await firstOption.waitFor({ state: 'visible', timeout: 8000 });
        await firstOption.click();
      });

      // ── Campo: Unidad (DxLookup ENTITY, required) ──
      await test.step('Seleccionar unidad de medida', async () => {
        const unitWidget = page.locator('.dx-field-item')
          .filter({ has: page.locator('text=Unidad') })
          .locator('.dx-selectbox, .dx-lookup')
          .first();

        try {
          await unitWidget.click({ timeout: 5000 });
        } catch {
          await page.locator('input[aria-label="Unidad"]').click({ timeout: 5000 });
        }

        const firstOption = page.locator('.dx-overlay-content .dx-list-item').first();
        await firstOption.waitFor({ state: 'visible', timeout: 8000 });
        await firstOption.click();
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

      // Verificar que el producto aparece en la grilla
      // El DataGrid de DX renderiza filas en .dx-data-row
      await expect(
        page.locator('.dx-data-row').filter({ hasText: productName }),
      ).toBeVisible({ timeout: 15000 });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 3 — Editar producto
  // ───────────────────────────────────────────────────────────────────────────
  test('03 - Editar precio del producto', async () => {

    await test.step('Seleccionar el producto recién creado en el grid', async () => {
      // DxDataGrid con FilterRow visible — buscar por nombre en la fila de filtros
      // La fila de filtro tiene inputs .dx-editor-cell input (uno por columna)
      // La columna "Nombre" es la 4ª visible (después de imagen, código, sku, barcode...)
      // ⚠ FRAGILE: usamos el FilterRow de DX por placeholder/aria-label de columna
      // Alternativa: click directo en la fila del registro

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

    await test.step('Navegar al dashboard de cajas', async () => {
      await page.goto(`${BASE_URL}/#/cashbox`);
      await waitForDxLoadPanel(page);
      await expect(page.locator('.cash-register-dashboard-page')).toBeVisible({ timeout: 15000 });
    });

    await test.step('Buscar una caja disponible (estado Cerrada) y hacer click en Abrir', async () => {
      // El dashboard lista cajas. Buscamos un botón "Abrir" (text="Abrir") en alguna fila
      // cashRegisterDashboardFields: cajas cerradas tienen <Button text="Abrir" ...>
      // ⚠ FRAGILE: si todas las cajas están abiertas, esto falla
      // Manejo: si hay caja ya abierta, la usamos directamente (ver siguiente step)

      const openBtn = page.locator('.dx-data-row .dx-button').filter({ hasText: 'Abrir' }).first();

      try {
        await openBtn.waitFor({ state: 'visible', timeout: 8000 });
        await openBtn.click();
      } catch {
        // Ninguna caja cerrada encontrada — puede que ya haya una abierta
        // Intentamos usar la primera caja abierta (botón "Ver Movimientos" / "Cerrar Caja")
        const movementsBtn = page
          .locator('.dx-data-row .dx-button[hint="Ver Movimientos"]')
          .first();
        if (await movementsBtn.isVisible({ timeout: 3000 })) {
          // Extraer el cashShiftId de la URL al hacer click
          const [response] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'commit' }).catch(() => null),
            movementsBtn.click(),
          ]);
          void response;
          // Capturar el ID del shift desde la URL actual
          const url = page.url();
          const match = url.match(/cash-shift\/(\d+)\/movements/);
          openedCashShiftId = match ? match[1] : null;
          return; // saltamos el paso de apertura
        }
        throw new Error('No se encontró caja disponible para abrir ni caja ya abierta');
      }
    });

    await test.step('Ingresar saldo inicial y confirmar apertura', async () => {
      // CashRegisterOpen renderiza una Card con título "Apertura de caja"
      // y un DxForm con campo "Saldo inicial" (FieldType.CURRENCY, label='Saldo inicial')
      await expect(page.locator('text=Apertura de caja')).toBeVisible({ timeout: 10000 });
      await fillDxInput(page, 'Saldo inicial', '100');

      // Botón "Abrir caja" — es un <Button> de DX con texto "Abrir caja"
      const abrirCajaBtn = page.locator('.dx-button').filter({ hasText: 'Abrir caja' }).first();
      await abrirCajaBtn.click();
    });

    await test.step('Verificar redirección a movimientos y capturar cashShiftId', async () => {
      // CashRegisterOpen navega a '/cash-shift/:cashShiftId/movements' después de abrir
      await page.waitForURL(/\/#\/cash-shift\/\d+\/movements/, { timeout: 15000 });
      const url = page.url();
      const match = url.match(/cash-shift\/(\d+)\/movements/);
      openedCashShiftId = match ? match[1] : null;
      expect(openedCashShiftId).not.toBeNull();

      // Verificar que llegamos a la página de movimientos
      await expect(page.locator('.cash-movements-page')).toBeVisible({ timeout: 10000 });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 5 — Registrar cliente nuevo
  // ───────────────────────────────────────────────────────────────────────────
  test('05 - Crear cliente nuevo', async () => {

    await test.step('Navegar a /#/customer', async () => {
      await page.goto(`${BASE_URL}/#/customer`);
      await waitForDxLoadPanel(page);
      await expect(page.locator('.grid.theme-dependent').first()).toBeVisible({ timeout: 15000 });
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
      const clienteInput = page.locator('textarea[aria-label="Cliente"], [aria-label="Cliente"]').first();
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

      // Verificar que el cliente aparece en el grid
      await expect(
        page.locator('.dx-data-row').filter({ hasText: customerName }),
      ).toBeVisible({ timeout: 15000 });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 6 — Registrar venta en POS
  // ───────────────────────────────────────────────────────────────────────────
  test('06 - Venta en POS', async () => {

    await test.step('Navegar a /#/pos', async () => {
      await page.goto(`${BASE_URL}/#/pos`);
      // La shell del POS tiene class="pos-shell"
      await expect(page.locator('.pos-shell')).toBeVisible({ timeout: 20000 });
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
      await expect(page.locator('.pos-line__name').filter({ hasText: productName })).toBeVisible({ timeout: 8000 });
    });

    await test.step('Seleccionar el cliente creado', async () => {
      // POSCheckoutPanel — sección "Cliente (opcional)"
      // Hacer click en el toggle para abrir la sección
      const customerToggle = page.locator('button.pos-section-toggle__btn').filter({ has: page.locator('[data-lucide="user-2"], .lucide-user-2') }).or(
        page.locator('button.pos-section-toggle__btn').filter({ hasText: /cliente/i })
      ).first();

      await customerToggle.waitFor({ state: 'visible', timeout: 8000 });
      await customerToggle.click();

      // Input de búsqueda de cliente: aria-label="Cliente"
      const customerSearchInput = page.locator('input[aria-label="Cliente"].pos-config-input');
      await customerSearchInput.waitFor({ state: 'visible', timeout: 6000 });
      await customerSearchInput.fill(customerName.substring(0, 10)); // primeros 10 chars

      // Esperar sugerencias
      const suggestions = page.locator('.pos-customer-suggestions[role="listbox"]');
      await suggestions.waitFor({ state: 'visible', timeout: 8000 });

      // Seleccionar la primera sugerencia que coincida
      const customerOption = page
        .locator('.pos-customer-option')
        .filter({ hasText: customerName })
        .first();
      await customerOption.waitFor({ state: 'visible', timeout: 6000 });
      await customerOption.click();

      // Verificar que el cliente quedó seleccionado (el toggle muestra el nombre)
      await expect(
        page.locator('button.pos-section-toggle__btn').filter({ hasText: customerName })
      ).toBeVisible({ timeout: 6000 });
    });

    await test.step('Seleccionar método de pago', async () => {
      // POSCheckoutPanel — radiogroup "Método de pago"
      // Seleccionamos el primer método disponible (generalmente "Efectivo")
      const firstPaymentMethod = page
        .locator('[role="radiogroup"][aria-label="Método de pago"] label.pos-payment-btn')
        .first();
      await firstPaymentMethod.waitFor({ state: 'visible', timeout: 8000 });
      await firstPaymentMethod.click();

      // Si es pago en efectivo, aparece el campo "Monto recibido"
      // Ingresar monto exacto usando el botón "Exacto"
      const exactoBtn = page.locator('button.pos-discount-preset').filter({ hasText: 'Exacto' });
      if (await exactoBtn.isVisible({ timeout: 3000 })) {
        await exactoBtn.click();
      } else {
        // Si no hay efectivo, el campo de monto recibido no aparece — OK
      }
    });

    await test.step('Completar el pago (Cobrar)', async () => {
      // Botón "Cobrar" — tiene clase .pos-checkout y es el botón principal del footer
      const cobrarBtn = page.locator('button.pos-checkout').filter({ hasText: /cobrar/i });
      await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
      // Verificar que no está disabled
      await expect(cobrarBtn).not.toBeDisabled({ timeout: 5000 });
      await cobrarBtn.click();
    });

    await test.step('Verificar overlay "¡Venta completada!"', async () => {
      // POSCheckoutPanel renderiza .pos-postsale con role="status" y aria-live="assertive"
      // Contiene <h2 class="pos-postsale__title">¡Venta completada!</h2>
      await expect(
        page.locator('.pos-postsale__title').filter({ hasText: '¡Venta completada!' })
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
      expect(openedCashShiftId, 'cashShiftId debe estar disponible del paso 4').not.toBeNull();
      await page.goto(`${BASE_URL}/#/cash-shift/${openedCashShiftId}/movements`);
      await waitForDxLoadPanel(page);
      await expect(page.locator('.cash-movements-page')).toBeVisible({ timeout: 15000 });
    });

    await test.step('Abrir formulario de nuevo movimiento', async () => {
      await clickGridToolbarButton(page, 'Nuevo');
      // El DxDialog tiene título fijo "Movimiento de caja" (definido en CashMovements.tsx)
      const popup = page.locator('.dx-popup-title').filter({ hasText: /movimiento de caja/i });
      await popup.waitFor({ state: 'visible', timeout: 8000 });
    });

    await test.step('Llenar campos del movimiento', async () => {
      // cashMovementsFields: name('category').label('Categoría') — DxSelectBox ENTITY required
      // ⚠ FRAGILE: requiere categorías de movimiento en BD
      await test.step('Seleccionar categoría de movimiento', async () => {
        const categoryWidget = page.locator('.dx-popup-content .dx-field-item')
          .filter({ has: page.locator('text=Categoría') })
          .locator('.dx-selectbox, .dx-lookup')
          .first();

        try {
          await categoryWidget.click({ timeout: 5000 });
        } catch {
          await page.locator('.dx-popup-content input[aria-label="Categoría"]').click({ timeout: 5000 });
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

      // ⚠ FRAGILE: DxTextArea aria-label
      const descInput = page.locator('.dx-popup-content textarea[aria-label="Descripcion"], .dx-popup-content [aria-label="Descripcion"]').first();
      await descInput.waitFor({ state: 'visible', timeout: 6000 });
      await descInput.fill(movementDescription);
      await page.keyboard.press('Tab');

      // cashMovementsFields: name('amount').label('Monto') — FieldType.CURRENCY required
      await page.locator('.dx-popup-content input[aria-label="Monto"]').fill('50');
      await page.keyboard.press('Tab');
    });

    await test.step('Guardar movimiento y verificar en la lista', async () => {
      await clickDialogButton(page, 'Guardar');

      const popup = page.locator('.dx-popup-title').filter({ hasText: /movimiento de caja/i });
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
      const movementRow = page
        .locator('.dx-data-row')
        .filter({ hasText: createdMovementRowText! });
      await movementRow.waitFor({ state: 'visible', timeout: 10000 });
      await movementRow.click();
    });

    await test.step('Abrir formulario de edición', async () => {
      await clickGridToolbarButton(page, 'Editar');
      const popup = page.locator('.dx-popup-title').filter({ hasText: /editar/i });
      await popup.waitFor({ state: 'visible', timeout: 8000 });
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
      const movementRow = page
        .locator('.dx-data-row')
        .filter({ hasText: createdMovementRowText! });
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
      expect(openedCashShiftId, 'cashShiftId debe estar disponible del paso 4').not.toBeNull();
      await page.goto(`${BASE_URL}/#/cash-register/${openedCashShiftId}/close`);
      // CashRegisterClose renderiza un DataGrid con clase .grid.theme-dependent
      await expect(page.locator('.grid.theme-dependent').first()).toBeVisible({ timeout: 20000 });
      // La toolbar del grid tiene el título "Cierre de caja"
      await expect(page.locator('.grid-header').filter({ hasText: 'Cierre de caja' })).toBeVisible({ timeout: 10000 });
    });

    await test.step('Ingresar montos contados en el grid editable', async () => {
      // CashRegisterClose usa un DataGrid con Editing mode="cell" allowUpdating={true}
      // Las columnas editables son "Monto contado" (countedAmount) y "Nota" (note)
      // Cada fila corresponde a un método de pago

      // Esperamos a que se carguen los datos del summary
      await waitForDxLoadPanel(page);

      // Hacemos click en la primera celda editable de la columna "Monto contado"
      // La columna tiene caption="Monto contado" con dataField="countedAmount"
      // ⚠ FRAGILE: dependemos del índice de columna o del caption como aria-label
      const countedAmountCells = page.locator('.dx-data-row .dx-command-edit, .dx-data-row td').filter({
        // buscamos celdas que sean editables por índice de columna (columna 2 = monto contado)
      });

      // Estrategia: click en la primera celda de la 2ª columna de datos (monto contado)
      // Columnas: [expectedAmount, countedAmount, difference, note, updated(hidden)]
      // La celda de countedAmount es la 2ª columna visible → index 1 (0-based)
      const firstDataRow = page.locator('.dx-data-row').first();
      await firstDataRow.waitFor({ state: 'visible', timeout: 15000 });

      // Click en la 2ª td de la primera fila (countedAmount es columna 2)
      const countedAmountCell = firstDataRow.locator('td').nth(1);
      await countedAmountCell.click();

      // Una vez en modo edición de celda, DX renderiza un input dentro de la celda
      const cellEditor = page.locator('.dx-editor-cell input, .dx-texteditor-input').first();
      await cellEditor.waitFor({ state: 'visible', timeout: 5000 });
      await cellEditor.fill('0'); // monto contado = 0 (suficiente para el test)
      await page.keyboard.press('Tab'); // confirmar y avanzar
    });

    await test.step('Hacer click en "Cerrar caja"', async () => {
      // CashRegisterClose tiene un Button text="Cerrar caja" en la Toolbar del DataGrid
      const closeCajaBtn = page.locator('.dx-toolbar .dx-button').filter({ hasText: 'Cerrar caja' });
      await closeCajaBtn.waitFor({ state: 'visible', timeout: 8000 });
      await closeCajaBtn.click();
    });

    await test.step('Confirmar el diálogo de cierre', async () => {
      // CashRegisterClose usa devextreme/ui/dialog confirm() → renderiza un DxPopup nativo
      // El confirm de DX tiene dos botones: "Sí" y "No" (o "Yes"/"No" según locale)
      // El título del confirm es "Cerrar Caja"
      const confirmDialog = page.locator('.dx-dialog, .dx-overlay-content').filter({
        has: page.locator('text=seguro').or(page.locator('text=Cerrar Caja')),
      }).first();

      await confirmDialog.waitFor({ state: 'visible', timeout: 8000 });

      // Botón de confirmación positiva (Sí / Yes / OK)
      const yesBtn = page
        .locator('.dx-dialog-button, .dx-overlay-content .dx-button')
        .filter({ hasText: /^(Sí|Si|Yes|OK|Aceptar)$/i })
        .first();
      await yesBtn.click();
    });

    await test.step('Verificar redirección al dashboard de cajas', async () => {
      // cashRegisterService.closeCashRegister → navigate('/cash-register-dashboard')
      await page.waitForURL(/\/#\/cash-register-dashboard/, { timeout: 15000 });
      await expect(page.locator('.cash-register-dashboard-page')).toBeVisible({ timeout: 10000 });

      // Verificar que la caja que cerramos aparece como "Cerrada" en el dashboard
      // El formatter muestra badge con texto "Cerrada"
      await waitForDxLoadPanel(page);
      // Verificación laxa: que exista al menos una fila con badge "Cerrada"
      await expect(
        page.locator('.dx-data-row .badge').filter({ hasText: 'Cerrada' }).first()
      ).toBeVisible({ timeout: 15000 });
    });
  });

}); // end describe

// =============================================================================
// NOTAS DE SELECTORES INCIERTOS
// =============================================================================
//
// Los siguientes elementos carecen de data-testid o aria-label robustos.
// Se recomienda añadirlos para eliminar la fragilidad indicada con ⚠ FRAGILE:
//
// 1. FORMULARIO DE PRODUCTO — campos sin aria-label garantizado:
//    - Campos generados por DxForm + FieldBuilder: DX usa el label del field como
//      aria-label del input, pero SÓLO si labelMode != 'hidden'.
//      ProductPage usa labelMode="hidden" (por herencia de LoginForm, aunque DxForm
//      no lo establece explícitamente) → verificar en runtime.
//    RECOMENDACIÓN: añadir `inputAttr: { 'aria-label': fieldLabel }` en cada
//    FieldBuilder que no tenga buttons ni editorOptions explícitos.
//
// 2. DXSELECTBOX / DXLOOKUP de Categoría y Unidad en ProductFields:
//    Estos campos no tienen inputAttr, por lo que DevExtreme puede o no generar
//    aria-label. El selector usado (buscar .dx-field-item que tenga el texto del label
//    + buscar .dx-selectbox/.dx-lookup) es frágil si el markup cambia.
//    RECOMENDACIÓN: añadir `inputAttr: { 'data-testid': 'field-category' }` en
//    el FieldBuilder correspondiente.
//
// 3. CAMPO "TIPO" en cashMovementsFields:
//    Tiene `.visibleOnForm(false)` pero el campo 'type' es required. Si la lógica
//    de DxForm no envía un valor por defecto, el POST falla con error 422.
//    RECOMENDACIÓN: añadir `.defaultValue('income')` en el FieldBuilder del campo
//    type para garantizar que se envíe aunque no sea visible.
//
// 4. SELECTOR DE SUCURSAL (BranchPicker):
//    El componente BranchPicker no tiene data-testid en su popup ni en las opciones.
//    Si la empresa tiene múltiples sucursales, el flujo de login se detiene aquí.
//    RECOMENDACIÓN: añadir `data-testid="branch-picker-option"` a cada ítem de la lista.
//
// 5. CONFIRMACIÓN DE CIERRE DE CAJA (devextreme/ui/dialog confirm):
//    El confirm() de DX usa locale del browser — el botón puede llamarse "Sí", "Yes"
//    o "OK" según la configuración del idioma en el bundle DX.
//    RECOMENDACIÓN: configurar DX globalmente con `config({ defaultCurrency: 'PEN' })`
//    y establecer el locale español para garantizar texto "Sí".
//    Alternativa: migrar el confirm a un DxDialog custom con data-testid en los botones.
//
// 6. CELDAS DEL GRID EN CashRegisterClose:
//    El DataGrid de cierre no tiene IDs ni data-testid en las columnas. El selector
//    `.dx-data-row td.nth(1)` depende del orden de columnas renderizadas.
//    RECOMENDACIÓN: añadir `cssClass` o `name` en los Column de CashRegisterClose
//    para poder identificarlos con `.dx-col-countedAmount`.
//
// 7. CAMPO Descripcion en cashMovementsFields:
//    El label usa "Descripcion" (sin acento) — si DX normaliza el aria-label esto
//    puede variar. El selector usa el valor exacto del label tal como está en el código.
//
// =============================================================================
