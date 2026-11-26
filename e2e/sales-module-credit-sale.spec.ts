import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { customerReceivableBalance } from './helpers/receivables';
import { dismissDevToolbar, waitForGridLoad } from './helpers/crud';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('Crédito en el módulo Ventas crea una cuenta por cobrar con el contrato canónico', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  const openingBalance = await customerReceivableBalance(page, E2E_SEED.customerName);

  await page.goto(`${BASE_URL}/billing/sales`);
  await dismissDevToolbar(page);
  await waitForGridLoad(page);
  await page.getByRole('button', { name: 'Nueva venta' }).click();
  await expect(page).toHaveURL(/\/billing\/sales\/new$/);

  const customerInput = page.getByPlaceholder('Busca por nombre o RUC');
  await customerInput.fill(E2E_SEED.customerName);
  await page.getByRole('option').filter({ hasText: E2E_SEED.customerName }).first().click();
  const productInput = page.getByPlaceholder('Selecciona un producto o servicio');
  await productInput.fill(E2E_SEED.productName);
  await page.getByRole('option').filter({ hasText: E2E_SEED.productName }).first().click();
  await expect(page.getByLabel('Precio de la línea 1')).toHaveValue(/^10(?:\.0+)?$/);

  const responsePromise = page.waitForResponse(
    (response) =>
      /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
  );
  await page.getByTestId('new-invoice-save-panel').click();
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
  expect(requestBody).not.toHaveProperty('customerDocument');

  const sale = JSON.parse(responseText) as {
    id?: string;
    balanceDue?: string;
    grandTotal?: string;
    paidTotal?: string;
  };
  expect(Number(sale.paidTotal)).toBe(0);
  expect(Number(sale.balanceDue)).toBe(Number(sale.grandTotal));
  await expect(page).toHaveURL(new RegExp(`/billing/sales/${sale.id}$`), { timeout: 15000 });

  await page.goto(`${BASE_URL}/accounts-receivable`);
  const receivable = page.getByRole('row').filter({ hasText: E2E_SEED.customerName }).first();
  const [whole, cents] = (openingBalance + Number(sale.balanceDue)).toFixed(2).split('.');
  await expect(receivable.getByRole('cell').nth(1)).toContainText(
    new RegExp(`${whole}[.,]${cents}`),
    { timeout: 15_000 },
  );
});
