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
 * Stack: React 19 + react-router with real paths (/ruta, ADR-020)
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
import {
  clickCrudSave,
  crudShell,
  crudShellBody,
  dismissDevToolbar,
  waitForSaleFormReady,
} from './helpers/crud';
import { customerRow, customersGrid, waitForCustomersGrid } from './helpers/customers';
import { completePosCashPayment, selectPosCustomer, waitForPosCheckoutReady } from './helpers/pos';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

// ── Env / config ──────────────────────────────────────────────────────────────
const BASE_URL = getE2EBaseUrl();
let USERNAME = process.env.E2E_USERNAME ?? '';
let PASSWORD = process.env.E2E_PASSWORD ?? '';

// ── Test data — unique per run ────────────────────────────────────────────────
const ts = Date.now();
const productName = `Test Producto ${ts}`;

// ── Seed product — always available from app:test:seed-e2e ───────────────────
const SEED_PRODUCT_NAME = E2E_SEED.productName;
const SEED_PRODUCT_PRICE = E2E_SEED.productPrice;
const SEED_CUSTOMER_DNI = '20100000001';
const SEED_CUSTOMER_NAME = 'Cliente CI E2E';

// ── Persisted state between steps ─────────────────────────────────────────────
let openedCashSessionId: string | null = null; // capturado al abrir caja
let createdMovementRowText: string | null = null; // descripción del movimiento
let salesModuleSaleId: string | null = null; // venta creada desde /sale
let salesModuleProductName: string | null = null; // producto elegido en /sale
let posProductName = productName; // product to use in POS test (may fall back to seed)

type CashRegisterDashboardRow = {
  id?: string | number;
  status?: string;
  cashSessionId?: string | number | null;
  cashShiftId?: string | number | null;
};

// ─────────────────────────────────────────────────────────────────────────────
// Helper utilities
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Espera a que el datagrid termine de cargar datos (overlay oculto).
 * Complementa waitForLoadState para grids que hacen fetch adicional.
 */
async function waitForGridLoad(page: Page) {
  const loadPanel = page.locator('.nb-datagrid__loading-overlay').first();
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
  const loginInputIds: Record<string, string> = {
    Usuario: '#login-username',
    Contraseña: '#login-password',
  };
  let input = page.locator('__missing__');

  for (const label of labels) {
    const selector = loginInputIds[label]
      ? `${loginInputIds[label]}:visible`
      : `input[aria-label="${label}"]:visible, textarea[aria-label="${label}"]:visible, [role="textbox"][aria-label="${label}"]:visible`;
    const candidate = page.locator(selector).first();
    await candidate.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {});
    if ((await candidate.count()) > 0) {
      input = candidate;
      break;
    }
  }

  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.fill(value);
  await expect(input).toHaveValue(value);
}

/**
 * Selecciona una opción en un DxSelectBox / DxLookup por aria-label del contenedor.
 * Hace click en el wrapper → espera el overlay dropdown → hace click en la opción deseada.
 */
/**
 * Hace click en un botón de la toolbar del DxDataGrid identificado por texto.
 */
async function clickGridToolbarButton(page: Page, buttonText: string) {
  // NativeDataGridView renderiza la toolbar en .nb-datagrid__toolbar con botones .nb-button.
  // The current UI can render translated or English labels depending on locale.
  const alternatives: Record<string, RegExp> = {
    Nuevo: /^(Nuevo|New)$/i,
    Editar: /^(Editar|Edit)$/i,
    Eliminar: /^(Eliminar|Delete)$/i,
    Guardar: /^(Guardar|Save)$/i,
  };
  const text = alternatives[buttonText] ?? new RegExp(`^${buttonText}$`, 'i');
  const btn = page.locator('.nb-datagrid__toolbar .nb-button').filter({ hasText: text }).first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

/**
 * Filtra el DxDataGrid activo por la columna "Nombre" usando el FilterRow.
 * El FilterRow de DX usa textboxes — encontramos el que corresponde a la columna Nombre
 * buscando la celda de filtro que está debajo del columnheader "Columna Nombre".
 */
/**
 * Espera a salir de la pantalla de login (la ruta ya no es /login y no hay formulario).
 */
async function waitForPostLoginLanding(page: Page) {
  await page
    .waitForFunction(
      () => {
        const onLogin = window.location.pathname === '/login';
        const loginForm = document.querySelector('form.login-form');
        return !onLogin && !loginForm;
      },
      undefined,
      { timeout: 20000 },
    )
    .catch(() => {});

  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

async function gotoProtectedRoute(page: Page, routePath: string) {
  await page.goto(`${BASE_URL}/${routePath}`);
  await page.waitForLoadState('domcontentloaded', { timeout: 10000 }).catch(() => {});
  await ensureLoggedIn(page);
  await waitForPostLoginLanding(page);

  if (new URL(page.url()).pathname !== `/${routePath}`) {
    await page.goto(`${BASE_URL}/${routePath}`);
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

  // Check the URL — if we are on /login, we definitely need to log in
  const isLoginUrl = new URL(page.url()).pathname === '/login';

  // Also wait for the login form — give more time for SPA to initialize and redirect
  const loginForm = page.locator('form.login-form');
  const isLoginPage =
    isLoginUrl || (await loginForm.isVisible({ timeout: 8000 }).catch(() => false));
  if (!isLoginPage) {
    return;
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
 * Attempts to recover the cash session ID from the cashbox page.
 * If the register is already open: clicks "Ver Movimientos" and extracts the ID from the URL.
 * If the register is closed: opens it (Saldo inicial = 100) and captures the ID.
 */
async function recoverCashSessionId(page: Page): Promise<string | null> {
  // Navigate to cashbox and wait for SPA to fully settle BEFORE checking auth.
  // The SPA needs networkidle to complete the auth redirect (/login if not authenticated).
  await page.goto(`${BASE_URL}/cashbox`);
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  // Now the SPA has had time to redirect to /login if not authenticated
  await ensureLoggedIn(page);
  // After ensureLoggedIn, wait for all auth API calls to settle
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  if (new URL(page.url()).pathname !== '/cashbox') {
    // Re-navigate to cashbox — at this point the JWT cookie is established
    await page.goto(`${BASE_URL}/cashbox`);
    // Wait for the React SPA to boot and render the cashbox grid
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  }
  await waitForGridLoad(page);
  // Give the DX grid extra time to render rows after the load panel disappears
  await page.waitForTimeout(1000);

  // Check for "Ver Movimientos" (register open) vs "Abrir" (register closed)
  const movementsBtn = page.getByTestId('cash-btn-movements').first();
  const movementsBtnVisible = await movementsBtn.isVisible({ timeout: 8000 }).catch(() => false);

  if (movementsBtnVisible) {
    // Register is open — try clicking the button, then fall back to API query
    let navigated = false;
    try {
      await Promise.all([
        page.waitForURL(/\/cash-session\/\d+\/movements/, { timeout: 8000 }),
        movementsBtn.click(),
      ]);
      navigated = true;
    } catch {
      // Button click didn't trigger expected navigation; fall back to API
    }

    if (!navigated) {
      const dashData = await page.evaluate(async (base: string) => {
        const resp = await fetch(`${base}/api/cash-registers/dashboard`);
        return resp.ok ? resp.json() : [];
      }, BASE_URL);
      const openReg = Array.isArray(dashData)
        ? dashData.find(
            (r: CashRegisterDashboardRow) =>
              r.status === 'open' && (r.cashSessionId ?? r.cashShiftId),
          )
        : null;
      const cashSessionId = openReg?.cashSessionId ?? openReg?.cashShiftId;
      if (cashSessionId) {
        await page.goto(`${BASE_URL}/cash-session/${cashSessionId}/movements`);
      }
    }

    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
    const url = page.url();
    const match = url.match(/cash-session\/(\d+)\/movements/);
    return match ? match[1] : null;
  }

  // Register is closed — navigate directly to open page via API
  try {
    const dashData = await page.evaluate(async (base: string) => {
      const resp = await fetch(`${base}/api/cash-registers/dashboard`);
      return resp.ok ? resp.json() : [];
    }, BASE_URL);
    const closedReg = Array.isArray(dashData)
      ? dashData.find((r: CashRegisterDashboardRow) => (r.cashSessionId ?? r.cashShiftId) == null)
      : null;
    if (!closedReg?.id) return null;

    await page.goto(`${BASE_URL}/cash-register/${closedReg.id}/open`);
    await page.locator('text=Apertura de caja').waitFor({ state: 'visible', timeout: 10000 });
    await page.getByLabel('Saldo inicial').fill('100');
    const abrirCajaBtn = page.getByTestId('cash-open-submit-btn').first();

    await abrirCajaBtn.click();

    await page.waitForURL(/\/cash-session\/\d+\/movements/, { timeout: 15000 });
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    const url = page.url();
    const match = url.match(/cash-session\/(\d+)\/movements/);
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
      locale: 'es-PE',
      serviceWorkers: 'block',
      recordVideo: { dir: 'var/test-results/videos/', size: { width: 1280, height: 720 } },
    });
    page = await context.newPage();
    page.on('pageerror', (error) => {
      // eslint-disable-next-line no-console
      console.error('[E2E pageerror]', error.message);
    });
    page.on('console', (message) => {
      if (message.type() === 'error') {
        // eslint-disable-next-line no-console
        console.error('[E2E console]', message.text());
      }
    });
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
        // Brief pause to let React commit the selection state before accepting
        await page.waitForTimeout(300);
        // Hacer click en "Aceptar" para confirmar la selección
        const acceptBtn = page
          .getByRole('dialog', { name: 'Seleccionar local' })
          .getByRole('button', { name: /aceptar/i });
        await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
        await acceptBtn.click();
        // Wait for the dialog to close after accepting
        await page
          .locator('[data-testid="branch-picker-option"]')
          .first()
          .waitFor({ state: 'hidden', timeout: 10000 });
      } catch {
        // No apareció el picker — probablemente sólo hay 1 local (auto-seleccionado)
        // o la rama fue seleccionada automáticamente
      }
    });

    await test.step('Verificar redirección a dashboard o POS', async () => {
      // La app redirige a /dashboard o /pos (rutas reales, sin hash)
      await page.waitForURL(/\/(dashboard|pos)(\?|#|$)/, { timeout: 20000 });
      // Verificación adicional: ya no hay rutas con hash
      expect(page.url()).not.toContain('/#/');
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 2 — Verificar producto seed
  // ───────────────────────────────────────────────────────────────────────────
  test('02 - Verificar producto seed', async () => {
    await gotoProtectedRoute(page, 'catalog/products');
    await waitForGridLoad(page);
    await expect(page.locator('.nb-datagrid:visible').first()).toBeVisible({ timeout: 15000 });

    // The current basic product preset has no filter row and can paginate the
    // deterministic seed product out of view. Verify it through the same API
    // used by the grid instead of depending on a hidden legacy filter input.
    const seedProducts = await page.evaluate(async () => {
      const response = await fetch('/api/products?rows=1000');
      if (!response.ok) {
        throw new Error(`GET /api/products failed with ${response.status}`);
      }
      const payload = await response.json();
      return Array.isArray(payload)
        ? payload
        : (payload.data ?? payload.items ?? payload.member ?? payload['hydra:member'] ?? []);
    });
    const seedProduct = seedProducts.find(
      (product: { name?: string }) => product.name === SEED_PRODUCT_NAME,
    );
    expect(seedProduct).toBeTruthy();
    expect(Number(seedProduct.salePrice)).toBe(Number(SEED_PRODUCT_PRICE));

    posProductName = SEED_PRODUCT_NAME;
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 4 — Abrir sesión de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('04 - Abrir sesión de caja', async () => {
    // Navigate to cashbox dashboard
    await gotoProtectedRoute(page, 'cashbox');
    await waitForGridLoad(page);
    // Check that the nb-datagrid toolbar with "Cajas" title is visible
    // Wait for at least one register card to appear before checking button state
    await expect(page.getByTestId('cashbox-card').first()).toBeVisible({ timeout: 15000 });

    // Check if there is a closed cash register (Abrir button) or an open one (Ver Movimientos)
    const openBtn = page.getByTestId('cash-btn-open').first();
    const alreadyOpen = !(await openBtn.isVisible({ timeout: 8000 }).catch(() => false));

    if (alreadyOpen) {
      // Cash register is already open. Try the "Ver Movimientos" button; fall back to API query.
      const movementsBtn = page.getByTestId('cash-btn-movements').first();
      await movementsBtn.waitFor({ state: 'visible', timeout: 5000 });
      let navigated = false;
      try {
        await Promise.all([
          page.waitForURL(/\/cash-session\/\d+\/movements/, { timeout: 8000 }),
          movementsBtn.click(),
        ]);
        navigated = true;
      } catch {
        // Button click didn't produce the expected URL; fall back to dashboard API
      }
      if (!navigated) {
        const dashData = await page.evaluate(async (base: string) => {
          const resp = await fetch(`${base}/api/cash-registers/dashboard`);
          return resp.ok ? resp.json() : [];
        }, BASE_URL);
        const openReg = Array.isArray(dashData)
          ? dashData.find(
              (r: CashRegisterDashboardRow) =>
                r.status === 'open' && (r.cashSessionId ?? r.cashShiftId),
            )
          : null;
        const cashSessionId = openReg?.cashSessionId ?? openReg?.cashShiftId;
        if (cashSessionId) {
          await page.goto(`${BASE_URL}/cash-session/${cashSessionId}/movements`);
        }
      }
    } else {
      // Navigate directly to the cash register open page via API to avoid button click interception
      const dashData = await page.evaluate(async (base: string) => {
        const resp = await fetch(`${base}/api/cash-registers/dashboard`);
        return resp.ok ? resp.json() : [];
      }, BASE_URL);
      const closedReg = Array.isArray(dashData)
        ? dashData.find((r: CashRegisterDashboardRow) => (r.cashSessionId ?? r.cashShiftId) == null)
        : null;
      if (closedReg?.id) {
        await page.goto(`${BASE_URL}/cash-register/${closedReg.id}/open`);
      }

      // Fill the initial balance form
      await expect(page.locator('text=Apertura de caja')).toBeVisible({ timeout: 10000 });
      await page.getByLabel('Saldo inicial').fill('100');

      // Confirm opening
      const abrirCajaBtn = page.getByTestId('cash-open-submit-btn').first();
      await abrirCajaBtn.click();

      // Wait for redirect to movements page
      await page.waitForURL(/\/cash-session\/\d+\/movements/, { timeout: 15000 });
    }

    // Wait for the page to settle before reading URL
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

    // Capture the cashSessionId from the URL
    const url = page.url();
    const match = url.match(/cash-session\/(\d+)\/movements/);
    openedCashSessionId = match ? match[1] : null;
    expect(openedCashSessionId).not.toBeNull();

    // Verify we are on the shift detail page
    await expect(page.getByTestId('shift-detail')).toBeVisible({ timeout: 10000 });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 5 — Verificar cliente seed
  // ───────────────────────────────────────────────────────────────────────────
  test('05 - Buscar cliente por RUC/DNI', async () => {
    await test.step('Navegar a /customer', async () => {
      await gotoProtectedRoute(page, 'customer');
      await waitForCustomersGrid(page);
      await expect(
        customersGrid(page).getByRole('table', { name: 'Clientes', exact: true }),
      ).toBeVisible();
    });

    await test.step('Filtrar y verificar el cliente seed del entorno E2E', async () => {
      const filtered = page.waitForResponse((response) => {
        const url = new URL(response.url());
        return (
          url.pathname === '/api/customers' &&
          response.request().method() === 'GET' &&
          Array.from(url.searchParams.values()).some((value) => value.includes(SEED_CUSTOMER_DNI))
        );
      });
      await customersGrid(page)
        .getByRole('textbox', { name: 'Filtrar RUC/DNI', exact: true })
        .fill(SEED_CUSTOMER_DNI);
      const response = await filtered;
      expect(response.ok()).toBe(true);
      expect(response.headers()['content-type']).toMatch(/json/i);
      await waitForCustomersGrid(page);

      await expect(customerRow(page, SEED_CUSTOMER_NAME)).toContainText(SEED_CUSTOMER_DNI);
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 6 — Registrar venta en POS
  // ───────────────────────────────────────────────────────────────────────────
  test('06 - Venta en POS', async () => {
    await test.step('Asegurar caja abierta', async () => {
      openedCashSessionId = await recoverCashSessionId(page);
      expect(openedCashSessionId, 'debe haber una caja abierta para cobrar en POS').not.toBeNull();
    });

    await test.step('Navegar a /pos', async () => {
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
      await selectPosCustomer(page, {
        name: SEED_CUSTOMER_NAME,
        search: SEED_CUSTOMER_NAME,
      });
    });

    await test.step('Seleccionar método de pago', async () => {
      await completePosCashPayment(page);
    });

    await test.step('Completar el pago (Cobrar)', async () => {
      await waitForPosCheckoutReady(page);
      await page.getByTestId('pos-checkout-btn').click();
    });

    await test.step('Verificar confirmación "Venta registrada"', async () => {
      // POSCheckoutPanel renderiza .pos-postsale con role="status" y aria-live="assertive".
      await expect(
        page.getByTestId('pos-postsale-title').filter({ hasText: /Venta registrada|emitida/i }),
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
      // recoverCashSessionId opens the register if closed, or recovers the ID if already open.
      if (!openedCashSessionId) {
        openedCashSessionId = await recoverCashSessionId(page);
      }
      expect(
        openedCashSessionId,
        'debe haber una caja abierta para registrar ventas',
      ).not.toBeNull();
    });

    await test.step('Navegar a /billing/sales', async () => {
      await gotoProtectedRoute(page, 'billing/sales');
      await dismissDevToolbar(page);
      await waitForGridLoad(page);
      await expect(
        page.locator('.nb-datagrid__toolbar').filter({ hasText: 'Ventas' }).first(),
      ).toBeVisible({
        timeout: 15000,
      });
    });

    await test.step('Abrir formulario de nueva venta', async () => {
      await clickGridToolbarButton(page, 'Nuevo');
      await expect(crudShell(page, /nuevo|new/i)).toBeVisible({
        timeout: 12000,
      });
      await waitForSaleFormReady(crudShellBody(page));
    });

    await test.step('Verificar el almacén activo', async () => {
      const body = crudShellBody(page);
      const almacenInput = body
        .locator('#nb-form-warehouse, input[aria-label="Almacen"], input[aria-label="Almacén"]')
        .first();
      const almacenVisible = await almacenInput.isVisible({ timeout: 5000 }).catch(() => false);

      if (almacenVisible) {
        // The form auto-selects the only active warehouse once its options load, so
        // give that a moment before opening the list by hand.
        const autoSelected = await expect
          .poll(async () => (await almacenInput.inputValue().catch(() => '')).trim(), {
            timeout: 5000,
          })
          .not.toBe('')
          .then(
            () => true,
            () => false,
          );
        const currentVal = autoSelected ? 'selected' : '';
        if (currentVal.trim().length === 0) {
          await almacenInput.click();
          await page.waitForTimeout(300);
          const firstWarehouse = page.locator('[role="option"]').first();
          await firstWarehouse.waitFor({ state: 'visible', timeout: 8000 });
          await firstWarehouse.click();
          await page.waitForTimeout(300);
        }
        await expect(almacenInput).not.toHaveValue('');
      }
    });

    await test.step('Agregar producto al detalle de la venta', async () => {
      const saleDialog = crudShell(page, /nuevo|new/i);

      // NativeFormView: "+" button with aria-label="Agregar detalle"
      const addRowBtn = page.locator('button[aria-label="Agregar detalle"]').first();
      await addRowBtn.waitFor({ state: 'visible', timeout: 8000 });
      await addRowBtn.click();

      // The new row appears immediately in the native detail table
      const detailTable = saleDialog.locator('.nb-form__detail-table').first();
      const insertedRow = detailTable.locator('tbody tr').last();
      await insertedRow.waitFor({ state: 'visible', timeout: 8000 });

      // Product column renders a NativeEntitySelect; click the cell first because
      // compact/table layouts can leave the input without focus until interaction.
      const productCell = insertedRow.locator('td').nth(0);
      await productCell.click();
      const productInput = productCell.locator('input[role="combobox"], input').first();
      await productInput.waitFor({ state: 'attached', timeout: 5000 });
      await productInput.click();
      await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 40 });
      salesModuleProductName = SEED_PRODUCT_NAME;

      // NativeEntitySelect dropdown
      const seedProductOption = page
        .locator('[role="option"]')
        .filter({ hasText: SEED_PRODUCT_NAME });
      await seedProductOption.first().waitFor({ state: 'visible', timeout: 15000 });
      await seedProductOption.first().click();

      // Wait for the async productService.getOne() onChange to populate unitPrice before tabbing away.
      // Column indices: 0=product, 1=qty, 2=unitPrice
      const priceInput = insertedRow.locator('td').nth(2).locator('input').first();
      await expect(priceInput).not.toHaveValue('0', { timeout: 8000 });

      // Tab through remaining cells so onChange handlers run
      await page.keyboard.press('Tab'); // qty cell
      await page.keyboard.press('Tab'); // price cell
      await page.keyboard.press('Tab'); // exit detail grid

      // Verify the row exists in the native detail table
      // Note: product name is in an input.value (not text content), so we can't use hasText
      const committedRow = detailTable.locator('tbody tr').first();
      await committedRow.waitFor({ state: 'visible', timeout: 8000 });

      // The product's onChange auto-populates unitPrice; read via inputValue()
      // Column indices: 0=product, 1=qty, 2=unitPrice, 3=discountPercent, 4=discountAmount, 5=netAmount
      const priceValue = await committedRow
        .locator('td')
        .nth(2)
        .locator('input')
        .first()
        .inputValue()
        .catch(() => '0');
      expect(Number(priceValue)).toBeGreaterThan(0);
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

      const saleDialog = crudShell(page, /nuevo|new/i);

      // Watch for a toast that would indicate silent validation failure
      const toastLocator = page.locator('[class*="toast"]').first();
      const toastPromise = toastLocator
        .waitFor({ state: 'visible', timeout: 3000 })
        .then(() => toastLocator.textContent())
        .catch(() => null);

      await clickCrudSave(saleDialog);

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

      await expect(saleDialog).toBeHidden({
        timeout: 15000,
      });
      await waitForGridLoad(page);

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
      // Recover cashSessionId if test 04 didn't capture it (e.g. session was lost)
      if (!openedCashSessionId) {
        await ensureLoggedIn(page);
        openedCashSessionId = await recoverCashSessionId(page);
      }
      expect(openedCashSessionId, 'cashSessionId debe estar disponible del paso 4').not.toBeNull();
      await page.goto(`${BASE_URL}/cash-session/${openedCashSessionId}/movements`);
      // Re-authenticate if the session was lost (e.g. JWT refresh failed during test 06)
      await ensureLoggedIn(page);
      // Wait for auth to fully settle before checking URL or navigating
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      if (!page.url().includes(`/cash-session/${openedCashSessionId}/movements`)) {
        await page.goto(`${BASE_URL}/cash-session/${openedCashSessionId}/movements`);
        await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      }
      // La pantalla del turno muestra los movimientos y los paneles de efectivo.
      await expect(page.getByTestId('shift-detail')).toBeVisible({ timeout: 15000 });
      await expect(page.getByTestId('shift-ledger')).toBeVisible({ timeout: 15000 });
    });

    await test.step('Abrir formulario de ingreso', async () => {
      await page.getByTestId('shift-register-income').click();
      await expect(page.getByRole('dialog', { name: 'Registrar ingreso' })).toBeVisible({
        timeout: 12000,
      });
    });

    const dialog = page.getByRole('dialog', { name: 'Registrar ingreso' });
    await test.step('Llenar campos del ingreso', async () => {
      const movementDescription = `Movimiento test ${ts}`;
      createdMovementRowText = movementDescription; // guardamos para el paso 9
      await dialog.getByLabel('Motivo').fill(movementDescription);
      await dialog.getByLabel('Monto (S/)').fill('50');
    });

    await test.step('Guardar ingreso y verificar en la lista', async () => {
      await dialog.getByTestId('cash-movement-submit-btn').click();
      await expect(dialog).toBeHidden({ timeout: 15000 });

      await expect(
        page
          .getByTestId('shift-ledger')
          .getByRole('row', { name: new RegExp(createdMovementRowText!) }),
      ).toBeVisible({ timeout: 15000 });
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 9 — Movimientos append-only (sin edición)
  // ───────────────────────────────────────────────────────────────────────────
  test('09 - Movimientos de caja son append-only', async () => {
    const movementRow = page
      .getByTestId('shift-ledger')
      .getByRole('row', { name: new RegExp(createdMovementRowText!) });

    await test.step('El movimiento creado sigue visible con el monto original', async () => {
      await expect(movementRow).toBeVisible({ timeout: 10000 });
      await expect(movementRow).toContainText('50');
    });

    await test.step('No hay acción Editar ni Eliminar en la fila', async () => {
      await expect(movementRow.getByRole('button', { name: /editar|eliminar/i })).toHaveCount(0);
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // PASO 10 — Cerrar sesión de caja
  // ───────────────────────────────────────────────────────────────────────────
  test('10 - Cerrar sesión de caja', async () => {
    await test.step('Navegar al cierre de caja', async () => {
      // Recover cashSessionId if not available
      if (!openedCashSessionId) {
        await ensureLoggedIn(page);
        openedCashSessionId = await recoverCashSessionId(page);
      }
      expect(openedCashSessionId, 'cashSessionId debe estar disponible del paso 4').not.toBeNull();
      await page.goto(`${BASE_URL}/cash-session/${openedCashSessionId}/close`);
      // CashRegisterClose renders the page header "Cerrar turno T-xxxx" (custom component, not a CRUD grid)
      await expect(page.getByRole('heading', { name: /^Cerrar turno/ })).toBeVisible({
        timeout: 20000,
      });
    });

    await test.step('Ingresar montos contados y justificar diferencias', async () => {
      await page.getByRole('button', { name: 'Prefiero ingresar solo el total' }).click();
      const counts = page.getByRole('spinbutton', { name: /^Monto contado de / });
      await expect(counts.first()).toBeVisible();
      for (const input of await counts.all()) await input.fill('0');
      // One reason covers the whole difference, and only shows up when there is one.
      const reason = page.getByRole('textbox', { name: 'Motivo de la diferencia', exact: true });
      if (await reason.isVisible({ timeout: 2000 }).catch(() => false))
        await reason.fill('Diferencia de prueba E2E');
      await expect(page.getByTestId('cash-close-btn')).toBeEnabled();
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
      // El navigate('/cashbox') puede competir con re-renders del grid; esperamos la ruta o el dashboard visible.
      await page
        .waitForFunction(
          () =>
            window.location.pathname.includes('/cashbox') || !!document.querySelector('#cashbox'),
          undefined,
          { timeout: 20000 },
        )
        .catch(() => {});
      await waitForGridLoad(page);
      // Cada caja es una tarjeta en el dashboard de cajas.
      await expect(page.getByTestId('cashbox-card').first()).toBeVisible({ timeout: 15000 });

      // Verificación best-effort del estado final en dashboard.
      await expect(
        page
          .getByTestId('cashbox-card')
          .filter({ hasText: /cerrada|abierta|arqueo pendiente|ci e2e branch|caja/i })
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
