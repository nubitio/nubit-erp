import { expect, test, type Page } from '@playwright/test';

import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

async function fillDxInput(page: Page, ariaLabel: string, value: string) {
  const input = page.locator(`input[aria-label="${ariaLabel}"]`).first();
  await input.waitFor({ state: 'visible', timeout: 10_000 });
  await input.click({ clickCount: 3 });
  await page.keyboard.press('Control+a');
  await page.keyboard.press('Delete');
  await input.pressSequentially(value, { delay: 30 });
  await page.keyboard.press('Tab');
}

async function completeBranchSelectionIfVisible(page: Page) {
  const branchDialog = page.getByRole('dialog', { name: 'Seleccionar local' });
  try {
    await branchDialog.waitFor({ state: 'visible', timeout: 7_000 });
  } catch {
    return;
  }

  const seededBranchOption = branchDialog.getByRole('radio', { name: 'CI E2E Branch' });
  if (await seededBranchOption.isVisible({ timeout: 1_000 }).catch(() => false)) {
    await seededBranchOption.click();
  } else {
    await branchDialog.getByRole('radio').first().click();
  }

  const acceptButton = branchDialog.getByRole('button', { name: /aceptar/i });
  await acceptButton.click();
  await expect(branchDialog).toBeHidden({ timeout: 15_000 });
}

test('price-list route renders grid', async ({ page }) => {
  const consoleErrors: string[] = [];
  const failedAssets: string[] = [];

  // Only capture JS runtime errors (pageerror) and true JS console errors —
  // not transient network errors which produce "Failed to load resource" messages.
  // Network errors (502, 503) are caught separately via response listener.
  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      const text = msg.text();
      // Skip "Failed to load resource" — these are network errors, not JS errors
      if (text.startsWith('Failed to load resource')) return;
      consoleErrors.push(text);
    }
  });
  page.on('pageerror', (err) => consoleErrors.push(`pageerror: ${err.message}`));
  page.on('response', (response) => {
    const url = response.url();
    if (
      response.status() >= 400 &&
      (url.includes('localhost:5173') ||
        url.includes('localhost:5174') ||
        url.includes('/build/'))
    ) {
      failedAssets.push(`${response.status()} ${url}`);
    }
  });

  const seed = await getE2ESeed();
  await page.goto(`${BASE_URL}/#/login`);
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 20_000 });
  await fillDxInput(page, 'Usuario', seed.username);
  await fillDxInput(page, 'Contraseña', seed.password);
  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();
  await completeBranchSelectionIfVisible(page);

  await page.goto(`${BASE_URL}/#/price-list`);
  // Retry once on 502 — Vite assets may return 502 transiently under Docker load
  const titleVisible = await page.getByText('Tarifas', { exact: true }).isVisible({ timeout: 20_000 }).catch(() => false);
  if (!titleVisible || failedAssets.length > 0) {
    failedAssets.length = 0;
    await page.reload();
    await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
  }
  await expect(page.getByText('Tarifas', { exact: true })).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('.nb-datagrid, .nb-crud-page, .datagrid-list').first()).toBeVisible({
    timeout: 20_000,
  });

  console.log('failedAssets', failedAssets);
  console.log('consoleErrors', consoleErrors);
  expect(failedAssets, 'no failed Vite/build assets').toEqual([]);
  expect(consoleErrors, 'no JS runtime errors').toEqual([]);
});