import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { parseApiCollection } from './helpers/api';
import { ensureOpenCashSession } from './helpers/cashbox';
import { loginAtSeedBranchViaApi } from './helpers/fiscal';
import { selectPosCustomer, selectPosPaymentMethod, waitForPosCheckoutReady } from './helpers/pos';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('POS redondea efectivo S/10.63 a S/10.60 y persiste el ajuste', async ({ page, request }) => {
  const seed = await getE2ESeed();
  const auth = await loginAtSeedBranchViaApi(request, BASE_URL, seed.username, seed.password);
  const headers = { ...auth.headers, 'Content-Type': 'application/merge-patch+json' };
  const productsResponse = await request.get(
    `${BASE_URL}/api/products?code=${encodeURIComponent(E2E_SEED.productCode)}`,
    { headers: auth.headers },
  );
  const product = parseApiCollection<{ id: string; code?: string }>(
    await productsResponse.json(),
  ).find((item) => item.code === E2E_SEED.productCode);
  expect(product).toBeTruthy();

  try {
    expect(
      (
        await request.patch(`${BASE_URL}/api/tenant/settings`, {
          headers,
          data: { cashRoundingEnabled: true },
        })
      ).ok(),
    ).toBeTruthy();
    expect(
      (
        await request.patch(`${BASE_URL}/api/products/${product?.id}`, {
          headers,
          data: { salePrice: '10.63' },
        })
      ).ok(),
    ).toBeTruthy();

    await login(page, seed.username, seed.password);
    await ensureOpenCashSession(page, BASE_URL);
    await page.goto(`${BASE_URL}/pos`);
    const search = page.getByTestId('pos-product-search');
    await search.waitFor({ state: 'visible', timeout: 25000 });
    await search.fill(E2E_SEED.productName);
    const card = page
      .getByTestId('pos-product-card')
      .filter({ hasText: E2E_SEED.productName })
      .filter({ hasNotText: E2E_SEED.product2Name })
      .first();
    await card.waitFor({ state: 'visible', timeout: 15000 });
    const add = card.getByTestId('pos-product-card-add');
    if (await add.isVisible({ timeout: 2000 }).catch(() => false)) await add.click();
    else await card.click();
    await selectPosCustomer(page, { name: E2E_SEED.customerName });
    const paymentDialog = await selectPosPaymentMethod(page, /efectivo/i);
    await expect(page.getByTestId('pos-cash-rounding')).toContainText('0.03');
    await expect(page.getByTestId('pos-checkout-btn')).toContainText('10.60');
    await expect(paymentDialog.getByText('S/ 10.60').first()).toBeVisible();
    await paymentDialog.getByRole('button', { name: 'S/ 20.00' }).click();
    await expect(paymentDialog.getByText('Vuelto S/ 9.40')).toBeVisible();
    await waitForPosCheckoutReady(page);

    const saleResponsePromise = page.waitForResponse(
      (response) =>
        /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
    );
    await page.getByTestId('pos-checkout-btn').click();
    const saleResponse = await saleResponsePromise;
    expect(saleResponse.ok(), await saleResponse.text()).toBeTruthy();
    const sale = (await saleResponse.json()) as {
      grandTotal?: string;
      paidTotal?: string;
      cashRoundingAdjustment?: string;
      taxDocumentId?: string;
    };
    expect(sale).toMatchObject({
      grandTotal: '10.60',
      paidTotal: '10.60',
      cashRoundingAdjustment: '0.03',
    });
    expect(sale.taxDocumentId).toBeTruthy();
    const ticket = await request.get(`${BASE_URL}/receipt/${sale.taxDocumentId}/ticket?preview=1`);
    expect(ticket.ok()).toBeTruthy();
    const ticketHtml = await ticket.text();
    expect(ticketHtml).toContain('Redondeo en efectivo');
    expect(ticketHtml).toContain('S/ 10.60');
  } finally {
    await request.patch(`${BASE_URL}/api/tenant/settings`, {
      headers,
      data: { cashRoundingEnabled: false },
    });
    await request.patch(`${BASE_URL}/api/products/${product?.id}`, {
      headers,
      data: { salePrice: E2E_SEED.productPrice },
    });
  }
});
