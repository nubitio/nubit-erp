import { expect, type Page } from '@playwright/test';

type CashRegisterDashboardRow = {
  id?: string | number;
  status?: string;
  cashSessionId?: string | number | null;
  cashShiftId?: string | number | null;
};

async function waitForCashboxGrid(page: Page) {
  await expect(
    page.locator('.nb-datagrid__toolbar').filter({ hasText: /caja/i }).first(),
  ).toBeVisible({
    timeout: 15000,
  });
  await page.locator('.nb-datagrid__row').first().waitFor({ state: 'visible', timeout: 15000 });
}

function cashOpenButton(page: Page) {
  return page
    .getByTestId('cash-btn-open')
    .or(page.locator('.nb-datagrid__row').getByRole('button', { name: /^Abrir$/i }))
    .first();
}

function cashMovementsButton(page: Page) {
  return page
    .getByTestId('cash-btn-movements')
    .or(page.locator('.nb-datagrid__row').getByRole('button', { name: /movimientos/i }))
    .first();
}

/**
 * Ensures an open cash session exists and returns its ID.
 */
export async function ensureOpenCashSession(page: Page, baseUrl: string): Promise<string> {
  await page.goto(`${baseUrl}/#/cashbox`);
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
  await waitForCashboxGrid(page);

  const openBtn = cashOpenButton(page);
  const registerClosed = await openBtn.isVisible({ timeout: 8000 }).catch(() => false);

  if (registerClosed) {
    const dashData = await page.evaluate(async (base: string) => {
      const resp = await fetch(`${base}/api/cash-registers/dashboard`);
      return resp.ok ? resp.json() : [];
    }, baseUrl);
    const closedReg = Array.isArray(dashData)
      ? dashData.find((r: CashRegisterDashboardRow) => (r.cashSessionId ?? r.cashShiftId) == null)
      : null;

    if (closedReg?.id) {
      await page.goto(`${baseUrl}/#/cash-register/${closedReg.id}/open`);
    } else {
      await openBtn.click();
      await page.waitForURL(/\/#\/cash-register\/\d+\/open/, { timeout: 10000 }).catch(() => {});
    }

    await expect(page.locator('text=Apertura de caja')).toBeVisible({ timeout: 10000 });
    await page.getByLabel('Saldo inicial').fill('100');
    await page.getByTestId('cash-open-submit-btn').first().click();
    await page.waitForURL(/\/#\/cash-session\/\d+\/movements/, { timeout: 15000 });
  } else {
    const movementsBtn = cashMovementsButton(page);
    let navigated = false;

    if (await movementsBtn.isVisible({ timeout: 5000 }).catch(() => false)) {
      try {
        await Promise.all([
          page.waitForURL(/\/#\/cash-session\/\d+\/movements/, { timeout: 8000 }),
          movementsBtn.click(),
        ]);
        navigated = true;
      } catch {
        // fall back to dashboard API
      }
    }

    if (!navigated) {
      const dashData = await page.evaluate(async (base: string) => {
        const resp = await fetch(`${base}/api/cash-registers/dashboard`);
        return resp.ok ? resp.json() : [];
      }, baseUrl);
      const openReg = Array.isArray(dashData)
        ? dashData.find(
            (r: CashRegisterDashboardRow) =>
              r.status === 'open' && (r.cashSessionId ?? r.cashShiftId),
          )
        : null;
      const cashSessionId = openReg?.cashSessionId ?? openReg?.cashShiftId;
      if (cashSessionId) {
        await page.goto(`${baseUrl}/#/cash-session/${cashSessionId}/movements`);
      }
    }
  }

  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

  const match = page.url().match(/cash-session\/(\d+)\/movements/);
  expect(match?.[1], 'debe existir una sesión de caja abierta').toBeTruthy();

  return match![1];
}
