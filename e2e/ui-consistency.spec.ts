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
  /** Pages that open a create shell */
  opensForm: boolean;
  shell?: 'drawer' | 'dialog' | 'page';
  /** Pages with ColumnPresetSelector segments */
  hasPresets: boolean;
  /** Pages whose create form uses NativeFormView tabs */
  hasFormTabs?: RegExp[];
};

const CRUD_PAGES: CrudPageSpec[] = [
  {
    route: '/product',
    toolbar: /productos|products/i,
    opensForm: true,
    shell: 'drawer',
    hasPresets: true,
    hasFormTabs: [/General/i, /Inventario/i],
  },
  {
    route: '/customer',
    toolbar: /clientes|customers/i,
    opensForm: true,
    shell: 'drawer',
    hasPresets: true,
  },
  { route: '/category', toolbar: /categor/i, opensForm: true, shell: 'dialog', hasPresets: false },
  { route: '/warehouse', toolbar: /almac/i, opensForm: true, shell: 'dialog', hasPresets: false },
  {
    route: '/supplier',
    toolbar: /proveedor/i,
    opensForm: true,
    shell: 'drawer',
    hasPresets: true,
  },
  { route: '/role', toolbar: /rol/i, opensForm: true, shell: 'dialog', hasPresets: true },
  { route: '/user', toolbar: /usuario/i, opensForm: true, shell: 'dialog', hasPresets: true },
  { route: '/invoice', toolbar: /comprobante/i, opensForm: false, hasPresets: true },
  {
    route: '/purchases',
    toolbar: /compra/i,
    opensForm: true,
    shell: 'page',
    hasPresets: true,
    hasFormTabs: [/Cabecera/i, /Notas/i],
  },
  { route: '/reorder', toolbar: /reabastecimiento|reorder/i, opensForm: false, hasPresets: true },
];

const shellClassPattern: Record<NonNullable<CrudPageSpec['shell']>, RegExp> = {
  drawer: /nb-drawer|crud-drawer/,
  dialog: /nb-crud-dialog|nb-dialog/,
  page: /nb-crud-page-shell/,
};

function datagridSurface(page: Page) {
  return page.locator('.nb-datagrid').first();
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
        page.locator('.nb-datagrid__toolbar').filter({ hasText: spec.toolbar }).first(),
      ).toBeVisible({ timeout: 15000 });

      if (spec.hasPresets) {
        await expect(columnPresetControl(page)).toBeVisible({ timeout: 8000 });
        const presetButtons = columnPresetControl(page).getByRole('button');
        expect(await presetButtons.count()).toBeGreaterThanOrEqual(1);
      }

      // Native selects are not used in datagrid chrome (filters, toolbar, paginator).
      const nativeSelects = grid.locator('select');
      await expect(nativeSelects).toHaveCount(0);

      // Enum/operator filters use AppDropdown (compact), never native <select>.
      const enumFilterDropdowns = grid.locator(
        '.nb-datagrid__filter-row .nb-dropdown, .nb-datagrid__filter-operator .nb-dropdown',
      );
      if ((await enumFilterDropdowns.count()) > 0) {
        await expect(enumFilterDropdowns.first()).toBeVisible();
      }
    });

    if (spec.opensForm) {
      test(`${spec.route} — formulario CRUD consistente`, async () => {
        await page.goto(`${BASE_URL}/#${spec.route}`);
        await waitForGridLoad(page);

        await clickGridToolbarButton(page, 'Nuevo');

        const shell = crudShell(page, /nuevo|new/i);
        await shell.waitFor({ state: 'visible', timeout: 12000 });
        if (spec.shell) {
          await expect(shell).toHaveClass(shellClassPattern[spec.shell]);
        }

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
