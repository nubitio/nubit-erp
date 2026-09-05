import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import {
  clickCrudSave,
  clickGridToolbarButton,
  crudShell,
  crudShellBody,
  dismissDevToolbar,
  waitForGridLoad,
  waitForSaleFormReady,
} from './helpers/crud';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test('Crédito en el módulo Ventas crea una cuenta por cobrar con el contrato canónico', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);

  await page.goto(`${BASE_URL}/#/billing/sales`);
  await dismissDevToolbar(page);
  await waitForGridLoad(page);
  await clickGridToolbarButton(page, 'Nuevo');

  const shell = crudShell(page, /nuevo|new/i);
  const body = crudShellBody(page);
  await waitForSaleFormReady(body);

  await page.locator('button[aria-label="Agregar detalle"]').first().click();
  const detailTable = shell.locator('.nb-form__detail-table').first();
  const row = detailTable.locator('tbody tr').last();
  await row.waitFor({ state: 'visible', timeout: 8_000 });

  const productCell = row.locator('td').nth(0);
  await productCell.click();
  const productInput = productCell.locator('input[role="combobox"], input').first();
  await productInput.click();
  await productInput.pressSequentially(E2E_SEED.productName, { delay: 30 });
  await page
    .locator('.nb-form__lookup-menu button[role="option"]')
    .filter({ hasText: E2E_SEED.productName })
    .first()
    .click();
  await expect(row.locator('td').nth(2).locator('input').first()).not.toHaveValue('0', {
    timeout: 10_000,
  });

  await page
    .locator('label.split-payments-field__method')
    .filter({ hasText: /^Credito$/i })
    .click();

  await expect(page.getByLabel('Venta a crédito')).toHaveCount(0);
  await expect(page.getByTestId('sales-credit-notice')).toContainText(
    'Selecciona un cliente identificado',
  );

  let createRequests = 0;
  page.on('request', (request) => {
    if (/\/api\/sales(?:\?.*)?$/.test(request.url()) && request.method() === 'POST') {
      createRequests += 1;
    }
  });
  await clickCrudSave(shell);
  await expect(body.locator('.nb-form__error')).toContainText(
    'Selecciona un cliente identificado para registrar una cuenta por cobrar.',
  );
  expect(createRequests).toBe(0);

  const customerInput = body.getByLabel(/Cliente/i).first();
  await customerInput.fill(E2E_SEED.customerName);
  await page
    .locator('.nb-form__lookup-menu button[role="option"]')
    .filter({ hasText: E2E_SEED.customerName })
    .first()
    .click();
  await expect(page.getByTestId('sales-credit-notice')).toContainText('Cuentas por cobrar');

  const responsePromise = page.waitForResponse(
    (response) =>
      /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
  );
  await clickCrudSave(shell);
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
    balanceDue?: string;
    grandTotal?: string;
    paidTotal?: string;
  };
  expect(Number(sale.paidTotal)).toBe(0);
  expect(Number(sale.balanceDue)).toBe(Number(sale.grandTotal));

  await expect(shell).toBeHidden({ timeout: 15_000 });
  await waitForGridLoad(page);
  const saleRow = page.getByRole('row').filter({ hasText: E2E_SEED.customerName }).first();
  await expect(saleRow).toContainText(/Cr[eé]dito/, { timeout: 15_000 });

  await page.goto(`${BASE_URL}/#/accounts-receivable`);
  const receivable = page.getByRole('row').filter({ hasText: E2E_SEED.customerName }).first();
  const [whole, cents] = Number(sale.balanceDue).toFixed(2).split('.');
  await expect(receivable).toContainText(new RegExp(`${whole}[.,]${cents}`), { timeout: 15_000 });
});
