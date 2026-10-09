import { expect, type Page } from '@playwright/test';
import { getE2EBaseUrl } from '../support/e2eSeed';
import { login } from './auth';

const BASE_URL = getE2EBaseUrl();

/** Navigate to a hash route, re-authenticating if the session expired. */
export async function gotoProtectedRoute(
  page: Page,
  hashPath: string,
  credentials: { username: string; password: string },
) {
  const targetHash = `/#/${hashPath}`;
  const escaped = targetHash.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const navigate = async () => {
    await page.goto(`${BASE_URL}${targetHash}`);
    await page.waitForLoadState('domcontentloaded', { timeout: 10000 }).catch(() => {});
  };

  await navigate();

  const loginForm = page.locator('form.login-form');
  const onLogin =
    page.url().includes('/#/login') ||
    page.url().includes('#/login') ||
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
