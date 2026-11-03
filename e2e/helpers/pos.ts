import { expect, type Page } from '@playwright/test';

export interface SelectPosCustomerOptions {
  name: string;
  /** RUC/DNI or business name substring used to search. */
  search?: string;
}

/**
 * Select a customer in the POS checkout panel (EntityDropdown).
 */
export async function selectPosCustomer(page: Page, opts: SelectPosCustomerOptions) {
  const customerSection = page.getByTestId('pos-customer');
  await customerSection.waitFor({ state: 'visible', timeout: 10000 });

  const searchInput = customerSection
    .locator('.nb-form__lookup-input, input[role="combobox"], input[type="search"]')
    .first();
  await searchInput.waitFor({ state: 'visible', timeout: 8000 });

  const searchTerm = opts.search ?? opts.name;
  const responsePromise = page
    .waitForResponse((r) => r.url().includes('/customers') && r.request().method() === 'GET', {
      timeout: 15000,
    })
    .catch(() => null);

  await searchInput.click();
  await searchInput.fill('');
  await searchInput.fill(searchTerm);
  await responsePromise;

  const option = page.getByRole('option').filter({ hasText: opts.name }).first();
  await expect(option).toBeVisible({ timeout: 15000 });
  await option.click();

  const expectedName = opts.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  await expect(searchInput).toHaveValue(new RegExp(expectedName, 'i'), { timeout: 8000 });
}

/** Open the "Cobrar venta" dialog from the order panel (idempotent). */
export async function openPosPayment(page: Page) {
  const dialog = page.getByRole('dialog', { name: 'Cobrar venta' });
  if (await dialog.isVisible({ timeout: 500 }).catch(() => false)) return dialog;
  const open = page.getByTestId('pos-open-payment-btn');
  await expect(open).toBeEnabled({ timeout: 15000 });
  await open.click();
  await dialog.waitFor({ state: 'visible', timeout: 8000 });
  return dialog;
}

/** Pick a payment method in the "Cobrar venta" dialog. */
export async function selectPosPaymentMethod(page: Page, name: RegExp) {
  const dialog = await openPosPayment(page);
  await dialog
    .getByRole('radiogroup', { name: 'Método de pago principal' })
    .locator('label')
    .filter({ hasText: name })
    .first()
    .click();
  return dialog;
}

/**
 * Select cash payment in the dialog. The tendered amount defaults to the exact total,
 * so no further input is needed.
 */
export async function completePosCashPayment(page: Page) {
  const dialog = await openPosPayment(page);
  const group = dialog.getByRole('radiogroup', { name: 'Método de pago principal' });
  await group.waitFor({ state: 'visible', timeout: 15000 });
  const cash = group
    .locator('label')
    .filter({ hasText: /efectivo/i })
    .first();
  if (await cash.isVisible({ timeout: 3000 }).catch(() => false)) await cash.click();
  else await group.locator('label').first().click();
}

/** Wait until the POS checkout button is enabled. */
export async function waitForPosCheckoutReady(page: Page) {
  await openPosPayment(page);
  const cobrarBtn = page.getByTestId('pos-checkout-btn');
  await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
  await expect(cobrarBtn).not.toBeDisabled({ timeout: 15000 });
}
