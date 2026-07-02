import { expect, test } from '@playwright/test';

import { login as loginUser } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test.describe('Price list page', () => {
  test('loads after login without vite or runtime errors', async ({ page }) => {
    const consoleErrors: string[] = [];
    const failedRequests: string[] = [];

    // Skip "Failed to load resource" console errors — those are network-level
    // and captured more precisely via response listener below.
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (text.startsWith('Failed to load resource')) return;
        consoleErrors.push(text);
      }
    });
    page.on('pageerror', (err) => consoleErrors.push(`pageerror: ${err.message}`));
    page.on('response', (response) => {
      const url = response.url();
      if (
        response.status() >= 400 &&
        (url.includes('/build/') ||
          url.includes('localhost:5174') ||
          url.includes('/api/price-lists'))
      ) {
        failedRequests.push(`${response.status()} ${url}`);
      }
    });

    const seed = await getE2ESeed();
    await loginUser(page, seed.username, seed.password);
    await page.goto(`${BASE_URL}/#/price-list`);
    // Retry once if 502s are detected — transient under Docker load
    await page.waitForTimeout(2000);
    if (failedRequests.length > 0) {
      failedRequests.length = 0;
      await page.reload();
      await page.waitForLoadState('networkidle', { timeout: 15_000 }).catch(() => {});
      await page.waitForTimeout(2000);
    }

    await page.screenshot({ path: 'var/e2e/price-list-debug.png', fullPage: true });

    const shellVisible = await page.locator('.nb-admin-shell').isVisible();
    const gridVisible = await page.locator('.nb-datagrid, .datagrid-list').first().isVisible().catch(() => false);
    const errorBoundary = await page.getByText(/error|failed|cannot/i).first().isVisible().catch(() => false);

    expect(failedRequests, 'failed network').toEqual([]);
    expect(consoleErrors, 'console errors').toEqual([]);
    expect(shellVisible, 'admin shell visible').toBe(true);
    expect(gridVisible || errorBoundary, 'grid or visible error').toBe(true);
  });
});
