import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { completePosCashPayment, waitForPosCheckoutReady } from './helpers/pos';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import { E2E_SEED } from './support/seedConstants';

const BASE_URL = getE2EBaseUrl();

type CashPosState = {
  session?: { id: number; cashRegisterName: string; openingAmount: string } | null;
  availableRegisters: Array<{ id: number; name: string }>;
};

async function posState(page: Page): Promise<CashPosState> {
  const response = await page.request.get(`${BASE_URL}/api/cash-pos-state`, {
    headers: { Accept: 'application/json' },
  });
  expect(response.ok(), 'cash-pos-state debe responder 2xx').toBeTruthy();
  return (await response.json()) as CashPosState;
}

/** The badge shows "Verificando" until the server answers; wait for the real answer. */
async function waitForCajaState(page: Page, state: 'Abierta' | 'Cerrada') {
  await expect(page.getByTestId('pos-caja-badge')).toContainText(state, { timeout: 30_000 });
}

async function openPos(page: Page) {
  await page.goto(`${BASE_URL}/pos`);
  await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25_000 });
  await expect(page.getByTestId('pos-caja-badge')).not.toContainText('Verificando', {
    timeout: 30_000,
  });
}

async function openMenu(page: Page, item: RegExp) {
  await page.getByRole('button', { name: 'Más opciones' }).click();
  await page.getByRole('menuitem', { name: item }).click();
}

/** Opens the register from the POS dialog with the given opening cash. */
async function openCajaFromPos(page: Page, openingCash: string) {
  await page.getByRole('button', { name: 'Abrir caja' }).first().click();
  const dialog = page.getByRole('dialog', { name: 'Abrir caja' });
  await expect(dialog).toBeVisible();
  await dialog.getByLabel(/Efectivo inicial/).fill(openingCash);
  await dialog.getByTestId('cash-open-submit-btn').click();
  await expect(dialog).toBeHidden({ timeout: 15_000 });
  await waitForCajaState(page, 'Abierta');
}

/**
 * Counts what the system expects and closes from the POS dialog. Without `countedCash` the count is
 * whatever the dialog says is expected, so a session opened by another spec closes with no difference.
 */
async function closeCajaFromPos(page: Page, countedCash?: string) {
  await openMenu(page, /Cerrar caja/);
  const dialog = page.getByRole('dialog', { name: 'Cerrar caja' });
  await expect(dialog).toBeVisible();
  const expected = dialog.getByText('Esperado en efectivo').locator('..');
  await expect(expected).toContainText(/S\/\s*[\d.,]+/, { timeout: 20_000 });
  const counted =
    countedCash ??
    (await expected.innerText()).match(/S\/\s*([\d.,]+)/)?.[1]?.replace(/,/g, '') ??
    '0';
  await dialog.getByRole('button', { name: 'Ingresar importe' }).click();
  await dialog.getByRole('spinbutton', { name: 'Monto contado de Efectivo' }).fill(counted);
  const closeButton = dialog.getByTestId('cash-close-btn');
  await expect(closeButton).toBeEnabled();
  await closeButton.click();

  const confirm = page.getByRole('alertdialog', { name: 'Cerrar caja' });
  await expect(confirm).toContainText('El conteo coincide con lo esperado');
  const closeResponse = page.waitForResponse(
    (response) =>
      /\/api\/cash-sessions\/\d+\/close$/.test(response.url()) &&
      response.request().method() === 'POST',
  );
  await confirm.getByRole('button', { name: 'Sí' }).click();
  const closed = await closeResponse;
  expect(closed.ok(), `cierre: ${closed.status()} ${await closed.text()}`).toBeTruthy();
  await expect(dialog).toBeHidden({ timeout: 20_000 });
  await waitForCajaState(page, 'Cerrada');
}

test('el cajero abre, mueve efectivo y cierra la caja sin salir del POS', async ({ page }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await openPos(page);

  await test.step('si la caja venía abierta, se cierra desde el POS para partir de cero', async () => {
    if (
      await page
        .getByTestId('pos-caja-badge')
        .innerText()
        .then((text) => text.includes('Abierta'))
    ) {
      await closeCajaFromPos(page);
    }
    await expect(page.getByRole('button', { name: 'Abrir caja' }).first()).toBeVisible();
    expect((await posState(page)).session ?? null).toBeNull();
  });

  await test.step('con la caja cerrada no se puede cobrar y no hay acciones de caja', async () => {
    await expect(page.getByTestId('pos-open-payment-btn')).toBeDisabled();
    await page.getByRole('button', { name: 'Más opciones' }).click();
    await expect(page.getByRole('menuitem', { name: /Cerrar caja/ })).toHaveCount(0);
    await expect(page.getByRole('menuitem', { name: /Movimientos y reporte X/ })).toHaveCount(0);
    await page.keyboard.press('Escape');
  });

  await test.step('abre la caja desde el POS: el servidor registra el efectivo inicial', async () => {
    await openCajaFromPos(page, '150');

    const state = await posState(page);
    expect(state.session?.openingAmount).toBe('150.00');
    expect(state.availableRegisters).toEqual([]);
    await expect(page.getByTestId('pos-caja-badge')).toContainText(
      state.session?.cashRegisterName ?? '',
    );
  });

  await test.step('registra un egreso de caja y el corte parcial lo refleja', async () => {
    await openMenu(page, /Movimientos y reporte X/);
    const dialog = page.getByRole('dialog', { name: 'Movimientos de caja' });
    await expect(dialog.getByText('Saldo en caja')).toBeVisible({ timeout: 15_000 });

    await dialog.getByRole('combobox', { name: 'Categoría' }).click();
    await page.getByRole('option', { name: /Gasto de caja chica/ }).click();
    await dialog.getByLabel(/Monto/).fill('12.50');
    await dialog.getByLabel(/Descripción/).fill('Bolsas y cinta de embalaje');
    await dialog.getByTestId('cash-movement-submit-btn').click();

    await expect(dialog.getByTestId('cash-movements-list')).toContainText('Bolsas y cinta');
    await expect(dialog.getByTestId('cash-movements-list')).toContainText('12.50');
    await expect(dialog.getByText('S/ 137.50')).toBeVisible();
    await dialog.getByRole('button', { name: 'Cerrar', exact: true }).last().click();
    await expect(dialog).toBeHidden();
  });

  await test.step('el cierre lo cuenta el cajero y el servidor cierra la sesión', async () => {
    await closeCajaFromPos(page, '137.50');

    const state = await posState(page);
    expect(state.session ?? null).toBeNull();
    expect(state.availableRegisters.length).toBeGreaterThan(0);
  });

  await test.step('deja la caja abierta para el resto de la suite', async () => {
    await openCajaFromPos(page, '100');
  });
});

test('un estado de caja desactualizado se corrige: el POS cierra lo que el servidor ya cerró', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await openPos(page);
  if ((await posState(page)).session == null) await openCajaFromPos(page, '100');
  await waitForCajaState(page, 'Abierta');

  // The session is closed behind the POS's back (an administrator, another device).
  const sessionId = (await posState(page)).session?.id;
  const summary = await page.request.get(`${BASE_URL}/api/cash-sessions/${sessionId}/summary`);
  expect(summary.ok()).toBeTruthy();
  const rows = (await summary.json()) as Array<{ paymentMethodId: number; expectedAmount: string }>;
  const csrf = (await page.context().cookies()).find((cookie) => cookie.name === 'CSRF_TOKEN');
  const close = await page.request.post(`${BASE_URL}/api/cash-sessions/${sessionId}/close`, {
    headers: { 'X-CSRF-Token': csrf?.value ?? '' },
    data: {
      closureItems: rows.map((row) => ({
        cashSession: `/api/cash-sessions/${sessionId}`,
        paymentMethod: `/api/payment-methods/${row.paymentMethodId}`,
        expectedAmount: Number(row.expectedAmount),
        countedAmount: Number(row.expectedAmount),
        difference: 0,
      })),
    },
  });
  expect(close.ok(), `cierre por API: ${close.status()} ${await close.text()}`).toBeTruthy();

  // Coming back to the tab (visibilitychange) revalidates: the POS must not keep claiming the caja is open.
  await page.evaluate(() =>
    document.dispatchEvent(new Event('visibilitychange', { bubbles: true })),
  );
  await waitForCajaState(page, 'Cerrada');
  await expect(page.getByTestId('pos-open-payment-btn')).toBeDisabled();

  await openCajaFromPos(page, '100');
});

type ShiftSales = {
  sessionId: number | null;
  count: number;
  total: string;
  sales: Array<{ id: string; status: string; total: string; payments: unknown[] }>;
};

async function shiftSales(page: Page): Promise<ShiftSales> {
  const response = await page.request.get(`${BASE_URL}/api/cash-pos-state/sales`, {
    headers: { Accept: 'application/json' },
  });
  expect(response.ok(), 'cash-pos-state/sales debe responder 2xx').toBeTruthy();
  return (await response.json()) as ShiftSales;
}

test('el cajero ve las ventas de su turno, las reimprime y las encuentra al cerrar', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await openPos(page);
  if ((await posState(page)).session == null) await openCajaFromPos(page, '100');
  await waitForCajaState(page, 'Abierta');

  const before = await shiftSales(page);
  expect(before.sessionId).toBe((await posState(page)).session?.id);

  await test.step('vende en efectivo y la venta entra al turno', async () => {
    await page
      .getByTestId('pos-product-card')
      .filter({ hasText: E2E_SEED.productName })
      .filter({ hasNotText: E2E_SEED.product2Name })
      .first()
      .click();
    await completePosCashPayment(page);
    await waitForPosCheckoutReady(page);
    const sold = page.waitForResponse(
      (response) =>
        /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
    );
    await page.getByTestId('pos-checkout-btn').click();
    expect((await sold).ok()).toBeTruthy();
    // The cashier dismisses the "comprobante emitido" dialog to start the next sale.
    await page.getByRole('button', { name: /Nueva venta/ }).click();

    await expect.poll(async () => (await shiftSales(page)).count).toBe(before.count + 1);
  });

  await test.step('el menú del POS muestra la venta con su método de pago', async () => {
    await openMenu(page, /Ventas del turno/);
    const dialog = page.getByRole('dialog', { name: 'Ventas del turno' });
    await expect(dialog.getByRole('status')).toContainText(`${before.count + 1} venta`, {
      timeout: 15_000,
    });
    const list = dialog.getByTestId('cash-shift-sales-list');
    await expect(list.getByText('Efectivo').first()).toBeVisible();
    await expect(list.getByRole('button', { name: 'Reimprimir' }).first()).toBeVisible();
    await dialog.getByRole('button', { name: 'Cerrar', exact: true }).last().click();
    await expect(dialog).toBeHidden();
  });

  await test.step('al cerrar, el cierre cuenta las operaciones y deja ver las ventas', async () => {
    await openMenu(page, /Cerrar caja/);
    const close = page.getByRole('dialog', { name: 'Cerrar caja' });
    const methods = close.getByRole('table').first();
    await expect(methods.getByRole('columnheader', { name: 'Operaciones' })).toBeVisible({
      timeout: 20_000,
    });
    await expect(methods.getByRole('row', { name: /Efectivo/ })).toBeVisible();

    await close.getByTestId('cash-close-view-sales').click();
    const sales = page.getByRole('dialog', { name: 'Ventas del turno' });
    await expect(sales.getByTestId('cash-shift-sales-list')).toBeVisible({ timeout: 15_000 });
    await sales.getByRole('button', { name: 'Cerrar', exact: true }).last().click();
    await expect(sales).toBeHidden();
    // The count dialog is still there: the cashier goes back to it and keeps selling.
    await expect(close).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(close).toBeHidden();
  });
});

test('Cajas y turnos vive en Finanzas, no como ítem suelto', async ({ page }) => {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, seed.username, seed.password);
  await page.goto(`${BASE_URL}/dashboard`);

  const sidebar = page.getByRole('navigation').first();
  await expect(sidebar.getByRole('button', { name: 'Finanzas', exact: true })).toBeVisible({
    timeout: 25_000,
  });
  await expect(sidebar.getByRole('button', { name: 'Caja', exact: true })).toHaveCount(0);

  const finance = sidebar.getByRole('button', { name: 'Finanzas', exact: true });
  await expect(async () => {
    if ((await finance.getAttribute('aria-expanded')) !== 'true') await finance.click();
    await expect(finance).toHaveAttribute('aria-expanded', 'true', { timeout: 1_000 });
  }).toPass({ timeout: 10_000 });

  await sidebar.getByRole('button', { name: 'Cajas y turnos' }).click();
  await expect(page).toHaveURL(/\/cashbox\/dashboard/);
  // The sections stay reachable from the page itself: they are not sidebar sub-items.
  for (const section of ['Cajas', 'Registros', 'Categorías']) {
    await expect(page.getByText(section, { exact: true }).first()).toBeVisible();
  }
});
