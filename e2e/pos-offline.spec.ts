import { readFile, writeFile } from 'node:fs/promises';
import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import { completePosCashPayment, waitForPosCheckoutReady } from './helpers/pos';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import { E2E_SEED } from './support/seedConstants';
import updatePolicy from '../config/app-update.json' with { type: 'json' };

const BASE_URL = getE2EBaseUrl();

test.use({
  serviceWorkers: 'allow',
  // The isolated Docker stack is HTTP on a private IP. Enable secure-context
  // browser APIs only for this test origin, as HTTPS would do in production.
  launchOptions: {
    args: [
      '--no-sandbox',
      `--unsafely-treat-insecure-origin-as-secure=${new URL(BASE_URL).origin}`,
    ],
  },
});

async function readOfflineState(page: Page) {
  return page.evaluate(async () => {
    const databases = await indexedDB.databases();
    if (!databases.some((db) => db.name === 'efact_offline_db')) return null;
    return new Promise<{
      sales: {
        id: string;
        status: string;
        idempotencyKey: string;
        serverSaleId?: string;
        items: unknown[];
        payments: unknown[];
        totals: Record<string, number>;
      }[];
      outbox: { saleId: string; status: string; request: { headers: Record<string, string> } }[];
      products: unknown[];
    }>((resolve, reject) => {
      const open = indexedDB.open('efact_offline_db');
      const timeout = window.setTimeout(
        () => reject(new Error('Offline database read timed out')),
        5000,
      );
      open.onblocked = () => {
        clearTimeout(timeout);
        reject(new Error('Offline database open blocked'));
      };
      open.onerror = () => {
        clearTimeout(timeout);
        reject(open.error);
      };
      open.onsuccess = () => {
        const db = open.result;
        const tx = db.transaction(['sales', 'outbox', 'products'], 'readonly');
        const sales = tx.objectStore('sales').getAll();
        const outbox = tx.objectStore('outbox').getAll();
        const products = tx.objectStore('products').getAll();
        tx.oncomplete = () => {
          clearTimeout(timeout);
          db.close();
          resolve({ sales: sales.result, outbox: outbox.result, products: products.result });
        };
        tx.onerror = () => {
          clearTimeout(timeout);
          db.close();
          reject(tx.error);
        };
      };
    });
  });
}

test('Una venta offline sobrevive a recarga y actualización PWA y se sincroniza sin duplicados', async ({
  page,
  context,
}) => {
  test.setTimeout(180_000);
  const seed = await getE2ESeed();
  const swPath = 'public/sw.js';
  const originalWorker = await readFile(swPath, 'utf8');
  const errors: string[] = [];
  const attempts: { key: string; serverId: string }[] = [];
  page.on('pageerror', (error) => errors.push(error.message));

  try {
    await page.goto(`${BASE_URL}/#/login`);
    // waitForFunction survives the first controllerchange-triggered reload.
    await page.waitForFunction(() => !!navigator.serviceWorker?.controller, undefined, {
      timeout: 30_000,
    });
    await login(page, seed.username, seed.password);
    await ensureOpenCashSession(page, BASE_URL);
    await page.goto(`${BASE_URL}/#/pos`);
    await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25_000 });
    // Fail clearly when someone runs this spec against Vite dev assets: the
    // actual production worker and its caches are part of the regression.
    await expect
      .poll(() => page.evaluate(() => !!navigator.serviceWorker?.controller), { timeout: 30_000 })
      .toBe(true);
    await expect
      .poll(async () => (await readOfflineState(page))?.products.length ?? 0)
      .toBeGreaterThan(0);
    await expect
      .poll(() =>
        page.evaluate(
          async () =>
            !!(await (await caches.open('app-shell')).match(new URL('/', window.location.origin))),
        ),
      )
      .toBe(true);

    await context.setOffline(true);
    await expect(page.getByLabel('Sin conexión', { exact: true })).toBeVisible();
    const product = page
      .getByTestId('pos-product-card')
      .filter({ hasText: E2E_SEED.productName })
      .filter({ hasNotText: E2E_SEED.product2Name })
      .first();
    await product.click();
    await completePosCashPayment(page);
    await waitForPosCheckoutReady(page);
    await page.getByTestId('pos-checkout-btn').click();
    await expect(page.getByRole('button', { name: /Ventas pendientes: 1/ })).toBeVisible();

    const queued = await readOfflineState(page);
    expect(queued?.sales).toHaveLength(1);
    expect(queued?.outbox).toHaveLength(1);
    const sale = queued!.sales[0];
    expect(sale.status).toBe('pending');
    expect(queued!.outbox[0].request.headers['Idempotency-Key']).toBe(sale.idempotencyKey);

    await page.reload({ waitUntil: 'domcontentloaded' });
    // The real PWA cache plus session-scoped metadata must reopen a usable POS.
    await expect(page.locator('#root')).toBeAttached();
    await expect(page.getByRole('button', { name: /Ventas pendientes: 1/ })).toBeVisible({
      timeout: 30_000,
    });
    await expect(page.getByTestId('pos-product-search')).toBeVisible();
    await expect(page.getByLabel('Sin conexión', { exact: true })).toBeVisible();
    await expect
      .poll(async () => (await readOfflineState(page))?.sales[0])
      .toMatchObject({
        id: sale.id,
        status: 'pending',
        idempotencyKey: sale.idempotencyKey,
        items: sale.items,
        payments: sale.payments,
        totals: sale.totals,
      });
    // This must be more than a readable pending queue: keep selling offline
    // after the reload with the restored payment methods/warehouse/schema.
    await product.click();
    await completePosCashPayment(page);
    await waitForPosCheckoutReady(page);
    await page.getByTestId('pos-checkout-btn').click();
    await expect(page.getByRole('button', { name: /Ventas pendientes: 2/ })).toBeVisible();
    const secondSale = (await readOfflineState(page))!.sales.find((row) => row.id !== sale.id)!;
    expect(secondSale.idempotencyKey).not.toBe(sale.idempotencyKey);

    // Internet returns while the API is unavailable. The worker can update,
    // but the pending sale must remain durable until the backend recovers.
    await context.route('**/health/live', (route) =>
      route.fulfill({ status: 503, body: 'offline' }),
    );
    await context.route('**/api/sales', (route) => {
      if (route.request().method() === 'POST') return route.abort('failed');
      return route.continue();
    });
    await context.setOffline(false);
    await expect(page.getByLabel('Sin conexión', { exact: true })).toBeVisible();
    await writeFile(swPath, `${originalWorker}\n// E2E worker revision ${Date.now()}\n`);
    await page.evaluate(async () => {
      const registration = await navigator.serviceWorker.ready;
      await registration.update();
    });
    const updateButton = page.getByRole('button', { name: 'Actualizar ahora' });
    await expect(updateButton).toBeVisible({ timeout: 30_000 });
    // The old worker remains active and the POS is usable until acceptance.
    await expect(page.getByRole('button', { name: /Ventas pendientes: 2/ })).toBeVisible();
    expect(await page.evaluate(async () => !!(await navigator.serviceWorker.ready).waiting)).toBe(
      true,
    );
    const takeover = page.waitForEvent('framenavigated', {
      predicate: (frame) => frame === page.mainFrame(),
      timeout: 30_000,
    });
    await updateButton.click();
    await takeover;
    await expect(page.getByRole('button', { name: /Ventas pendientes: 2/ })).toBeVisible({
      timeout: 30_000,
    });
    expect((await readOfflineState(page))?.sales.find((row) => row.id === sale.id)).toMatchObject({
      id: sale.id,
      idempotencyKey: sale.idempotencyKey,
      status: 'pending',
    });

    // The backend accepts the first retry, but the response is lost. The next
    // attempt must reuse the key and return that same sale, not create another.
    await context.unroute('**/health/live');
    await context.unroute('**/api/sales');
    await context.route('**/api/sales', async (route) => {
      if (route.request().method() !== 'POST') return route.continue();
      const response = await route.fetch();
      expect(response.ok(), await response.text()).toBe(true);
      const created = (await response.json()) as { id: string };
      attempts.push({
        key: route.request().headers()['idempotency-key'],
        serverId: String(created.id),
      });
      if (attempts.length === 1) return route.abort('failed');
      return route.fulfill({ response });
    });
    await context.setOffline(true);
    await context.setOffline(false);
    await expect
      .poll(
        async () => ({
          status: (await readOfflineState(page))?.sales.every((row) => row.status === 'synced'),
          attempts: attempts.length,
        }),
        // API failures during takeover increase the real retry backoff to
        // 30s/60s. Include that delay and the 10s queue polling interval.
        { timeout: 90_000 },
      )
      .toEqual({ status: true, attempts: 3 });
    const retried = attempts.filter((attempt) => attempt.key === attempts[0].key);
    expect(retried).toHaveLength(2);
    expect(retried[1]).toEqual(retried[0]);
    expect(new Set(attempts.map((attempt) => attempt.key))).toEqual(
      new Set([sale.idempotencyKey, secondSale.idempotencyKey]),
    );
    const synced = await readOfflineState(page);
    expect(synced?.sales).toHaveLength(2);
    for (const localSale of synced!.sales) {
      expect(localSale.serverSaleId).toBe(
        attempts.find((attempt) => attempt.key === localSale.idempotencyKey)?.serverId,
      );
    }
    expect(synced?.outbox.every((item) => item.status === 'done')).toBe(true);
    await expect(page.getByRole('button', { name: /Ventas pendientes: 0/ })).toBeVisible();
    expect(errors).toEqual([]);
  } finally {
    const statePath = test.info().outputPath('offline-state.json');
    await writeFile(
      statePath,
      JSON.stringify(
        { state: await readOfflineState(page).catch(() => null), attempts, errors },
        null,
        2,
      ),
    );
    await test.info().attach('offline-state', {
      path: statePath,
      contentType: 'application/json',
    });
    await context.setOffline(false);
    await writeFile(swPath, originalWorker);
  }
});

test('La actualización espera al cobro en curso y una versión obligatoria bloquea nuevas ventas', async ({
  page,
  context,
}) => {
  const seed = await getE2ESeed();
  let finishPayment = () => {};
  try {
    await page.goto(`${BASE_URL}/#/login`);
    await page.waitForFunction(() => !!navigator.serviceWorker?.controller);
    await login(page, seed.username, seed.password);
    await ensureOpenCashSession(page, BASE_URL);
    await page.goto(`${BASE_URL}/#/pos`);
    const product = page
      .getByTestId('pos-product-card')
      .filter({ hasText: E2E_SEED.productName })
      .filter({ hasNotText: E2E_SEED.product2Name })
      .first();
    await product.click();
    await completePosCashPayment(page);
    await waitForPosCheckoutReady(page);

    const versionResponse = await page.request.get(`${BASE_URL}/health/app-version`);
    expect(versionResponse.ok()).toBe(true);
    expect(versionResponse.headers()['cache-control']).toContain('no-store');
    const authHeaders = await page.evaluate(() => {
      const token = JSON.parse(localStorage.getItem('auth_token') ?? 'null');
      const csrf = document.cookie.split('; ').find((cookie) => cookie.startsWith('CSRF_TOKEN='));
      const headers: Record<string, string> = {};
      if (token) headers.Authorization = `Bearer ${token}`;
      if (csrf) headers['X-CSRF-Token'] = decodeURIComponent(csrf.slice('CSRF_TOKEN='.length));
      return headers;
    });
    const cachedProduct = (await readOfflineState(page))!.products[0] as {
      id: string;
      name: string;
    };
    const rejected = await page.request.patch(`${BASE_URL}/api/products/${cachedProduct.id}`, {
      headers: {
        ...authHeaders,
        'X-App-Contract-Version': '0',
        'Content-Type': 'application/merge-patch+json',
      },
      data: { name: 'Obsolete client must not change this product' },
    });
    expect(rejected.status()).toBe(426);
    expect((await rejected.json()).errorCode).toBe('APP_UPDATE_REQUIRED');
    const unchanged = await page.request.get(`${BASE_URL}/api/products/${cachedProduct.id}`, {
      headers: authHeaders,
    });
    expect((await unchanged.json()).name).toBe(cachedProduct.name);
    await expect.poll(() => page.evaluate(() => !!navigator.serviceWorker.controller)).toBe(true);
    // The version endpoint also announces deployments before the new worker
    // finishes downloading. Actual worker acceptance is covered above.
    await context.route('**/health/app-version', (route) =>
      route.fulfill({
        json: {
          revision: 'app-next.js',
          offlineContractVersion: updatePolicy.offlineContractVersion,
          minimumOfflineContractVersion: updatePolicy.offlineContractVersion,
        },
      }),
    );
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    const updateButton = page.getByRole('button', { name: 'Actualizar ahora' });
    await expect(updateButton).toBeVisible({ timeout: 30_000 });

    const paymentGate = new Promise<void>((resolve) => {
      finishPayment = resolve;
    });
    let submissions = 0;
    await context.route('**/api/sales', async (route) => {
      if (route.request().method() !== 'POST') return route.continue();
      submissions += 1;
      await paymentGate;
      const response = await route.fetch();
      await route.fulfill({ response });
    });
    await page.getByTestId('pos-checkout-btn').click();
    await expect.poll(() => submissions).toBe(1);
    await expect(updateButton).toBeDisabled();

    await context.route('**/health/app-version', (route) =>
      route.fulfill({
        json: {
          revision: 'app-next.js',
          offlineContractVersion: updatePolicy.offlineContractVersion + 1,
          minimumOfflineContractVersion: updatePolicy.offlineContractVersion + 1,
        },
      }),
    );
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await expect(page.getByText('Actualización necesaria', { exact: true })).toBeVisible();
    await expect(updateButton).toBeDisabled();
    finishPayment();
    await expect(updateButton).toBeEnabled({ timeout: 20_000 });
    await page.getByTestId('pos-postsale-new-sale').click();
    await expect(page.getByTestId('pos-checkout-btn')).toBeDisabled();
    await product.click();
    await completePosCashPayment(page);
    await expect(page.getByTestId('pos-checkout-btn')).toBeDisabled();
    expect(submissions).toBe(1);
  } finally {
    finishPayment();
  }
});
