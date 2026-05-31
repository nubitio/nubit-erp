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

  await acceptButton.waitFor({ state: 'visible', timeout: 5_000 });
  await expect(acceptButton).toBeEnabled({ timeout: 5_000 });
  await acceptButton.click();
  await expect(branchDialog).toBeHidden({ timeout: 15_000 });
}

test.describe('Login — flujos de autenticación', () => {
  test('rechaza credenciales inválidas y permanece en el formulario de login', async ({ page }) => {
    await page.goto(`${BASE_URL}/#/login`);
    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });

    await fillDxInput(page, 'Usuario', 'usuario.invalido@e2e.test');
    await fillDxInput(page, 'Contraseña', 'contrasena_incorrecta_xyz_123');

    const loginResponsePromise = page.waitForResponse(
      (resp) => resp.url().includes('/api/auth/login') && resp.request().method() === 'POST',
      { timeout: 15000 },
    );

    await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

    const loginResponse = await loginResponsePromise;
    expect([400, 401]).toContain(loginResponse.status());

    // El formulario debe seguir visible — no redirige al app
    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 5000 });
    expect(page.url()).not.toMatch(/\/#\/(dashboard|pos|sale|cashbox)/);
  });
});

test.describe('Post-login shell entry regression', () => {
  test('mounts the authenticated shell at the redirected hash route without refresh', async ({
    page,
  }) => {
    const seed = await getE2ESeed();

    await page.addInitScript(() => {
      const key = '__efact_e2e_load_count__';
      const currentCount = Number(window.sessionStorage.getItem(key) ?? '0');
      window.sessionStorage.setItem(key, String(currentCount + 1));
    });

    await page.goto(`${BASE_URL}/#/login?redirect=%2Fuser`);

    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15_000 });

    await fillDxInput(page, 'Usuario', seed.username);
    await fillDxInput(page, 'Contraseña', seed.password);

    await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

    await completeBranchSelectionIfVisible(page);

    await expect.poll(() => page.url(), { timeout: 20_000 }).toBe(`${BASE_URL}/#/user`);
    await expect(page.locator('form.login-form')).toBeHidden({ timeout: 10_000 });
    await expect(page.locator('.side-nav-outer-toolbar')).toBeVisible({ timeout: 20_000 });

    await expect
      .poll(async () => page.evaluate(() => window.location.hash), { timeout: 5_000 })
      .toBe('#/user');

    await expect
      .poll(
        async () =>
          page.evaluate(() =>
            Number(window.sessionStorage.getItem('__efact_e2e_load_count__') ?? '0'),
          ),
        { timeout: 5_000 },
      )
      .toBe(1);
  });

  test('session expiry dialog clears branch context and returns to login', async ({ page }) => {
    const seed = await getE2ESeed();

    await page.goto(`${BASE_URL}/#/login?redirect=%2Fdashboard`);
    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15_000 });

    await fillDxInput(page, 'Usuario', seed.username);
    await fillDxInput(page, 'Contraseña', seed.password);

    await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();
    await completeBranchSelectionIfVisible(page);

    await expect(page.locator('.side-nav-outer-toolbar')).toBeVisible({ timeout: 20_000 });
    await expect
      .poll(async () => page.evaluate(() => localStorage.getItem('selectedBranchId')), {
        timeout: 5_000,
      })
      .not.toBeNull();

    await page.evaluate(() => {
      window.dispatchEvent(new CustomEvent('efact:session-expired'));
    });

    const dialog = page.getByRole('dialog', { name: 'Sesión expirada' });
    await expect(dialog).toBeVisible({ timeout: 5_000 });
    await dialog.getByRole('button', { name: 'Ir al login' }).click();

    await expect.poll(() => page.url(), { timeout: 10_000 }).toBe(`${BASE_URL}/#/login`);
    await expect
      .poll(
        async () => page.evaluate(() => ({
          branchId: localStorage.getItem('selectedBranchId'),
          branchName: localStorage.getItem('selectedBranchName'),
          user: localStorage.getItem('user'),
        })),
        { timeout: 5_000 },
      )
      .toEqual({ branchId: null, branchName: null, user: null });
  });
});
