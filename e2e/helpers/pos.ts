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
    .waitForResponse(
      (r) => r.url().includes('/customers') && r.request().method() === 'GET',
      { timeout: 15000 },
    )
    .catch(() => null);

  await searchInput.click();
  await searchInput.fill('');
  await searchInput.fill(searchTerm);
  await responsePromise;

  const option = page
    .locator('.nb-form__lookup-menu button[role="option"]')
    .filter({ hasText: opts.name })
    .first();
  await expect(option).toBeVisible({ timeout: 15000 });
  await option.click();

  await expect(customerSection).toContainText(opts.name, { timeout: 8000 });
}

/**
 * Select cash payment via the split-payments quick method picker.
 */
export async function completePosCashPayment(page: Page) {
  const methodsGroup = page.locator('[role="radiogroup"][aria-label="Método de pago rápido"]');
  await methodsGroup.waitFor({ state: 'visible', timeout: 15000 });

  const cashMethod = methodsGroup
    .locator('label.split-payments-field__method')
    .filter({ hasText: /efectivo/i })
    .first();
  const hasCash = await cashMethod.isVisible({ timeout: 3000 }).catch(() => false);
  if (hasCash) {
    await cashMethod.click();
  } else {
    await methodsGroup.locator('label.split-payments-field__method').first().click();
  }

  // Cash with exact total is auto-balanced; open drawer only if tendered amount is required.
  const paymentsTrigger = page.getByTestId('pos-payments-trigger');
  const triggerVisible = await paymentsTrigger.isVisible({ timeout: 2000 }).catch(() => false);
  if (!triggerVisible) {
    return;
  }

  const warningBadge = paymentsTrigger.locator('.split-payments-field__trigger-badge.is-warning');
  const needsDrawer = await warningBadge.isVisible({ timeout: 1000 }).catch(() => false);
  if (!needsDrawer) {
    return;
  }

  await paymentsTrigger.click();

  const exactoChip = page
    .locator('.split-payments-drawer .nb-chip')
    .filter({ hasText: /^Exacto$/ })
    .first();
  if (await exactoChip.isVisible({ timeout: 3000 }).catch(() => false)) {
    await exactoChip.click();
  } else {
    const amountInput = page.getByLabel('Monto recibido').first();
    if (await amountInput.isVisible({ timeout: 2000 }).catch(() => false)) {
      const totalText = await page.getByTestId('pos-checkout-btn').locator('strong').textContent();
      const match = totalText?.match(/[\d.]+/);
      await amountInput.fill(match?.[0] ?? '100');
    }
  }

  const doneBtn = page.locator('.split-payments-drawer__actions button').filter({ hasText: /listo|cerrar|guardar/i }).first();
  if (await doneBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await doneBtn.click();
  } else {
    await page.keyboard.press('Escape');
  }
}

/** Wait until the POS checkout button is enabled. */
export async function waitForPosCheckoutReady(page: Page) {
  const cobrarBtn = page.getByTestId('pos-checkout-btn');
  await cobrarBtn.waitFor({ state: 'visible', timeout: 8000 });
  await expect(cobrarBtn).not.toBeDisabled({ timeout: 15000 });
}