/**
 * Captures kardex grouped-header metrics for visual QA (label centering, divider alignment).
 */

import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { waitForGridLoad } from './helpers/crud';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();
const SCREENSHOT_DIR = 'var/e2e';
const SEED_PRODUCT_CODE = 'E2E-CI-PRODUCT';

async function resolveProductId(page: import('@playwright/test').Page): Promise<string> {
  const productId = await page.evaluate(
    async ({ baseUrl, productCode }) => {
      const response = await fetch(
        `${baseUrl}/api/products?code=${encodeURIComponent(productCode)}&page=1&itemsPerPage=100`,
        { cache: 'no-store' },
      );
      const payload = await response.json();
      const products = (payload['hydra:member'] ?? payload.member ?? payload ?? []) as Array<{
        id?: string;
        code?: string;
      }>;
      return products.find((item) => item.code === productCode)?.id ?? null;
    },
    { baseUrl: BASE_URL, productCode: SEED_PRODUCT_CODE },
  );

  expect(productId).toBeTruthy();
  return productId!;
}

test.describe('Kardex — header visual QA', () => {
  test.use({ viewport: { width: 1280, height: 800 } });

  test('group labels centered and dividers aligned', async ({ page }) => {
    const seed = await getE2ESeed();
    await login(page, seed.username, seed.password);
    const productId = await resolveProductId(page);
    await page.goto(`${BASE_URL}/#/inventory/movements?product=${productId}`, {
      waitUntil: 'networkidle',
    });
    await waitForGridLoad(page);
    await expect(page.getByTestId('inventory-kardex-page')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.nb-datagrid__table--grouped-headers')).toBeVisible();

    const metrics = await page.evaluate(() => {
      const labels = Array.from(
        document.querySelectorAll('.nb-datagrid__header-band-row .nb-datagrid__header-group-cell'),
      ).map((th) => {
        const rect = th.getBoundingClientRect();
        const style = window.getComputedStyle(th);

        return {
          text: th.textContent?.trim() ?? '',
          textAlign: style.textAlign,
          groupWidth: rect.width,
        };
      });

      const bandDividers = Array.from(
        document.querySelectorAll('.nb-datagrid__header-band-row .nb-datagrid__col-group-divider'),
      );
      const leafDividers = Array.from(
        document.querySelectorAll('.nb-datagrid__header-leaf-row .nb-datagrid__col-group-divider'),
      );
      const dividerDeltas = bandDividers.map((band, index) => {
        const leaf = leafDividers[index];
        if (!leaf) return 999;
        return Math.abs(band.getBoundingClientRect().right - leaf.getBoundingClientRect().right);
      });

      const sampleDivider = document.querySelector('.nb-datagrid__col-group-divider');
      const dividerColor = sampleDivider
        ? getComputedStyle(sampleDivider).borderRightColor
        : 'n/a';

      return {
        labels,
        dividerColor,
        dividerAlignmentMax: dividerDeltas.length ? Math.max(...dividerDeltas) : 0,
      };
    });

    for (const label of metrics.labels) {
      const expectedAlign = label.text === 'DOCUMENTO' ? 'left' : 'center';
      expect(label.textAlign, `align ${label.text}`).toBe(expectedAlign);
      expect(label.groupWidth, `width ${label.text}`).toBeGreaterThan(0);
    }
    expect(metrics.dividerAlignmentMax).toBeLessThan(2);
    expect(metrics.labels.map((l) => l.text)).toEqual(
      expect.arrayContaining(['DOCUMENTO', 'ENTRADAS', 'SALIDAS', 'SALDO']),
    );

    await page.locator('.nb-datagrid__table-wrap').screenshot({
      path: `${SCREENSHOT_DIR}/kardex-visual-review-grid.png`,
    });
    await page.locator('.nb-hub-panel').screenshot({
      path: `${SCREENSHOT_DIR}/kardex-visual-review-dashboard.png`,
    });
  });
});