import { expect, type Locator, type Page } from '@playwright/test';

/** Scope the schema-driven grid to the active Customers page. */
export const customersGrid = (page: Page): Locator =>
  page.getByRole('main', { name: 'Clientes content', exact: true }).locator('.nb-datagrid');

/** Locate the create action in the customer's toolbar at either viewport size. */
export const newCustomerButton = (page: Page): Locator =>
  customersGrid(page).getByRole('button', { name: 'Nuevo', exact: true });

/** First load done and no request in flight. */
export async function waitForCustomersGrid(page: Page): Promise<void> {
  await expect(customersGrid(page)).toBeVisible({ timeout: 30_000 });
  await expect(customersGrid(page)).toHaveAttribute('aria-busy', 'false', { timeout: 30_000 });
}

/** A row (desktop) or card (mobile) showing the given text. */
export const customerRow = (page: Page, text: string | RegExp): Locator =>
  customersGrid(page).getByRole('row').filter({ hasText: text });

/**
 * The colour a theme token resolves to, as the computed `rgb(...)` the browser reports.
 * Used instead of hard-coding a palette value, so the assertion is "uses the error/success
 * colour of the active theme" and keeps holding when the palette is tuned.
 */
export async function themeColor(page: Page, token: string): Promise<string> {
  return page.evaluate((name) => {
    const probe = document.createElement('span');
    probe.style.color = `var(${name})`;
    document.body.appendChild(probe);
    const colour = getComputedStyle(probe).color;
    probe.remove();
    return colour;
  }, token);
}
