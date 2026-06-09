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

/** Returns the active CRUD shell — drawer/dialog or inline page form.
 * Matches by role+name when the shell exposes "nuevo|new|..." (or caller-provided name).
 * Always includes structural containers (.crud-drawer, .crud-page-shell) so that
 * page-mode resources (aria-label = resource title like "Compras") are discoverable
 * regardless of the exact accessible name.
 */
export function crudShell(page: Page, name: RegExp = /nuevo|new|editar|edit/i): Locator {
  const named = page.getByRole('dialog', { name }).or(page.getByRole('region', { name }));
  const structural = page.locator('.crud-drawer-root--open .crud-drawer, .crud-page-shell, .app-dialog:visible, .crud-dialog:visible');
  // structural first so page shells are found even if name doesn't include "new";
  // .last() selects the most recently attached / topmost shell in DOM order.
  return structural.or(named).last();
}

export function crudShellBody(page: Page, name?: RegExp): Locator {
  return crudShell(page, name).locator(
    '.crud-drawer__body, .crud-page-shell__body, .app-dialog__body, .crud-dialog__body',
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

/** Hide Symfony Web Debug Toolbar — it overlaps drawer footers in dev. */
export async function dismissDevToolbar(page: Page) {
  await page.addStyleTag({
    content:
      '.sf-toolbar, .sf-toolbar-opened, [id^="sfwdt"] { display: none !important; pointer-events: none !important; visibility: hidden !important; }',
  });
}

/** Click the CRUD save button without pointer interception from dev toolbars. */
export async function clickCrudSave(shell: Locator) {
  const save = shell
    .locator(
      '.crud-drawer__footer .dialog-save-button, .crud-page-shell__footer .dialog-save-button, .crud-dialog__footer .dialog-save-button, .app-dialog__footer .dialog-save-button',
    )
    .first();
  await save.waitFor({ state: 'visible', timeout: 8000 });
  await save.evaluate((btn) => (btn as HTMLButtonElement).click());
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

/** Column preset control rendered by ColumnPresetSelector (segment buttons). */
export function columnPresetControl(page: Page) {
  return page.locator('.native-datagrid__preset-control').first();
}

export async function selectColumnPreset(page: Page, presetLabel: RegExp | string) {
  const control = columnPresetControl(page);
  await control.waitFor({ state: 'visible', timeout: 8000 });
  await control.getByRole('button', { name: presetLabel }).click();
}
