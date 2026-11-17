import { expect, type Page } from '@playwright/test';
import { getE2EBaseUrl } from '../support/e2eSeed';
import { login } from './auth';

const BASE_URL = getE2EBaseUrl();

/** Navigate to an app route, re-authenticating if the session expired. */
export async function gotoProtectedRoute(
  page: Page,
  routePath: string,
  credentials: { username: string; password: string },
) {
  const targetPath = `/${routePath}`;
  const escaped = targetPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const navigate = async () => {
    await page.goto(`${BASE_URL}${targetPath}`);
    await page.waitForLoadState('domcontentloaded', { timeout: 10000 }).catch(() => {});
  };

  await navigate();

  const loginForm = page.locator('form.login-form');
  const onLogin =
    new URL(page.url()).pathname === '/login' ||
    (await loginForm.isVisible({ timeout: 2000 }).catch(() => false));

  if (onLogin) {
    await login(page, credentials.username, credentials.password);
    await navigate();
  }

  try {
    await page.waitForURL(new RegExp(escaped), { timeout: 15000 });
  } catch {
    await login(page, credentials.username, credentials.password);
    await navigate();
    await page.waitForURL(new RegExp(escaped), { timeout: 15000 });
  }

  await expect(loginForm)
    .toBeHidden({ timeout: 5000 })
    .catch(() => {});
}
