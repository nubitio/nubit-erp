import { expect, type Locator, type Page } from '@playwright/test';

/** Wait for the native datagrid loading overlay to finish (if it appears). */
export async function waitForGridLoad(page: Page) {
  const overlay = page.locator('.native-datagrid__loading-overlay').first();
  try {
    await overlay.waitFor({ state: 'visible', timeout: 2000 });
    await overlay.waitFor({ state: 'hidden', timeout: 15000 });
  } catch {
    // Overlay never appeared — grid already loaded.
  }
}

/**
 * Returns the active CRUD shell — drawer (preferred) or legacy centered dialog.
 * Both use role="dialog"; drawer body is `.crud-drawer__body`.
 */
export function crudShell(page: Page, name: RegExp = /nuevo|new|editar|edit/i): Locator {
  return page.getByRole('dialog', { name }).last();
}

export function crudShellBody(page: Page, name?: RegExp): Locator {
  return crudShell(page, name).locator(
    '.crud-drawer__body, .app-dialog__body, .crud-dialog__body',
  );
}

export async function clickGridToolbarButton(page: Page, buttonText: string) {
  const alternatives: Record<string, RegExp> = {
    Nuevo: /^(Nuevo|New)$/i,
    Editar: /^(Editar|Edit)$/i,
    Eliminar: /^(Eliminar|Delete)$/i,
  };
  const text = alternatives[buttonText] ?? new RegExp(`^${buttonText}$`, 'i');
  const btn = page
    .locator('.native-datagrid__toolbar .app-button')
    .filter({ hasText: text })
    .first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

/** Navigate to a CRUD list and open the "New" form shell. */
export async function openNewCrudForm(page: Page, route: string, toolbarTitle?: string | RegExp) {
  await page.goto(route);
  await waitForGridLoad(page);

  if (toolbarTitle) {
    await expect(
      page.locator('.native-datagrid__toolbar').filter({ hasText: toolbarTitle }).first(),
    ).toBeVisible({ timeout: 15000 });
  }

  await clickGridToolbarButton(page, 'Nuevo');

  const shell = crudShell(page, /nuevo|new/i);
  await shell.waitFor({ state: 'visible', timeout: 12000 });
  return shell;
}

export async function closeCrudShell(shell: Locator) {
  await shell
    .getByRole('button', { name: /cancel|cerrar/i })
    .first()
    .click()
    .catch(() => {});
  await shell.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
}

/** Switch an active tab inside a tabbed CRUD form (NativeFormView). */
export async function switchFormTab(body: Locator, tabName: RegExp | string) {
  const tab = body.getByRole('tab', { name: tabName });
  await tab.waitFor({ state: 'visible', timeout: 8000 });
  await tab.click();
}