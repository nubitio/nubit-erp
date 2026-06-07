import { expect, type Page } from '@playwright/test';
import { getE2EBaseUrl } from '../support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

/** Log in and complete branch selection when the picker appears. */
export async function login(page: Page, username: string, password: string) {
  await page.goto(`${BASE_URL}/#/login`);
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });

  const usernameInput = page.locator('input[aria-label="Usuario"]').first();
  await usernameInput.waitFor({ state: 'visible', timeout: 8000 });
  await usernameInput.click({ clickCount: 3 });
  await usernameInput.pressSequentially(username, { delay: 30 });
  await page.keyboard.press('Tab');

  const passwordInput = page.locator('input[aria-label="Contraseña"]').first();
  await passwordInput.waitFor({ state: 'visible', timeout: 8000 });
  await passwordInput.click({ clickCount: 3 });
  await passwordInput.pressSequentially(password, { delay: 30 });

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
    await page
      .locator('[data-testid="branch-picker-option"]')
      .first()
      .waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    // No branch picker — auto-selected.
  }

  await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
}