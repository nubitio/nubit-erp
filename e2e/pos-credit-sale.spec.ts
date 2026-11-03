import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { customerReceivableBalance } from './helpers/receivables';
import { ensureOpenCashSession } from './helpers/cashbox';
import {
  openPosPayment,
  selectPosCustomer,
  selectPosPaymentMethod,
  waitForPosCheckoutReady,
} from './helpers/pos';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('Crédito en el POS crea automáticamente una cuenta por cobrar', async ({ page }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await ensureOpenCashSession(page, BASE_URL);
  const openingBalance = await customerReceivableBalance(page, E2E_SEED.customerName);

  await page.goto(`${BASE_URL}/#/pos`);
  await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25_000 });

  const product = page
    .getByTestId('pos-product-card')
    .filter({ hasText: E2E_SEED.productName })
    .filter({ hasNotText: E2E_SEED.product2Name })
    .first();
  await product.waitFor({ state: 'visible', timeout: 15_000 });
  const addButton = product.getByTestId('pos-product-card-add');
  if (await addButton.isVisible({ timeout: 2_000 })) {
    await addButton.click();
  } else {
    await product.click();
  }

  await selectPosPaymentMethod(page, /^Credito$/i);

  await expect(page.getByLabel('Venta a crédito')).toHaveCount(0);
  await expect(page.getByTestId('pos-credit-notice')).toContainText('Selecciona un cliente');
  await expect(page.getByTestId('pos-checkout-btn')).toBeDisabled();
  await page.getByRole('button', { name: 'Volver', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Cobrar venta' })).toBeHidden();
  // The dialog hands focus back to the charge button; wait so it cannot steal it from the customer field.
  await expect(page.getByTestId('pos-open-payment-btn')).toBeFocused();

  await selectPosCustomer(page, {
    name: E2E_SEED.customerName,
    search: E2E_SEED.customerName,
  });

  await openPosPayment(page);
  await expect(page.getByTestId('pos-credit-notice')).toContainText('Cuentas por cobrar');
  await expect(page.getByTestId('pos-checkout-btn')).toContainText('Registrar crédito');
  await waitForPosCheckoutReady(page);

  const responsePromise = page.waitForResponse(
    (response) =>
      /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
  );
  await page.getByTestId('pos-checkout-btn').click();
  const response = await responsePromise;
  const responseText = await response.text();
  expect(response.ok(), responseText).toBeTruthy();

  const requestBody = response.request().postDataJSON() as Record<string, unknown>;
  expect(requestBody.settlements).toEqual([
    {
      type: 'RECEIVABLE',
      amount: '10.00',
    },
  ]);
  expect(requestBody).not.toHaveProperty('payments');
  expect(requestBody).not.toHaveProperty('creditSale');

  const sale = JSON.parse(responseText) as {
    balanceDue?: string;
    grandTotal?: string;
    paidTotal?: string;
  };
  expect(Number(sale.paidTotal)).toBe(0);
  expect(Number(sale.balanceDue)).toBe(Number(sale.grandTotal));

  await page.goto(`${BASE_URL}/#/accounts-receivable`);
  const row = page.getByRole('row').filter({ hasText: E2E_SEED.customerName }).first();
  const [whole, cents] = (openingBalance + Number(sale.balanceDue)).toFixed(2).split('.');
  await expect(row.getByRole('cell').nth(1)).toContainText(new RegExp(`${whole}[.,]${cents}`), {
    timeout: 15_000,
  });
});
