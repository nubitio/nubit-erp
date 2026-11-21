import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE = getE2EBaseUrl();

test.describe('role permissions dialog', () => {
  test('shows menus and actions as trees, and saves only real ids and keys', async ({ page }) => {
    const saved: Record<string, unknown> = {};
    // Saving is intercepted so the test changes nothing on the account's roles.
    for (const [path, name] of [
      ['**/api/role-menu', 'menu'],
      ['**/api/role-permissions', 'permissions'],
    ] as const) {
      await page.route(path, async (route) => {
        if (route.request().method() !== 'POST') return route.fallback();
        saved[name] = route.request().postDataJSON();
        return route.fulfill({ json: { success: true, message: 'Permisos guardados' } });
      });
    }

    const seed = await getE2ESeed();
    await login(page, seed.username, seed.password);
    await page.goto(`${BASE}/config/roles`);
    await page
      .getByText(/^(Super Administrador|Administrador|Cajero)$/)
      .first()
      .click();
    await page.getByRole('button', { name: /Acciones \(1\)/ }).click();
    await page.getByText('Permisos', { exact: true }).last().click();

    const dialog = page.getByRole('dialog', { name: /Permisos de/ });
    await expect(dialog.getByTestId('role-menu-tree')).toBeVisible({ timeout: 15_000 });
    await expect(dialog.getByRole('tab', { name: 'Menús' })).toHaveAttribute(
      'aria-selected',
      'true',
    );

    await dialog.getByRole('tab', { name: 'Acciones' }).click();
    await expect(dialog.getByTestId('role-actions-tree')).toBeVisible();
    await expect(dialog.getByTestId('role-actions-count')).toHaveText(/^\d+ de \d+ permisos$/);

    // the tree's own search narrows the rows
    await dialog.getByRole('searchbox').fill('zzz-no-permission-matches');
    await expect(dialog.getByRole('row').filter({ has: dialog.getByRole('checkbox') })).toHaveCount(
      0,
    );
    await dialog.getByRole('searchbox').fill('');

    await dialog.getByRole('button', { name: 'Guardar' }).click();
    await expect.poll(() => Object.keys(saved).length, { timeout: 15_000 }).toBe(2);

    const permissions = (saved.permissions as { permissions: string[] }).permissions;
    expect(Array.isArray(permissions)).toBe(true);
    expect(
      permissions.every((key) => !String(key).startsWith('module:')),
      'module ids are never sent as permissions',
    ).toBe(true);
    const menus = (saved.menu as { permissions: number[] }).permissions;
    expect(menus.every((id) => Number.isInteger(id))).toBe(true);
  });
});
