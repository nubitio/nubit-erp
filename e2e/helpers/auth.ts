import { expect, type Page } from '@playwright/test';
import { E2E_SEED } from '../support/seedConstants';
import { getE2EBaseUrl } from '../support/e2eSeed';

async function fillDxInput(page: Page, ariaLabel: string, value: string) {
  const loginInputIds: Record<string, string> = {
    Usuario: '#login-username',
    Contraseña: '#login-password',
  };
  const input = page
    .locator(
      loginInputIds[ariaLabel]
        ? `${loginInputIds[ariaLabel]}:visible`
        : `input[aria-label="${ariaLabel}"], textarea[aria-label="${ariaLabel}"], [role="textbox"][aria-label="${ariaLabel}"]`,
    )
    .first();
  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.fill(value);
  await page.keyboard.press('Tab');
}

async function waitForPostLoginLanding(page: Page) {
  await page
    .waitForFunction(
      () => {
        const hash = window.location.hash ?? '';
        const loginForm = document.querySelector('form.login-form');
        return !hash.includes('/login') && !loginForm;
      },
      undefined,
      { timeout: 30000 },
    )
    .catch(() => {});

  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

/** Log in and complete branch selection when the picker appears. */
export async function login(page: Page, username: string, password: string) {
  const baseUrl = getE2EBaseUrl();

  await page.goto(`${baseUrl}/#/login`);
  await page.waitForLoadState('domcontentloaded', { timeout: 15000 }).catch(() => {});
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 25000 });

  await fillDxInput(page, 'Usuario', username);
  await fillDxInput(page, 'Contraseña', password);

  const loginResponsePromise = page.waitForResponse(
    (response) =>
      response.url().includes('/api/auth/login') && response.request().method() === 'POST',
    { timeout: 20000 },
  );

  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

  const loginResponse = await loginResponsePromise;
  expect(
    loginResponse.ok(),
    `login API debe responder 2xx (status ${loginResponse.status()})`,
  ).toBeTruthy();

  try {
    const firstBranch = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranch.waitFor({ state: 'visible', timeout: 7000 });
    const e2eBranch = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: E2E_SEED.branchName });
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

  await waitForPostLoginLanding(page);

  const onAppRoute =
    page.url().includes('/#/dashboard') ||
    page.url().includes('/#/pos') ||
    !(
      page.url().includes('/#/login') ||
      (await page
        .locator('form.login-form')
        .isVisible()
        .catch(() => false))
    );

  expect(onAppRoute, 'debe salir de /login tras autenticarse').toBeTruthy();
}
