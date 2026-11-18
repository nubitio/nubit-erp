import { expect, type Locator, type Page } from '@playwright/test';

/**
 * The customers screen (ADR-019 pilot) is a composed page, not the schema-driven grid, so it is
 * addressed by role and test id instead of the engine's `.nb-datagrid__*` classes.
 */
export const customersGrid = (page: Page): Locator => page.getByTestId('customers-grid');

/** Desktop shows a "Nuevo" button in the header; small screens show a floating "Nuevo cliente". */
export const newCustomerButton = (page: Page, mobile = false): Locator =>
  page.getByRole('button', { name: mobile ? 'Nuevo cliente' : 'Nuevo', exact: true });

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
