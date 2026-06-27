/**
 * Visual baseline for master-detail formDetail pages before/after
 * migrating to inferred line fields (x-embedded-lines).
 *
 *   make up
 *   E2E_BASE_URL=http://localhost:8080 yarn test:e2e e2e/form-detail-visual.spec.ts
 *
 * Screenshots → var/e2e-screenshots/{E2E_VISUAL_PHASE}/
 */

import { expect, test, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import {
  crudShell,
  crudShellBody,
  dismissDevToolbar,
  switchFormTab,
  waitForGridLoad,
} from './helpers/crud';

const BASE_URL = getE2EBaseUrl();
const PHASE = process.env.E2E_VISUAL_PHASE ?? 'baseline';
const OUT_DIR = join('var', 'e2e-screenshots', PHASE);
const SEED_PRODUCT_NAME = 'Producto CI E2E';

let USERNAME = process.env.E2E_USERNAME ?? '';
let PASSWORD = process.env.E2E_PASSWORD ?? '';

function shot(page: Page, name: string) {
  mkdirSync(OUT_DIR, { recursive: true });
  return page.screenshot({ path: join(OUT_DIR, `${name}.png`), fullPage: true });
}

async function fillDxInput(page: Page, ariaLabel: string | string[], value: string) {
  const labels = Array.isArray(ariaLabel) ? ariaLabel : [ariaLabel];
  let input = page.locator('__missing__');

  for (const label of labels) {
    const candidate = page
      .locator(
        `input[aria-label="${label}"], textarea[aria-label="${label}"], [role="textbox"][aria-label="${label}"]`,
      )
      .first();
    if (await candidate.isVisible({ timeout: 1500 }).catch(() => false)) {
      input = candidate;
      break;
    }
  }

  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.click({ clickCount: 3 });
  await page.keyboard.press('Control+a');
  await page.keyboard.press('Delete');
  await input.pressSequentially(value, { delay: 30 });
  await page.keyboard.press('Tab');
}

async function login(page: Page) {
  await page.goto(`${BASE_URL}/#/login`);
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 15000 });

  await fillDxInput(page, 'Usuario', USERNAME);
  await fillDxInput(page, 'Contraseña', PASSWORD);
  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

  try {
    const firstBranchOption = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranchOption.waitFor({ state: 'visible', timeout: 7000 });
    const e2eBranchOption = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: 'CI E2E Branch' });
    const targetOption =
      (await e2eBranchOption.count()) > 0 ? e2eBranchOption.first() : firstBranchOption;
    await targetOption.click();
    await page.waitForTimeout(300);
    const acceptBtn = page
      .getByRole('dialog', { name: 'Seleccionar local' })
      .getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
    await page.locator('[data-testid="branch-picker-option"]').first().waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    /* no branch picker */
  }

  await page.waitForURL(/\/#\/(dashboard|pos)/, { timeout: 20000 });
}

async function waitForApiDocs(page: Page) {
  await page
    .waitForResponse(
      (response) => response.url().includes('/api/docs.jsonld') && response.ok(),
      { timeout: 30000 },
    )
    .catch(() => {});
}

async function gotoRoute(page: Page, hashPath: string) {
  await page.goto(`${BASE_URL}/#/${hashPath}`);
  await page.waitForLoadState('domcontentloaded', { timeout: 10000 }).catch(() => {});
  await waitForApiDocs(page);
  await waitForGridLoad(page);
  await dismissDevToolbar(page);
}

async function waitForInferredDetailColumns(page: Page) {
  const detailHeaders = page.locator('.nb-form__detail-table thead th');
  await expect
    .poll(async () => detailHeaders.count(), {
      timeout: 30000,
      message: 'Esperando columnas inferidas en la tabla de detalle (x-embedded-lines)',
    })
    .toBeGreaterThanOrEqual(3);
}

async function clickNuevo(page: Page) {
  const nuevoBtn = page
    .locator('.nb-datagrid__toolbar .nb-button')
    .filter({ hasText: /^(Nuevo|New)$/i })
    .first();
  await nuevoBtn.waitFor({ state: 'visible', timeout: 15000 });
  await nuevoBtn.click();
  await expect(crudShell(page)).toBeVisible({ timeout: 30000 });
  await waitForInferredDetailColumns(page);
}

test.describe.serial(`Form detail visual — ${PHASE}`, () => {
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    const seed = await getE2ESeed();
    USERNAME = seed.username;
    PASSWORD = seed.password;

    const context = await browser.newContext();
    page = await context.newPage();
    await login(page);
  });

  test.afterAll(async () => {
    await page.close().catch(() => {});
  });

  test('purchases — form with detail row', async () => {
    await gotoRoute(page, 'purchases');
    await expect(
      page.locator('.nb-datagrid__toolbar').filter({ hasText: /compras/i }).first(),
    ).toBeVisible({ timeout: 15000 });

    await clickNuevo(page);
    const shell = crudShell(page);
    const body = crudShellBody(page);
    await switchFormTab(body, /Cabecera/i);

    const addRowBtn = shell.locator('button[aria-label="Agregar detalle"]').first();
    await addRowBtn.click();
    const detailTable = shell.locator('.nb-form__detail-table').first();
    await expect(detailTable).toBeVisible({ timeout: 8000 });

    const row = detailTable.locator('tbody tr').last();
    const productInput = row.locator('input[role="combobox"], input').first();
    await productInput.waitFor({ state: 'visible', timeout: 10000 });
    await productInput.click();
    await productInput.pressSequentially(SEED_PRODUCT_NAME, { delay: 40 });
    const seedOption = page
      .locator('.nb-form__lookup-menu button[role="option"]')
      .filter({ hasText: SEED_PRODUCT_NAME });
    if (await seedOption.first().isVisible({ timeout: 10000 }).catch(() => false)) {
      await seedOption.first().click();
    }

    await shot(page, 'purchases-form-detail');
    await expect(detailTable.locator('thead th').count()).resolves.toBeGreaterThanOrEqual(4);
  });

  test('stock receipt — empty detail table', async () => {
    await gotoRoute(page, 'receipt');
    await clickNuevo(page);
    await expect(page.locator('.nb-form__detail-table').first()).toBeVisible({ timeout: 10000 });
    await shot(page, 'stock-receipt-form-detail');
  });

  test('credit note — form shell', async () => {
    await gotoRoute(page, 'credit-note');
    await clickNuevo(page);
    const detailTable = page.locator('.nb-form__detail-table').first();
    if (await detailTable.isVisible({ timeout: 5000 }).catch(() => false)) {
      await expect(detailTable.locator('thead')).toBeVisible();
    }
    await shot(page, 'credit-note-form-detail');
  });
});