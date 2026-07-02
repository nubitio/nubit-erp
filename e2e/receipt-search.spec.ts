/**
 * Public voucher search (/buscar) — result must render as a modal overlay,
 * including after hard refresh (PRG + session, not inline below the form).
 */

import { expect, test } from '@playwright/test';

const SEARCH = {
  ruc: '20000000001',
  voucherType: '01',
  series: 'F001',
  number: '670',
  date: '2026-06-09',
  total: '5012',
};

async function submitVoucherSearch(page: import('@playwright/test').Page) {
  await page.goto('/buscar');
  await page.locator('#ruc').fill(SEARCH.ruc);
  await page.locator('#voucher_type').selectOption(SEARCH.voucherType);
  await page.locator('#series').fill(SEARCH.series);
  await page.locator('#number').fill(SEARCH.number);
  await page.locator('#date').fill(SEARCH.date);
  await page.locator('#total').fill(SEARCH.total);
  await page.getByRole('button', { name: 'Buscar comprobante' }).click();
  await page.waitForURL('**/buscar');
}

async function expectModalOverlay(page: import('@playwright/test').Page) {
  const overlay = page.locator('#receipt-search-result');
  await expect(overlay).toBeVisible();
  await expect(overlay).toHaveClass(/receipt-search-overlay/);
  await expect(page.locator('.receipt-search-overlay__scrim')).toBeVisible();
  await expect(page.getByRole('dialog')).toBeVisible();
  await expect(page.getByRole('heading', { name: /Factura F001-670/i })).toBeVisible();

  const layout = await overlay.evaluate((el) => {
    const styles = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return {
      position: styles.position,
      parentTag: el.parentElement?.tagName ?? '',
      top: rect.top,
      left: rect.left,
      width: rect.width,
      height: rect.height,
    };
  });

  expect(layout.position).toBe('fixed');
  expect(layout.parentTag).toBe('BODY');
  expect(layout.top).toBe(0);
  expect(layout.left).toBe(0);

  const viewport = page.viewportSize();
  if (viewport) {
    expect(layout.width).toBeGreaterThanOrEqual(viewport.width - 1);
    expect(layout.height).toBeGreaterThanOrEqual(viewport.height - 1);
  }
}

test.describe('Consulta pública de comprobantes', () => {
  test.skip(!!process.env.CI, 'Public receipt-search fixture is not part of the CI E2E seed.');

  test('muestra el resultado en un overlay modal', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 568 });
    await submitVoucherSearch(page);
    await expectModalOverlay(page);
  });

  test('mantiene el overlay tras recargar la página (GET, sin reenvío POST)', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 568 });
    await submitVoucherSearch(page);
    await expectModalOverlay(page);

    await page.reload({ waitUntil: 'networkidle' });
    await expectModalOverlay(page);
    await expect(page).toHaveURL(/\/buscar$/);
  });

  test('cierra el overlay y limpia el estado de búsqueda', async ({ page }) => {
    await submitVoucherSearch(page);
    await expectModalOverlay(page);

    await page.locator('.receipt-search-overlay__actions .receipt-btn--secondary').click();
    await expect(page).toHaveURL(/\/buscar$/);
    await expect(page.locator('#receipt-search-result')).toHaveCount(0);
  });
});
