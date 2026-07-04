import { expect, test, type Page } from '@playwright/test';

const CP_USER = process.env.CP_ADMIN_USER ?? 'admin';
const CP_PASSWORD = process.env.CP_ADMIN_PASSWORD ?? 'Admin.Control.123';

async function hideDebugToolbar(page: Page) {
  const toolbar = page.locator('.sf-toolbar');
  if (await toolbar.count()) {
    await toolbar.evaluate((el) => {
      el.style.display = 'none';
    });
  }
}

async function loginControlPlane(page: Page) {
  await page.goto('/admin/login');
  await page.locator('#username').fill(CP_USER);
  await page.locator('#password').fill(CP_PASSWORD);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/admin\/?$/, { timeout: 15_000 });
  await expect(page.locator('.nb-admin-shell')).toBeVisible({ timeout: 15_000 });
}

async function expectShellScreenshot(page: Page, name: string) {
  await hideDebugToolbar(page);
  await expect(page.locator('.nb-admin-shell')).toHaveScreenshot(name, {
    animations: 'disabled',
    maxDiffPixelRatio: 0.02,
  });
}

test.describe('Control Plane — login', () => {
  test('login page alineada con tokens del SPA', async ({ page }) => {
    await page.goto('/admin/login');
    await hideDebugToolbar(page);
    await expect(page.locator('.cp-login__card')).toBeVisible();
    await expect(page).toHaveScreenshot('control-plane-login.png', {
      animations: 'disabled',
      maxDiffPixelRatio: 0.02,
    });
  });
});

test.describe('Control Plane — regresión visual', () => {
  test.beforeEach(async ({ page }) => {
    await loginControlPlane(page);
  });

  test('rutas principales del shell', async ({ page }) => {
    const routes: Array<{ path: string; shot: string; ready?: RegExp | string }> = [
      { path: '/admin', shot: 'control-plane-dashboard.png', ready: /Panel|Dashboard/i },
      { path: '/admin/tenants', shot: 'control-plane-tenants.png', ready: /Tenants/i },
      { path: '/admin/audit-log', shot: 'control-plane-audit.png', ready: /Auditoría|Audit/i },
      { path: '/admin/users', shot: 'control-plane-users.png', ready: /Usuarios|Users/i },
    ];

    for (const route of routes) {
      await page.goto(route.path);
      if (route.ready) {
        await expect(page.getByRole('heading', { name: route.ready })).toBeVisible({ timeout: 15_000 });
      }
      await expectShellScreenshot(page, route.shot);
    }
  });

  test('detalle de tenant con tabs', async ({ page }) => {
    await page.goto('/admin/tenants');
    await expect(page.getByRole('heading', { name: /Tenants/i })).toBeVisible({ timeout: 15_000 });

    await page.getByRole('button', { name: /Acciones/i }).first().click();
    await page.getByRole('menuitem', { name: /Gestionar|Manage/i }).click();

    await expect(page.getByRole('tab', { name: /General/i })).toBeVisible({ timeout: 15_000 });
    await expectShellScreenshot(page, 'control-plane-tenant-detail.png');
  });
});