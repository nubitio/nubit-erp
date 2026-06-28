/**
 * Visual + layout checks for the inventory hub compact chrome.
 * Saves reference screenshots under var/e2e/ for manual review.
 */

import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { selectColumnPreset, waitForGridLoad } from './helpers/crud';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();
const SCREENSHOT_DIR = 'var/e2e';

test.describe('Inventory hub — layout visual', () => {
  test.use({ viewport: { width: 1280, height: 720 } });

  test.beforeEach(async ({ page }) => {
    const seed = await getE2ESeed();
    await login(page, seed.username, seed.password);
    await page.goto(`${BASE_URL}/#/inventory/stock`, { waitUntil: 'networkidle' });
    await waitForGridLoad(page);
    await expect(page.getByTestId('inventory-stock-page')).toBeVisible({ timeout: 15000 });
    await selectColumnPreset(page, 'Básico');
    await waitForGridLoad(page);
  });

  test('chrome compacto deja espacio dominante para la grilla', async ({ page }) => {
    const layout = await page.evaluate(() => {
      const viewportHeight = window.innerHeight;
      const hubHeader = document.querySelector('.nb-feature-hub__header');
      const hubBanner = document.querySelector('.nb-feature-hub__banner');
      const hubPanel = document.querySelector('.nb-hub-panel');
      const datagridToolbar = document.querySelector('.nb-datagrid__toolbar');
      const tableWrap = document.querySelector('.nb-datagrid__table-wrap');

      const heightOf = (element: Element | null) =>
        element ? element.getBoundingClientRect().height : 0;

      const chromeHeight =
        heightOf(hubHeader) + heightOf(hubBanner) + heightOf(hubPanel) + heightOf(datagridToolbar);
      const gridHeight = heightOf(tableWrap);

      return {
        viewportHeight,
        chromeHeight,
        gridHeight,
        chromeRatio: chromeHeight / viewportHeight,
        gridRatio: gridHeight / viewportHeight,
      };
    });

    expect(layout.gridHeight).toBeGreaterThan(280);
    expect(layout.gridRatio).toBeGreaterThan(0.42);
    expect(layout.chromeRatio).toBeLessThan(0.42);

    await page.screenshot({
      path: `${SCREENSHOT_DIR}/inventory-stock-1280x720.png`,
      fullPage: false,
    });
  });

  test('KPIs, filtros y grilla son visibles sin scroll', async ({ page }) => {
    await expect(page.getByTestId('inventory-summary')).toBeVisible();
    await expect(page.getByTestId('inventory-summary').locator('.nb-kpi-metric-card')).toHaveCount(4);
    await expect(page.getByTestId('inventory-filter-chips')).toBeVisible();
    await expect(page.locator('.nb-datagrid__table-wrap')).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Producto/i })).toBeVisible();
    await expect(page.getByRole('row', { name: /E2E-CI-PRODUCT/ }).first()).toBeVisible({ timeout: 15000 });

    const aboveFold = await page.evaluate(() => {
      const gridTop = document.querySelector('.nb-datagrid__table-wrap')?.getBoundingClientRect().top ?? 9999;
      return gridTop < window.innerHeight * 0.55;
    });
    expect(aboveFold).toBe(true);

    await page.locator('.nb-hub-panel').screenshot({
      path: `${SCREENSHOT_DIR}/inventory-hub-dashboard.png`,
    });
    await page.locator('.nb-datagrid__table-wrap').screenshot({
      path: `${SCREENSHOT_DIR}/inventory-stock-grid.png`,
    });
  });

  test('hub compacto y operaciones se ven correctas', async ({ page }) => {
    await expect(page.locator('.nb-feature-hub--compact')).toBeVisible();
    await expect(page.locator('.nb-feature-hub__title')).toHaveText('Inventario');
    await expect(page.locator('.nb-filter-panel--toolbar')).toBeVisible();

    await page.goto(`${BASE_URL}/#/inventory/operations`, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('inventory-operations')).toBeVisible();
    await page.screenshot({
      path: `${SCREENSHOT_DIR}/inventory-operations-1280x720.png`,
      fullPage: false,
    });
  });
});