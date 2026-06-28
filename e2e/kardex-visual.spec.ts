/**
 * Visual checks for kardex grouped columns, KPI strip and export actions.
 */

import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { waitForGridLoad } from './helpers/crud';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();
const SCREENSHOT_DIR = 'var/e2e';
const SEED_PRODUCT_CODE = 'E2E-CI-PRODUCT';
const SEED_PRODUCT_NAME = 'Producto CI E2E';

async function resolveProductId(page: import('@playwright/test').Page): Promise<string> {
  const productId = await page.evaluate(
    async ({ baseUrl, productCode, productName }) => {
      const response = await fetch(
        `${baseUrl}/api/products?code=${encodeURIComponent(productCode)}&page=1&itemsPerPage=100`,
        { cache: 'no-store' },
      );
      const payload = await response.json();
      const products = (payload['hydra:member'] ?? payload.member ?? payload ?? []) as Array<{
        id?: string;
        code?: string;
        name?: string;
      }>;
      const product =
        products.find((item) => item.code === productCode) ??
        products.find((item) => item.name === productName) ??
        null;
      return product?.id ?? null;
    },
    { baseUrl: BASE_URL, productCode: SEED_PRODUCT_CODE, productName: SEED_PRODUCT_NAME },
  );

  expect(productId, 'E2E product id').toBeTruthy();
  return productId!;
}

test.describe('Kardex — layout visual', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test.beforeEach(async ({ page }) => {
    const seed = await getE2ESeed();
    await login(page, seed.username, seed.password);
    const productId = await resolveProductId(page);
    await page.goto(`${BASE_URL}/#/inventory/movements?product=${productId}`, {
      waitUntil: 'networkidle',
    });
    await waitForGridLoad(page);
    await expect(page.getByTestId('inventory-kardex-page')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('kardex-product-card')).toBeVisible({ timeout: 15000 });
  });

  test('KPIs en una fila y separadores de grupo visibles', async ({ page }) => {
    const kpiRow = page.getByTestId('kardex-summary');
    await expect(kpiRow).toBeVisible();
    await expect(kpiRow.locator('.nb-kpi-metric-card')).toHaveCount(6);

    const kpiLayout = await kpiRow.evaluate((element) => {
      const style = window.getComputedStyle(element);
      return {
        columns: style.gridTemplateColumns,
        cardCount: element.querySelectorAll('.nb-kpi-metric-card').length,
      };
    });
    expect(kpiLayout.cardCount).toBe(6);
    expect(kpiLayout.columns.split(' ').length).toBeGreaterThanOrEqual(6);

    await expect(page.getByRole('columnheader', { name: /DOCUMENTO/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /ENTRADAS/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /SALIDAS/i })).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /SALDO/i })).toBeVisible();
    await expect(page.locator('.nb-datagrid__table--grouped-headers')).toBeVisible();

    const dividerCount = await page.locator('.nb-datagrid__col-group-divider').count();
    expect(dividerCount).toBeGreaterThan(0);

    const dividerAlignment = await page.evaluate(() => {
      const bandDividers = Array.from(
        document.querySelectorAll('.nb-datagrid__header-band-row .nb-datagrid__col-group-divider'),
      );
      const leafDividers = Array.from(
        document.querySelectorAll('.nb-datagrid__header-leaf-row .nb-datagrid__col-group-divider'),
      );
      if (bandDividers.length === 0 || leafDividers.length === 0) {
        return { ok: false, maxDelta: 999 };
      }

      const deltas = bandDividers.map((bandCell, index) => {
        const leafCell = leafDividers[index];
        if (!leafCell) return 999;
        const bandRight = bandCell.getBoundingClientRect().right;
        const leafRight = leafCell.getBoundingClientRect().right;
        return Math.abs(bandRight - leafRight);
      });

      return { ok: true, maxDelta: Math.max(...deltas) };
    });
    expect(dividerAlignment.ok).toBe(true);
    expect(dividerAlignment.maxDelta).toBeLessThan(2);

    await page.locator('.nb-datagrid__table-wrap').screenshot({
      path: `${SCREENSHOT_DIR}/kardex-grid-grouped.png`,
    });
    await page.locator('.nb-hub-panel').screenshot({
      path: `${SCREENSHOT_DIR}/kardex-dashboard-kpis.png`,
    });
  });

  test('toolbar exporta PDF y Excel sin botón imprimir', async ({ page }) => {
    const toolbar = page.locator('.nb-datagrid__toolbar');
    await expect(toolbar.getByRole('button', { name: /^PDF$/i })).toBeVisible();
    await expect(toolbar.getByRole('button', { name: /^Excel$/i })).toBeVisible();
    await expect(toolbar.getByRole('button', { name: /^Imprimir$/i })).toHaveCount(0);
  });

  test('API devuelve sourceReference sin uuid corto', async ({ page }) => {
    const productId = await resolveProductId(page);
    const references = await page.evaluate(
      async ({ baseUrl, productId }) => {
        const response = await fetch(
          `${baseUrl}/api/stock-movements?product=${encodeURIComponent(`${baseUrl}/api/products/${productId}`)}&itemsPerPage=20`,
          { cache: 'no-store' },
        );
        const payload = await response.json();
        const items = (payload['hydra:member'] ?? payload.member ?? []) as Array<{
          sourceReference?: string | null;
          sourceId?: string | null;
        }>;
        return items
          .map((item) => item.sourceReference ?? '')
          .filter((value) => value.trim() !== '');
      },
      { baseUrl: BASE_URL, productId },
    );

    for (const reference of references) {
      expect(reference).not.toMatch(/^[0-9a-f]{8}$/i);
    }
  });
});