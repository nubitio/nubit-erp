import { chromium } from '@playwright/test';
import { getE2EBaseUrl } from './support/e2eSeed';

/**
 * Pay the SPA cold start once, outside the first spec.
 * golden-path-fiscal was losing 25s on pos-product-search because /health/live
 * is green long before Chromium has downloaded and executed the app bundle.
 */
async function globalSetup(): Promise<void> {
  const baseUrl = getE2EBaseUrl();
  const browser = await chromium.launch({
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage({ locale: 'es-PE' });

  try {
    await page.goto(`${baseUrl}/#/login`, {
      waitUntil: 'domcontentloaded',
      timeout: 60_000,
    });
    await page.locator('form.login-form').waitFor({ state: 'visible', timeout: 60_000 });
    await page
      .goto(`${baseUrl}/#/pos`, {
        waitUntil: 'domcontentloaded',
        timeout: 30_000,
      })
      .catch(() => undefined);
  } finally {
    await browser.close();
  }
}

export default globalSetup;
