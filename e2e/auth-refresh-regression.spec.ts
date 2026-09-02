import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test.describe('Regresión de rutas web bajo /api/v1', () => {
  test('PLE, Libro Diario, conciliación y suscripciones cargan sin un ciclo de refresh', async ({ page }) => {
    const seed = await getE2ESeed();
    const baseUrl = getE2EBaseUrl();
    await login(page, seed.username, seed.password);

    let refreshRequests = 0;
    let previewRequests = 0;
    let movementsRequests = 0;

    page.on('request', (request) => {
      const method = request.method();
      const url = request.url();
      if (method !== 'GET' && method !== 'POST') return;

      if (url.includes('/api/auth/refresh')) refreshRequests += 1;
      if (url.includes('/api/v1/ple/preview?')) previewRequests += 1;
      if (url.includes('/api/v1/bank-reconciliation/unreconciled-movements')) movementsRequests += 1;
    });

    await page.goto(`${baseUrl}/#/ple`);
    await expect(page.getByRole('heading', { name: 'SUNAT PLE — Libros electrónicos' })).toBeVisible();
    await expect(page.getByRole('tab', { name: /Diario \(/ })).toBeVisible();
    await page.waitForTimeout(1500);

    expect(refreshRequests).toBe(0);
    expect(previewRequests).toBeLessThanOrEqual(1);

    await page.getByRole('tab', { name: /Diario \(/ }).click();
    await expect(page.getByText(/Diario y Mayor salen de los asientos operativos/)).toBeVisible();

    await page.goto(`${baseUrl}/#/bank-reconciliation`);
    await expect(page.getByRole('heading', { name: 'Conciliación bancaria' })).toBeVisible();
    await page.waitForTimeout(1500);
    expect(movementsRequests).toBeLessThanOrEqual(1);
    expect(refreshRequests).toBe(0);

    await page.goto(`${baseUrl}/#/subscriptions/customer-subscriptions`);
    await expect(page.getByText('Suscripciones', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Error interno del servidor')).toHaveCount(0);
  });
});
