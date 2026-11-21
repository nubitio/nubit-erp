import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

/**
 * Global POS shortcuts. They are bound on `document`, so the cases that matter are the ones where
 * they must NOT fire: typing in a field, a dialog on top, an empty cart.
 */
async function openPos(page: Page) {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1288, height: 800 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/pos`);
  await expect(page.locator('.pos-product-card__select:not(:disabled)').first()).toBeVisible({
    timeout: 30000,
  });
}

const addProducts = async (page: Page, count: number) => {
  const cards = page.locator('.pos-product-card__select:not(:disabled)');
  for (let index = 0; index < count; index++) await cards.nth(index).click();
  await expect(page.locator('.pos-line')).toHaveCount(count);
};

const blurFields = (page: Page) => page.locator('body').click({ position: { x: 4, y: 4 } });

test('? lists the shortcuts, but not while typing in a field', async ({ page }) => {
  await openPos(page);
  const shortcuts = page.getByRole('dialog', { name: 'Atajos de teclado' });

  await page.getByTestId('pos-product-search').click();
  await page.keyboard.type('?');
  await expect(shortcuts).toHaveCount(0);
  await page.getByTestId('pos-product-search').fill('');

  await blurFields(page);
  await page.keyboard.press('Shift+?');
  await expect(shortcuts).toBeVisible();
  await expect(shortcuts.getByText('Poner la venta en espera')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(shortcuts).toHaveCount(0);
});

test('F8 edits the quantity of the last line', async ({ page }) => {
  await openPos(page);
  await addProducts(page, 2);
  await blurFields(page);

  await page.keyboard.press('F8');
  const last = page.locator('.pos-line').last();
  const input = last.locator('.pos-line__qty-input');
  await expect(input).toBeFocused();
  await input.fill('7');
  await input.press('Enter');
  await expect(last.locator('.pos-line__qty-display')).toContainText('7');
  await expect(page.locator('.pos-line').first().locator('.pos-line__qty-display')).toContainText(
    '1',
  );
});

test('F4 holds the sale and Shift+F4 lists held sales', async ({ page }) => {
  await openPos(page);
  const held = page.getByTestId('pos-held-sales-btn');

  // Empty cart: nothing to hold.
  await blurFields(page);
  await page.keyboard.press('F4');
  await expect(held).not.toContainText('(');

  await addProducts(page, 2);

  // A dialog on top owns the keyboard: F4 must not hold the sale behind it.
  await blurFields(page);
  await page.keyboard.press('Shift+?');
  await expect(page.getByRole('dialog', { name: 'Atajos de teclado' })).toBeVisible();
  await page.keyboard.press('F4');
  await expect(page.locator('.pos-line')).toHaveCount(2);
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog', { name: 'Atajos de teclado' })).toHaveCount(0);

  await blurFields(page);
  await page.keyboard.press('F4');
  await expect(page.locator('.pos-line')).toHaveCount(0);
  await expect(held).toContainText('(1)');

  await page.keyboard.press('Shift+F4');
  await expect(page.getByRole('dialog', { name: 'Ventas en espera' })).toBeVisible();
});

test('Shift+F3 opens the new customer dialog', async ({ page }) => {
  await openPos(page);
  await blurFields(page);
  await page.keyboard.press('Shift+F3');
  await expect(page.getByRole('dialog', { name: 'Nuevo cliente' })).toBeVisible();
  await expect(page.getByLabel('RUC/DNI')).toBeFocused();
});
