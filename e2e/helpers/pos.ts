import { expect, type Page } from '@playwright/test';

export interface SelectPosCustomerOptions {
  name: string;
  /** RUC/DNI or business name substring used to search. */
  search?: string;
}

/**
 * Select a customer in the POS checkout panel.
 * Clicks "Cambiar" to open search, waits for the customers API, then picks the option.
 */
export async function selectPosCustomer(page: Page, opts: SelectPosCustomerOptions) {
  const changeBtn = page.getByTestId('pos-customer-clear');
  await changeBtn.waitFor({ state: 'visible', timeout: 10000 });

  const searchInput = page.getByTestId('pos-customer-search');
  const searchAlreadyOpen = await searchInput.isVisible({ timeout: 1000 }).catch(() => false);
  if (!searchAlreadyOpen) {
    await changeBtn.click();
    await searchInput.waitFor({ state: 'visible', timeout: 8000 });
  }

  const searchTerm = opts.search ?? opts.name;
  const responsePromise = page
    .waitForResponse(
      (r) => r.url().includes('/customers') && r.request().method() === 'GET',
      { timeout: 15000 },
    )
    .catch(() => null);

  await searchInput.fill('');
  await searchInput.fill(searchTerm);
  await responsePromise;

  const option = page.getByTestId('pos-customer-option').filter({ hasText: opts.name }).first();
  await expect(option).toBeVisible({ timeout: 15000 });
  await option.click();

  await expect(page.getByTestId('pos-customer-toggle').filter({ hasText: opts.name })).toBeVisible({
    timeout: 8000,
  });
}

/**
 * Select cash payment and enter the received amount.
 * The cash panel is collapsed by default — expand it before filling.
 */
export async function completePosCashPayment(page: Page) {
  const paymentGroup = page.locator('[role="radiogroup"][aria-label="Método de pago"]');
  await paymentGroup.waitFor({ state: 'visible', timeout: 15000 });

  const cashMethod = paymentGroup.locator('label.pos-payment-btn').filter({ hasText: /efectivo/i }).first();
  const hasCash = await cashMethod.isVisible({ timeout: 3000 }).catch(() => false);
  if (hasCash) {
    await cashMethod.click();
  } else {
    await paymentGroup.locator('label.pos-payment-btn').first().click();
  }

  const cashToggle = page.locator('.pos-cash-toggle').first();
  const cashToggleVisible = await cashToggle.isVisible({ timeout: 3000 }).catch(() => false);
  if (cashToggleVisible) {
    const amountInput = page.getByLabel('Monto recibido');
    const inputVisible = await amountInput.isVisible({ timeout: 500 }).catch(() => false);
    if (!inputVisible) {
      await cashToggle.click();
      await amountInput.waitFor({ state: 'visible', timeout: 5000 });
    }

    const exactoChip = page
      .locator('.pos-payment__cash .app-chip')
      .filter({ hasText: /^Exacto$/ })
      .first();
    if (await exactoChip.isVisible({ timeout: 2000 }).catch(() => false)) {
      await exactoChip.click();
    } else {
      const totalText = await page.getByTestId('pos-checkout-btn').locator('strong').textContent();
      const match = totalText?.match(/[\d.]+/);
      await amountInput.fill(match?.[0] ?? '100');
    }
    return;
  }

  // Non-cash payment — no amount field required
  const amountInput = page.getByLabel('Monto recibido');
  if (await amountInput.isVisible({ timeout: 1500 }).catch(() => false)) {
    const totalText = await page.getByTestId('pos-checkout-btn').locator('strong').textContent();
    const match = totalText?.match(/[\d.]+/);
    await amountInput.fill(match?.[0] ?? '100');
  }
}

/** Wait until the POS checkout button is enabled. */
export async function waitForPosCheckoutReady(page: Page) {
  const cobrarBtn = page.getByTestId('pos-checkout-btn');
  await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
  await expect(cobrarBtn).not.toBeDisabled({ timeout: 15000 });
}