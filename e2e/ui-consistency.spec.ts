/**
 * e2e/ui-consistency.spec.ts
 *
 * Validates enterprise UX patterns across main CRUD pages:
 *  - Drawer shell (not legacy centered dialog) for create/edit
 *  - Column preset segments on list grids
 *  - No native <select> in datagrid toolbars/filters/paginators
 *  - Tabbed forms on long-form resources
 *  - AppDropdown for enum filters where applicable
 */

import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import {
  clickGridToolbarButton,
  closeCrudShell,
  columnPresetControl,
  crudShell,
  crudShellBody,
  switchFormTab,
  waitForGridLoad,
} from './helpers/crud';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

type CrudPageSpec = {
  route: string;
  toolbar: RegExp;
  /** Pages that open a create shell with drawer UX */
  opensDrawer: boolean;
  /** Pages with ColumnPresetSelector segments */
  hasPresets: boolean;
  /** Pages whose create form uses NativeFormView tabs */
  hasFormTabs?: RegExp[];
};

const CRUD_PAGES: CrudPageSpec[] = [
  {
    route: '/product',
    toolbar: /productos|products/i,
    opensDrawer: true,
    hasPresets: true,
    hasFormTabs: [/General/i, /Inventario/i],
  },
  {
    route: '/customer',
    toolbar: /clientes|customers/i,
    opensDrawer: true,
    hasPresets: true,
    hasFormTabs: [/General/i, /Contacto/i],
  },
  { route: '/category', toolbar: /categor/i, opensDrawer: true, hasPresets: true, hasFormTabs: [/General/i] },
  { route: '/warehouse', toolbar: /almac/i, opensDrawer: true, hasPresets: true, hasFormTabs: [/General/i] },
  {
    route: '/supplier',
    toolbar: /proveedor/i,
    opensDrawer: true,
    hasPresets: true,
    hasFormTabs: [/General/i, /Contacto/i],
  },
  { route: '/role', toolbar: /rol/i, opensDrawer: true, hasPresets: true, hasFormTabs: [/General/i] },
  { route: '/user', toolbar: /usuario/i, opensDrawer: true, hasPresets: true, hasFormTabs: [/Acceso/i] },
  { route: '/invoice', toolbar: /comprobante/i, opensDrawer: false, hasPresets: true },
  {
    route: '/purchases',
    toolbar: /compra/i,
    opensDrawer: true,
    hasPresets: true,
    hasFormTabs: [/Cabecera/i, /Notas/i],
  },
  { route: '/reorder', toolbar: /reabastecimiento|reorder/i, opensDrawer: false, hasPresets: true },
];

function datagridSurface(page: Page) {
  return page.locator('.native-datagrid').first();
}

test.describe('UI consistency — patrones CRUD enterprise', () => {
  let page: Page;
  let username = '';
  let password = '';

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    username = seed.username;
    password = seed.password;

    const ctx = await browser.newContext();
    page = await ctx.newPage();
    await login(page, username, password);
  });

  test.afterAll(async () => {
    await page.close().catch(() => {});
    await page.context().close().catch(() => {});
  });

  for (const spec of CRUD_PAGES) {
    test(`${spec.route} — grid UX coherente`, async () => {
      await page.goto(`${BASE_URL}/#${spec.route}`);
      await waitForGridLoad(page);

      const grid = datagridSurface(page);
      await expect(grid).toBeVisible({ timeout: 15000 });
      await expect(
        page.locator('.native-datagrid__toolbar').filter({ hasText: spec.toolbar }).first(),
      ).toBeVisible({ timeout: 15000 });

      if (spec.hasPresets) {
        await expect(columnPresetControl(page)).toBeVisible({ timeout: 8000 });
        const presetButtons = columnPresetControl(page).getByRole('button');
        expect(await presetButtons.count()).toBeGreaterThanOrEqual(2);
      }

      // Native selects are not used in datagrid chrome (filters, toolbar, paginator).
      const nativeSelects = grid.locator('select');
      await expect(nativeSelects).toHaveCount(0);

      // Enum/operator filters use AppDropdown (compact), never native <select>.
      const enumFilterDropdowns = grid.locator(
        '.native-datagrid__filter-row .app-dropdown, .native-datagrid__filter-operator .app-dropdown',
      );
      if ((await enumFilterDropdowns.count()) > 0) {
        await expect(enumFilterDropdowns.first()).toBeVisible();
      }
    });

    if (spec.opensDrawer) {
      test(`${spec.route} — formulario en drawer con tabs`, async () => {
        await page.goto(`${BASE_URL}/#${spec.route}`);
        await waitForGridLoad(page);

        await clickGridToolbarButton(page, 'Nuevo');

        const shell = crudShell(page, /nuevo|new/i);
        await shell.waitFor({ state: 'visible', timeout: 12000 });
        await expect(shell).toHaveClass(/crud-drawer/);

        const body = crudShellBody(page);
        await expect(body.locator('select')).toHaveCount(0);

        if (spec.hasFormTabs?.length) {
          await expect(body.getByRole('tablist')).toBeVisible({ timeout: 8000 });
          for (const tab of spec.hasFormTabs) {
            await switchFormTab(body, tab);
            await expect(body.getByRole('tab', { name: tab, selected: true })).toBeVisible();
          }
        }

        await closeCrudShell(shell);
      });
    }
  }
});