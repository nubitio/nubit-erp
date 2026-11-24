import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

for (const theme of ['light', 'dark'] as const) {
  test(`POS mobile follows the catalog and checkout design in ${theme}`, async ({ page }, info) => {
    const seed = await getE2ESeed();
    const unexpectedWrites: string[] = [];
    // Looking at the catalogue must not change business data. What the shell itself writes on load is let
    // through: the till's offline credential (POS) and the user's own appearance preference (profile).
    const housekeeping = /\/api\/(auth\/|pos\/offline-credential$|profile$)/;
    await page.route('**/api/**', (route) => {
      if (
        ['GET', 'HEAD', 'OPTIONS'].includes(route.request().method()) ||
        housekeeping.test(route.request().url())
      )
        return route.fallback();
      unexpectedWrites.push(route.request().url());
      return route.abort();
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.emulateMedia({ colorScheme: theme });
    await login(page, seed.username, seed.password);
    await page.goto(`${getE2EBaseUrl()}/pos`);
    await expect(page.getByRole('heading', { name: 'Nueva venta', exact: true })).toBeVisible();
    const add = page.locator('.pos-product-card__select:not(:disabled)').first();
    await expect(add).toBeVisible({ timeout: 30000 });
    const media = add.locator('.pos-product-card__thumb');
    // The photo leads the card: a 4:3 frame (POSDesign.scss), not a fixed-height strip.
    const frame = (await media.boundingBox())!;
    expect(frame.width).toBeGreaterThan(140);
    expect(frame.height / frame.width).toBeCloseTo(0.75, 1);
    await add.click();
    await add.focus();
    await page.keyboard.press('Enter');
    const review = page.getByTestId('pos-review-order');
    await expect(review).toBeInViewport({ ratio: 1 });
    await expect(review).toContainText('2 ítems');
    expect(unexpectedWrites).toEqual([]);
    if (theme === 'dark')
      await expect(page.locator('.pos-shell')).toHaveCSS('background-color', 'rgb(10, 11, 13)');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      390,
    );
    await page.screenshot({
      path: info.outputPath(`pos-catalog-${theme}.png`),
      animations: 'disabled',
    });
    const amount = await review.locator('strong').innerText();
    await review.click();
    await expect(page.getByRole('heading', { name: 'Cobrar', exact: true })).toBeFocused();
    await expect(page.getByTestId('pos-open-payment-btn')).toContainText(amount);
    await expect(page.getByTestId('pos-open-payment-btn')).toBeInViewport({ ratio: 1 });
    await page.getByTestId('pos-open-payment-btn').click();
    const paymentDialog = page.getByRole('dialog', { name: 'Cobrar venta' });
    await expect(paymentDialog).toBeVisible();
    await expect(page.getByTestId('pos-checkout-btn')).toContainText(amount);
    await expect(page.getByTestId('pos-checkout-btn')).toBeInViewport({ ratio: 1 });
    const methods = paymentDialog.getByRole('radiogroup', { name: 'Método de pago principal' });
    const credit = methods.locator('label').filter({ hasText: /^Cr[eé]dito$/i });
    if (await credit.count()) {
      await credit.click();
      await expect(page.getByTestId('pos-credit-notice')).toBeVisible();
    }
    await methods
      .locator('label')
      .filter({ hasText: /^Efectivo$/i })
      .click();
    await page.screenshot({
      path: info.outputPath(`pos-checkout-${theme}.png`),
      animations: 'disabled',
    });
    await page.keyboard.press('Escape');
    await expect(paymentDialog).toBeHidden();
    await page.getByRole('button', { name: 'Volver a productos', exact: true }).click();
    await expect(page.getByTestId('pos-product-search')).toBeFocused();
    await page.getByRole('button', { name: 'Opciones del punto de venta', exact: true }).click();
    const context = page.getByRole('dialog', { name: 'Opciones del punto de venta', exact: true });
    await expect(context).toBeVisible();
    await expect(context.getByRole('button', { name: 'Salir del POS', exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(context).toBeHidden();
  });
}

test('POS desktop keeps product price editing separate from cart selection', async ({
  page,
}, info) => {
  const seed = await getE2ESeed();
  await page.route('**/api/products/**', (route) =>
    ['GET', 'HEAD'].includes(route.request().method()) ? route.fallback() : route.abort(),
  );
  await login(page, seed.username, seed.password);
  await page.setViewportSize({ width: 1440, height: 960 });
  await page.goto(`${getE2EBaseUrl()}/pos`);
  const card = page.getByTestId('pos-product-card').first();
  await expect(card).toBeVisible({ timeout: 30000 });
  const add = card.getByTestId('pos-product-card-add');
  await expect(add).not.toHaveAttribute('aria-pressed', 'true');
  const edit = card.getByRole('button', { name: /Editar precio maestro/ });
  if (await edit.count()) {
    await edit.focus();
    await page.keyboard.press('Enter');
    await expect(card.getByRole('spinbutton')).toBeFocused();
    await expect(add).not.toHaveAttribute('aria-pressed', 'true');
    await card.getByRole('button', { name: 'Cancelar cambio de precio', exact: true }).click();
  }
  await expect(page.getByTestId('pos-review-order')).toBeHidden();
  await page.screenshot({ path: info.outputPath('pos-desktop.png'), animations: 'disabled' });
});
