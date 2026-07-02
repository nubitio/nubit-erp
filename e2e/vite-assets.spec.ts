import { expect, test } from '@playwright/test';

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://demo.localhost:8080';
const VITE_ORIGIN = process.env.VITE_DEV_SERVER_ORIGIN ?? 'http://localhost:5174';

test.describe('Vite dev assets', () => {
  test.skip(!!process.env.CI, 'CI uses prebuilt assets instead of the Vite dev server.');

  test('Symfony shell loads Vite client and app entry from the efact dev server', async ({
    page,
    request,
  }) => {
    const viteClient = await request.get(`${VITE_ORIGIN}/build/@vite/client`);
    expect(viteClient.status(), 'vite client status').toBe(200);
    expect(viteClient.headers()['content-type'] ?? '').toContain('javascript');

    const reactRefresh = await request.get(`${VITE_ORIGIN}/build/@react-refresh`);
    expect(reactRefresh.status(), 'react-refresh status').toBe(200);

    const failedScripts: string[] = [];
    page.on('response', (response) => {
      const url = response.url();
      if (
        url.includes('localhost:5173') ||
        url.includes('localhost:5174') ||
        url.includes('/build/@vite/') ||
        url.includes('/build/assets/app')
      ) {
        if (response.status() >= 400) {
          failedScripts.push(`${response.status()} ${url}`);
        }
      }
    });

    await page.goto(`${BASE_URL}/#/login`);
    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 20_000 });
    expect(failedScripts, 'vite asset responses').toEqual([]);
  });
});
