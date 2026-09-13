import { expect, type Page } from '@playwright/test';
import { E2E_SEED } from '../support/seedConstants';
import { getE2EBaseUrl } from '../support/e2eSeed';

async function fillDxInput(page: Page, ariaLabel: string, value: string) {
  const loginInputIds: Record<string, string> = {
    Usuario: '#login-username',
    'Contraseña': '#login-password',
  };
  const input = page
    .locator(
      loginInputIds[ariaLabel]
        ? `${loginInputIds[ariaLabel]}:visible`
        : `input[aria-label="${ariaLabel}"], textarea[aria-label="${ariaLabel}"], [role="textbox"][aria-label="${ariaLabel}"]`,
    )
    .first();
  await input.waitFor({ state: 'visible', timeout: 8000 });
  await input.fill(value);
  await page.keyboard.press('Tab');
}

async function waitForPostLoginLanding(page: Page) {
  await page
    .waitForFunction(
      () => {
        const hash = window.location.hash ?? '';
        const loginForm = document.querySelector('form.login-form');
        return !hash.includes('/login') && !loginForm;
      },
      undefined,
      { timeout: 30000 },
    )
    .catch(() => {});

  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
}

/** Log in and complete branch selection when the picker appears. */
export async function login(page: Page, username: string, password: string) {
  const baseUrl = getE2EBaseUrl();

  await page.goto(`${baseUrl}/#/login`);
  await page.waitForLoadState('domcontentloaded', { timeout: 15000 }).catch(() => {});
  await expect(page.locator('form.login-form')).toBeVisible({ timeout: 25000 });

  await fillDxInput(page, 'Usuario', username);
  await fillDxInput(page, 'Contraseña', password);

  const loginResponsePromise = page.waitForResponse(
    (response) =>
      response.url().includes('/api/auth/login') && response.request().method() === 'POST',
    { timeout: 20000 },
  );

  await page.locator('form.login-form').getByRole('button', { name: 'Ingresar' }).click();

  const loginResponse = await loginResponsePromise;
  expect(loginResponse.ok(), `login API debe responder 2xx (status ${loginResponse.status()})`).toBeTruthy();

  try {
    const firstBranch = page.locator('[data-testid="branch-picker-option"]').first();
    await firstBranch.waitFor({ state: 'visible', timeout: 7000 });
    const e2eBranch = page
      .locator('[data-testid="branch-picker-option"]')
      .filter({ hasText: E2E_SEED.branchName });
    const target = (await e2eBranch.count()) > 0 ? e2eBranch.first() : firstBranch;
    await target.click();
    await page.waitForTimeout(300);
    const acceptBtn = page
      .getByRole('dialog', { name: 'Seleccionar local' })
      .getByRole('button', { name: /aceptar/i });
    await acceptBtn.waitFor({ state: 'visible', timeout: 5000 });
    await acceptBtn.click();
    await page
      .locator('[data-testid="branch-picker-option"]')
      .first()
      .waitFor({ state: 'hidden', timeout: 10000 });
  } catch {
    // No branch picker — auto-selected.
  }

  await waitForPostLoginLanding(page);

  await ensureBranchContext(page, baseUrl, username, password);

  const onAppRoute =
    page.url().includes('/#/dashboard') ||
    page.url().includes('/#/pos') ||
    !(page.url().includes('/#/login') || (await page.locator('form.login-form').isVisible().catch(() => false)));

  expect(onAppRoute, 'debe salir de /login tras autenticarse').toBeTruthy();
}

/** Ensure the browser holds the branch-bound JWT required by branch-scoped APIs. */
export async function ensureBranchContext(
  page: Page,
  baseUrl = getE2EBaseUrl(),
  username?: string,
  password?: string,
): Promise<void> {
  // UI login stores the token in a React effect. On a redirect/retry that
  // effect can race this check, leaving the page without credentials even
  // though the login request succeeded. Restore it through the API before
  // attempting branch selection; the token is never exposed outside storage.
  const hasToken = await page.evaluate(() => Boolean(window.localStorage.getItem('auth_token')));
  if (!hasToken && username && password) {
    const response = await page.request.post(`${baseUrl}/api/auth/login`, {
      headers: { 'Content-Type': 'application/json', 'X-Client-Type': 'android' },
      data: { username, password },
    });
    expect(response.ok(), `login API debe responder 2xx (status ${response.status()})`).toBeTruthy();
    const payload = (await response.json()) as { token?: string };
    expect(payload.token, 'login debe devolver token').toBeTruthy();
    await page.evaluate((token) => {
      window.localStorage.setItem('auth_token', JSON.stringify(token));
    }, payload.token);
  }

  const result = await page.evaluate(async (base) => {
    const rawToken = window.localStorage.getItem('auth_token');
    // StorageUtils JSON-serializes values, including string JWTs.  The
    // browser API helper must send the JWT itself, not the JSON string
    // literal (including its surrounding quotes).
    let token: string | null = rawToken;
    if (rawToken) {
      try {
        const parsed = JSON.parse(rawToken) as unknown;
        token = typeof parsed === 'string' ? parsed : rawToken;
      } catch {
        // Accept tokens written directly by older sessions/tests.
      }
    }
    if (!token) return { ok: false, message: 'auth token missing' };
    const headers = { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' };
    const response = await fetch(`${base}/api/branches?itemsPerPage=100`, { headers });
    if (!response.ok) return { ok: false, message: `branches returned ${response.status}` };
    const payload = await response.json();
    const branches = Array.isArray(payload)
      ? payload
      : (payload.member ?? payload['hydra:member'] ?? []);
    const branch = branches.find((candidate: { name?: string; isActive?: boolean }) =>
      candidate.isActive !== false && candidate.name === 'CI E2E Branch',
    ) ?? branches.find((candidate: { isActive?: boolean }) => candidate.isActive !== false);
    if (!branch?.id) return { ok: false, message: 'no active branch available' };
    const selection = await fetch(`${base}/api/auth/select-branch`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ branchId: String(branch.id), response_mode: 'json' }),
    });
    if (!selection.ok) return { ok: false, message: `branch selection returned ${selection.status}` };
    const selected = await selection.json();
    if (!selected.token) return { ok: false, message: 'branch selection returned no token' };
    // Keep the same JSON-serialized representation used by StorageUtils;
    // subsequent HttpClient requests parse this value before sending it.
    window.localStorage.setItem('auth_token', JSON.stringify(selected.token));
    return { ok: true, message: '' };
  }, baseUrl);
  expect(result.ok, `debe seleccionar una sucursal E2E: ${result.message}`).toBeTruthy();
}
