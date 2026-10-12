import { expect, test, type BrowserContext } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();
test.use({
  launchOptions: {
    args: [
      '--no-sandbox',
      `--unsafely-treat-insecure-origin-as-secure=${new URL(BASE_URL).origin}`,
    ],
  },
});

async function invalidateAccessCookie(context: BrowserContext) {
  const cookie = (await context.cookies()).find((item) => item.name === 'AUTH_TOKEN');
  expect(cookie, 'el login debe emitir la cookie de acceso').toBeDefined();
  await context.addCookies([{ ...cookie!, value: 'expired-access-audit-marker' }]);
}

test('Un refresh real recupera peticiones concurrentes en dos pestañas sin perder la sesión', async ({
  page,
  context,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await page.goto(`${BASE_URL}/#/pos`);
  await expect(page.getByTestId('pos-product-search')).toBeVisible();
  await page.waitForLoadState('networkidle');
  const second = await context.newPage();
  await second.goto(`${BASE_URL}/#/pos`);
  await expect(second.getByTestId('pos-product-search')).toBeVisible();
  await second.waitForLoadState('networkidle');

  // Normal JWT expiry removes the access cookie but retains refresh/CSRF.
  await context.clearCookies({ name: 'AUTH_TOKEN' });
  // A fabricated/stale Bearer must not turn cookie-based refresh into a CSRF exemption.
  const forbidden = await page.request.post(`${BASE_URL}/api/auth/refresh`, {
    headers: { Authorization: 'Bearer csrf-audit-marker' },
  });
  expect(forbidden.status()).toBe(403);
  let renewals = 0;
  for (const tab of [page, second])
    tab.on('request', (request) => {
      if (request.url().endsWith('/api/auth/refresh')) renewals += 1;
    });
  const results = await Promise.all(
    [page, second].map((tab) =>
      tab.evaluate(async () => {
        return Promise.all(
          [
            '/api/products?itemsPerPage=2',
            '/api/warehouses?pagination=false',
            '/api/tenant/runtime-config',
          ].map(async (url) => (await fetch(url, { credentials: 'include' })).status),
        );
      }),
    ),
  );
  expect(results.flat()).toEqual([200, 200, 200, 200, 200, 200]);
  expect(renewals).toBe(1);
  await expect(page.getByRole('dialog', { name: 'Sesión expirada' })).toHaveCount(0);
  expect(
    await page.evaluate(() => JSON.parse(localStorage.getItem('user') ?? 'null')?.username),
  ).toBe(seed.username);
});

test('Un 503 de refresh conserva la sesión; un refresh inválido notifica una sola vez', async ({
  page,
  context,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await page.goto(`${BASE_URL}/#/pos`);
  await expect(page.getByTestId('pos-product-search')).toBeVisible();
  await page.waitForLoadState('networkidle');
  let renewals = 0;
  await context.route('**/api/auth/refresh', (route) => {
    renewals += 1;
    return route.fulfill({ status: 503, json: { message: 'Temporary maintenance' } });
  });
  await invalidateAccessCookie(context);
  const temporary = await page.evaluate(async () =>
    Promise.allSettled([
      fetch('/api/products'),
      fetch('/api/warehouses'),
      fetch('/api/tenant/runtime-config'),
    ]).then((results) =>
      results.map((result) =>
        result.status === 'rejected' ? result.reason.status : result.value.status,
      ),
    ),
  );
  expect(temporary).toEqual([503, 503, 503]);
  expect(renewals).toBe(1);
  expect(
    await page.evaluate(() => JSON.parse(localStorage.getItem('user') ?? 'null')?.username),
  ).toBe(seed.username);
  await expect(page.getByRole('dialog', { name: 'Sesión expirada' })).toHaveCount(0);
  // A definitive rejection must expire once; respect transient-failure backoff.
  await page.waitForTimeout(5100);
  await context.unroute('**/api/auth/refresh');
  await context.route('**/api/auth/refresh', (route) => {
    renewals += 1;
    return route.fulfill({ status: 401, json: { message: 'Invalid refresh credential' } });
  });
  await page.evaluate(() => {
    (window as unknown as { expiryNotices: number }).expiryNotices = 0;
    window.addEventListener('efact:session-expired', () => {
      (window as unknown as { expiryNotices: number }).expiryNotices += 1;
    });
  });
  const invalid = await page.evaluate(async () =>
    Promise.all([
      fetch('/api/products'),
      fetch('/api/warehouses'),
      fetch('/api/tenant/runtime-config'),
    ]).then((responses) => responses.map((response) => response.status)),
  );
  expect(invalid).toEqual([401, 401, 401]);
  expect(renewals).toBe(2);
  expect(
    await page.evaluate(() => (window as unknown as { expiryNotices: number }).expiryNotices),
  ).toBe(1);
  await expect(page.getByRole('dialog', { name: 'Sesión expirada' })).toBeVisible();
  await page.getByRole('button', { name: 'Ir al login' }).click();
  await expect(page.locator('form.login-form')).toBeVisible();
});
