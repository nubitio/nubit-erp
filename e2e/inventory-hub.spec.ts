/**
 * e2e/inventory-hub.spec.ts
 *
 * Validates the inventory hub UX (mockup phases A–C):
 *  - KPI summary cards
 *  - Filter chips (category + status)
 *  - Inline stock adjust drawer
 *  - Bulk receipt selection
 */

import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { columnPresetControl, crudShell, selectColumnPreset, waitForGridLoad } from './helpers/crud';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

async function resetInventoryFilters(page: Page) {
  const chips = page.getByTestId('inventory-filter-chips');
  await chips.getByRole('checkbox', { name: 'Todas' }).click();
  await chips.getByRole('button', { name: 'Todos', exact: true }).click();
  await waitForGridLoad(page);
}

/** Seeded E2E products — stable anchors regardless of tenant catalog size. */
function e2eStockProductCell(page: Page, index = 0) {
  return page
    .getByTestId('inventory-stock-page')
    .locator('.stock-product-cell')
    .filter({ hasText: /E2E-CI-PRODUCT/ })
    .nth(index);
}

async function waitForE2eStockRows(page: Page, minRows = 1) {
  for (let i = 0; i < minRows; i += 1) {
    await expect(e2eStockProductCell(page, i)).toBeVisible({ timeout: 15000 });
  }
}

async function gotoInventoryStock(page: Page) {
  // Force a fresh hub mount — same-hash navigation reuses React state (filters, presets).
  await page.goto(`${BASE_URL}/#/`);
  await page.goto(`${BASE_URL}/#/inventory/stock`, { waitUntil: 'networkidle' });
  await waitForGridLoad(page);
  await expect(page.getByTestId('inventory-stock-page')).toBeVisible({ timeout: 15000 });
  await resetInventoryFilters(page);
  await selectColumnPreset(page, 'Básico');
  await waitForGridLoad(page);
  await waitForE2eStockRows(page, 2);
}

test.describe('Inventory hub — existencias', () => {
  test.describe.configure({ mode: 'serial', retries: 1 });

  test.beforeEach(async ({ page }) => {
    const seed = await getE2ESeed();
    await login(page, seed.username, seed.password);
    await gotoInventoryStock(page);
  });

  test.afterEach(async ({ page }) => {
    for (const name of ['Ajustar stock', 'Ingreso masivo', 'Confirmar acción']) {
      const overlay = page.getByRole('dialog', { name }).or(page.getByRole('alertdialog', { name }));
      if (await overlay.isVisible().catch(() => false)) {
        await page.keyboard.press('Escape');
      }
    }
  });

  test('muestra KPIs y filtros del hub', async ({ page }) => {
    await expect(page.getByTestId('inventory-summary')).toBeVisible();
    await expect(page.getByTestId('inventory-summary').locator('.nb-kpi-metric-card')).toHaveCount(4);
    const chips = page.getByTestId('inventory-filter-chips');
    await expect(chips).toBeVisible();
    await expect(chips.getByRole('checkbox', { name: 'Todas' })).toBeVisible();
    await expect(chips.getByRole('button', { name: 'Todos', exact: true })).toBeVisible();
  });

  test('aplica ingreso desde el drawer de ajuste', async ({ page }) => {
    await page.locator('.nb-datagrid__loading-overlay').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    const row = page.getByRole('row', { name: /E2E-CI-PRODUCT-2/ });
    const adjustButton = row.getByTestId('stock-adjust-button');
    const drawer = page.getByRole('dialog', { name: 'Ajustar stock' });

    await expect(adjustButton).toBeVisible({ timeout: 8000 });
    await adjustButton.scrollIntoViewIfNeeded();
    await adjustButton.click();
    await expect(drawer).toBeVisible({ timeout: 8000 });

    const qtyInput = drawer.locator('input[type="number"]').first();
    await qtyInput.fill('1');

    const postPromise = page.waitForResponse(
      (res) => res.url().includes('/api/stock-entries') && res.request().method() === 'POST',
      { timeout: 15000 },
    );
    await drawer.getByRole('button', { name: 'Aplicar ajuste' }).click();
    const response = await postPromise;
    expect(response.status()).toBe(201);

    await expect(drawer).toBeHidden({ timeout: 8000 });
  });

  test('acción de fila abre drawer de ajuste', async ({ page }) => {
    await page.locator('.nb-datagrid__loading-overlay').waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    const row = page.getByRole('row', { name: /E2E-CI-PRODUCT-2/ });
    const adjustButton = row.getByTestId('stock-adjust-button');
    const drawer = page.getByRole('dialog', { name: 'Ajustar stock' });
    await adjustButton.scrollIntoViewIfNeeded();
    await expect(adjustButton).toBeEnabled();
    await expect(async () => {
      await adjustButton.click();
      await expect(drawer).toBeVisible({ timeout: 1500 });
    }).toPass({ timeout: 15000 });
    await expect(drawer.getByRole('button', { name: 'Ingreso' })).toBeVisible();
    await expect(drawer.getByRole('button', { name: 'Salida' })).toBeVisible();
    await expect(drawer.getByRole('button', { name: 'Conteo' })).toBeVisible();
    await drawer.getByRole('button', { name: 'Cancelar' }).click();
    await expect(drawer).toBeHidden({ timeout: 5000 });
  });

  test('selección múltiple habilita ingreso masivo', async ({ page }) => {
    await page.getByRole('row', { name: /E2E-CI-PRODUCT-2/ })
      .getByRole('checkbox', { name: 'Seleccionar fila' })
      .check();
    await page.getByRole('row', { name: /E2E-CI-PRODUCT - Producto CI E2E/ })
      .getByRole('checkbox', { name: 'Seleccionar fila' })
      .check();

    const actionsMenu = page.getByRole('button', { name: /Acciones \(2\)/ });
    await expect(actionsMenu).toBeVisible({ timeout: 8000 });
    await actionsMenu.click();
    const bulkBtn = page.getByRole('menuitem', { name: 'Ingreso masivo' });
    await expect(bulkBtn).toBeVisible({ timeout: 8000 });
    await bulkBtn.click();

    const confirmDialog = page.getByRole('alertdialog', { name: 'Confirmar acción' });
    await expect(confirmDialog).toBeVisible({ timeout: 8000 });
    await confirmDialog.getByRole('button', { name: 'Confirmar' }).click();

    const dialog = page.getByRole('dialog', { name: 'Ingreso masivo' });
    await expect(dialog).toBeVisible({ timeout: 8000 });
    const bulkPanel = dialog.getByTestId('bulk-stock-receipt-dialog');
    await expect(bulkPanel).toBeVisible();
    await expect(bulkPanel.getByText(/E2E-CI-PRODUCT/)).toHaveCount(2);
    await dialog.getByRole('button', { name: 'Cancelar' }).click();
    await expect(dialog).toBeHidden({ timeout: 5000 });
  });

  test('preset compacto reduce columnas visibles', async ({ page }) => {
    await expect(columnPresetControl(page)).toBeVisible({ timeout: 8000 });
    await selectColumnPreset(page, 'Compacto');
    await waitForGridLoad(page);
    await expect(page.getByRole('columnheader', { name: /Producto/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /En mano/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Disponible/i })).toHaveCount(0);
    await expect(page.getByRole('columnheader', { name: /Mínimo/i })).toHaveCount(0);
  });

  test('filtro por categoría oculta y restaura productos E2E', async ({ page }) => {
    const chips = page.getByTestId('inventory-filter-chips');
    const e2eCategory = await page
      .getByRole('row', { name: /E2E-CI-PRODUCT-2/ })
      .locator('.stock-product-link__category')
      .textContent();

    if (!e2eCategory?.trim()) {
      test.skip(true, 'El producto E2E no expone categoría en la grilla');
      return;
    }

    const categoryName = e2eCategory.trim();
    const otherCategory = chips
      .locator('.nb-filter-panel__chips')
      .getByRole('checkbox')
      .filter({ hasNotText: /^Todas$/ })
      .filter({ hasNotText: categoryName })
      .first();

    if (!(await otherCategory.isVisible({ timeout: 3000 }).catch(() => false))) {
      test.skip(true, 'No hay otra categoría visible para validar ocultar/restaurar productos E2E');
      return;
    }

    await expect(e2eStockProductCell(page, 0)).toBeVisible();
    await otherCategory.click();
    await waitForGridLoad(page);
    await expect(e2eStockProductCell(page, 0)).toHaveCount(0);

    await chips.getByRole('checkbox', { name: categoryName, exact: true }).click();
    await waitForGridLoad(page);
    await expect(chips.getByRole('checkbox', { name: categoryName, exact: true }))
      .toHaveClass(/is-active/);
    await expect(e2eStockProductCell(page, 0)).toBeVisible();
  });

  test('filtro de estado "Sin stock" actualiza la grilla', async ({ page }) => {
    const chips = page.getByTestId('inventory-filter-chips');
    await chips.getByRole('button', { name: 'Sin stock' }).click();
    await waitForGridLoad(page);
    await expect(chips.getByRole('button', { name: 'Sin stock' }))
      .toHaveClass(/nb-segmented-control__btn--active/);
  });

  test('acciones bulk aparecen en el dropdown Acciones de la toolbar', async ({ page }) => {
    const toolbar = page.locator('.nb-datagrid__toolbar');
    await page.getByRole('checkbox', { name: 'Seleccionar todas las filas' }).check();
    const actionsMenu = toolbar.getByRole('button', { name: /Acciones \(\d+\)/ });
    await expect(actionsMenu).toBeVisible({ timeout: 8000 });
    await actionsMenu.click();
    await expect(page.locator('.nb-datagrid__actions-popover')).toBeVisible();
    await expect(page.getByRole('menuitem', { name: 'Ingreso masivo' })).toBeVisible();
    await expect(page.locator('.nb-hub-panel .nb-datagrid__bulk-actions')).toHaveCount(0);
  });

  test('kardex carga desde /inventory/movements', async ({ page }) => {
    await page.goto(`${BASE_URL}/#/inventory/movements`, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('inventory-kardex-page')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('kardex-context-bar')).toBeVisible();
    await expect(page.getByTestId('kardex-empty-prompt')).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /DOCUMENTO/i })).toBeVisible();
  });
});

test.describe('Inventory hub — operaciones', () => {
  test.beforeEach(async ({ page }) => {
    const seed = await getE2ESeed();
    await login(page, seed.username, seed.password);
    await page.goto(`${BASE_URL}/#/inventory/operations`, { waitUntil: 'networkidle' });
  });

  test('muestra tarjetas de operación disponibles', async ({ page }) => {
    await expect(page.getByTestId('inventory-operations')).toBeVisible();
    await expect(page.getByTestId('inventory-operation-receipt')).toBeVisible();
    await expect(page.getByTestId('inventory-operation-cycle-count')).toBeVisible();
    await expect(page.getByTestId('inventory-operation-adjustment')).toBeVisible();
    await expect(page.getByTestId('inventory-operation-outbound')).toBeVisible();
  });

  test('conteo cíclico abre la página de conteo', async ({ page }) => {
    await page.getByTestId('inventory-operation-cycle-count').click();
    await page.waitForURL(/#\/inventory\/cycle-count/, { timeout: 10000 });
    await expect(page.getByTestId('cycle-count-page')).toBeVisible({ timeout: 10000 });
    await expect(page.getByTestId('cycle-count-table')).toBeVisible();
    await expect(page.getByTestId('cycle-count-row').first()).toBeVisible({ timeout: 15000 });
  });

  test('conteo cíclico habilita aplicar cuando hay diferencia', async ({ page }) => {
    await page.goto(`${BASE_URL}/#/inventory/cycle-count`, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('cycle-count-page')).toBeVisible({ timeout: 10000 });

    const e2eRow = page.getByRole('row').filter({ hasText: /E2E-CI-PRODUCT/ }).first();
    await expect(e2eRow).toBeVisible({ timeout: 15000 });

    const applyBtn = page.getByTestId('cycle-count-apply');
    await expect(applyBtn).toBeDisabled();

    const qtyInput = e2eRow.getByTestId('cycle-count-qty-input');
    const systemQty = Number(await qtyInput.inputValue());
    await qtyInput.fill(String(systemQty + 1));
    await expect(applyBtn).toBeEnabled();
    await expect(e2eRow.locator('.cycle-count-page__variance--up')).toHaveText('+1');
  });

  test('entrada manual navega al formulario de recepción', async ({ page }) => {
    await page.getByTestId('inventory-operation-receipt').click();
    await page.waitForURL(/#\/receipt\/new/, { timeout: 10000 });
    await expect(crudShell(page)).toBeVisible({ timeout: 8000 });
  });

  test('ajuste de stock navega al formulario', async ({ page }) => {
    await page.getByTestId('inventory-operation-adjustment').click();
    await page.waitForURL(/#\/stock-adjustment\/new/, { timeout: 10000 });
    await expect(crudShell(page)).toBeVisible({ timeout: 8000 });
  });
});
