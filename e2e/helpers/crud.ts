import { expect, type Locator, type Page } from '@playwright/test';

/** Wait for the native datagrid loading overlay to finish (if it appears). */
export async function waitForGridLoad(page: Page) {
  const overlay = page.locator('.nb-datagrid__loading-overlay').first();
  try {
    await overlay.waitFor({ state: 'visible', timeout: 2000 });
    await overlay.waitFor({ state: 'hidden', timeout: 15000 });
  } catch {
    // Overlay never appeared — grid already loaded.
  }
}

/** Returns the active CRUD shell — drawer/dialog or inline page form.
 * Matches by role+name when the shell exposes "nuevo|new|..." (or caller-provided name).
 * Always includes structural containers (.crud-drawer, .nb-crud-page-shell) so that
 * page-mode resources (aria-label = resource title like "Compras") are discoverable
 * regardless of the exact accessible name.
 */
export function crudShell(page: Page, name: RegExp = /nuevo|new|editar|edit/i): Locator {
  const named = page.getByRole('dialog', { name }).or(page.getByRole('region', { name }));
  const structural = page.locator(
    '.crud-drawer-root--open .crud-drawer, .nb-drawer-root--open .nb-drawer, .nb-crud-page-shell, .nb-dialog:visible, .nb-crud-dialog:visible',
  );
  // structural first so page shells are found even if name doesn't include "new";
  // .last() selects the most recently attached / topmost shell in DOM order.
  return structural.or(named).last();
}

export function crudShellBody(page: Page, name?: RegExp): Locator {
  return crudShell(page, name).locator(
    '.crud-drawer__body, .nb-drawer__body, .nb-crud-page-shell__body, .nb-dialog__body, .nb-crud-dialog__body',
  );
}

export async function clickGridToolbarButton(page: Page, buttonText: string) {
  const alternatives: Record<string, RegExp> = {
    Nuevo: /^(Nuevo|New|Nueva venta)$/i,
    Editar: /^(Editar|Edit)$/i,
    Eliminar: /^(Eliminar|Delete)$/i,
  };
  const text = alternatives[buttonText] ?? new RegExp(`^${buttonText}$`, 'i');
  const btn = page.locator('.nb-datagrid__toolbar .nb-button').filter({ hasText: text }).first();
  await btn.waitFor({ state: 'visible', timeout: 8000 });
  await btn.click();
}

/** Navigate to a CRUD list and open the "New" form shell. */
export async function openNewCrudForm(page: Page, route: string, toolbarTitle?: string | RegExp) {
  await page.goto(route);
  await waitForGridLoad(page);

  if (toolbarTitle) {
    await expect(
      page.locator('.nb-datagrid__toolbar').filter({ hasText: toolbarTitle }).first(),
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
      '.crud-drawer__footer .nb-dialog-save-button, .nb-drawer__footer .nb-dialog-save-button, .nb-crud-page-shell__footer .nb-dialog-save-button, .nb-crud-dialog__footer .nb-dialog-save-button, .nb-dialog__footer .nb-dialog-save-button',
    )
    .first();
  await save.waitFor({ state: 'visible', timeout: 8000 });
  await save.evaluate((btn) => (btn as HTMLButtonElement).click());
}

export async function closeCrudShell(shell: Locator) {
  const backOrClose = shell.locator('.nb-crud-page-shell__back, .nb-drawer__close').first();
  if (await backOrClose.isVisible({ timeout: 1500 }).catch(() => false)) {
    await backOrClose.click();
  } else {
    await shell
      .getByRole('button', { name: /cancel|cerrar/i })
      .first()
      .click()
      .catch(() => {});
  }
  await shell.waitFor({ state: 'hidden', timeout: 8000 }).catch(() => {});
}

/** Switch an active tab inside a tabbed CRUD form (NativeFormView). */
export async function switchFormTab(body: Locator, tabName: RegExp | string) {
  const tab = body.getByRole('tab', { name: tabName });
  await tab.waitFor({ state: 'visible', timeout: 8000 });
  await tab.click();
}

/** Expand a collapsible section inside a sectioned CRUD form (NativeFormView). */
export async function expandFormSection(body: Locator, sectionName: RegExp | string) {
  const section = body.locator('.nb-form__section').filter({ hasText: sectionName }).first();
  await section.waitFor({ state: 'visible', timeout: 8000 });

  const summary = section.locator('summary.nb-form__section-summary');
  if (await summary.isVisible({ timeout: 500 }).catch(() => false)) {
    const isOpen = await section.evaluate((el) => (el as HTMLDetailsElement).open);
    if (!isOpen) {
      await summary.click();
    }
  }
}

/** Wait until the main sale form fields are visible (page mode, sections layout). */
export async function waitForSaleFormReady(body: Locator) {
  await expect(body.getByLabel(/Cliente/i).first()).toBeVisible({ timeout: 8000 });
  await expect(body.getByLabel(/Fecha/i).first()).toBeVisible({ timeout: 8000 });
}

/** Column preset control rendered by ColumnPresetSelector (segment buttons). */
export function columnPresetControl(page: Page) {
  return page.locator('.nb-datagrid__preset-control').first();
}

export async function selectColumnPreset(page: Page, presetLabel: RegExp | string) {
  const control = columnPresetControl(page);
  await control.waitFor({ state: 'visible', timeout: 8000 });
  await control.getByRole('button', { name: presetLabel }).click();
}
