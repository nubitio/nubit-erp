/**
 * e2e/reorder-page.spec.ts
 *
 * Smoke tests for /#/reorder — stock replenishment alerts.
 * Verifies grid toolbar, column presets, and empty/success state rendering.
 */

import { expect, test, type Page } from '@playwright/test';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import { waitForGridLoad } from './helpers/crud';

const BASE_URL = getE2EBaseUrl();
let USERNAME = '';
let PASSWORD = '';

async function login(page: Page) {
  await page.goto(`${BASE_URL}/#/login`);
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });

  const usernameInput = page.locator('input[aria-label="Usuario"]').first();
  await usernameInput.waitFor({ state: 'visible', timeout: 8000 });
  await usernameInput.click({ clickCount: 3 });
  await usernameInput.pressSequentially(USERNAME, { delay: 30 });
  await page.keyboard.press('Tab');

  const passwordInput = page.locator('input[aria-label="Contraseña"]').first();
  await passwordInput.waitFor({ state: 'visible', timeout: 8000 });
  await passwordInput.click({ clickCount: 3 });
  await passwordInput.pressSequentially(PASSWORD, { delay: 30 });

  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

  try {
    const firstBranch = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranch.waitFor({ state: 'visible', timeout: 6000 });
    const e2eBranch = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: 'CI E2E Branch' });
    const target = (await e2eBranch.count()) > 0 ? e2eBranch.first() : firstBranch;
    await target.click();
    await page.waitForTimeout(300);
    const acceptBtn = page
      .getByRole('dialog', { name: 'Seleccionar local' })
      .getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
    await page.locator('[data-testid="branch-picker-option"]').first().waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    // No branch picker — auto-selected.
  }

  await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
}

test.describe('Reorder page — alertas de reabastecimiento', () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    USERNAME = seed.username;
    PASSWORD = seed.password;

    const ctx = await browser.newContext();
    page = await ctx.newPage();
    await login(page);
  });

  test.afterAll(async () => {
    await page.close().catch(() => {});
    await page.context().close().catch(() => {});
  });

  test('01 - La página carga con toolbar y selector de columnas', async () => {
    await page.goto(`${BASE_URL}/#/reorder`);
    await waitForGridLoad(page);

    await expect(
      page.locator('.native-datagrid__toolbar').filter({ hasText: 'Alertas de reabastecimiento' }).first(),
    ).toBeVisible({ timeout: 15000 });

    // Column preset selector (AppDropdown compact in toolbar).
    const presetTrigger = page
      .locator('.native-datagrid__toolbar .app-dropdown')
      .filter({ hasText: /básico|completo|columnas/i })
      .first();
    await expect(presetTrigger).toBeVisible({ timeout: 8000 });
  });

  test('02 - Muestra grid con filas o empty state de éxito', async () => {
    await page.goto(`${BASE_URL}/#/reorder`);
    await waitForGridLoad(page);

    const rows = page.locator('.native-datagrid__row');
    const emptyState = page.locator('.native-datagrid__empty .app-empty-state').first();

    const rowCount = await rows.count();
    if (rowCount > 0) {
      await expect(rows.first()).toBeVisible();
      await expect(page.locator('.native-datagrid__toolbar').filter({ hasText: 'Producto' })).toBeVisible();
    } else {
      await expect(emptyState).toBeVisible({ timeout: 8000 });
      await expect(emptyState).toContainText(/sin alertas|niveles óptimos|stock/i);
    }
  });

  test('03 - El preset "Completo" muestra la columna Actualizado', async () => {
    await page.goto(`${BASE_URL}/#/reorder`);
    await waitForGridLoad(page);

    const presetTrigger = page
      .locator('.native-datagrid__toolbar .app-dropdown__trigger')
      .first();
    await presetTrigger.click();

    const fullOption = page
      .locator('.app-dropdown__menu button, .app-dropdown__option')
      .filter({ hasText: /^Completo$/i })
      .first();
    await fullOption.waitFor({ state: 'visible', timeout: 5000 });
    await fullOption.click();
    await page.waitForTimeout(300);

    await expect(
      page.locator('.native-datagrid__toolbar').filter({ hasText: 'Actualizado' }),
    ).toBeVisible({ timeout: 5000 });
  });
});