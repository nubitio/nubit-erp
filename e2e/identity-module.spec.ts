import { createHmac } from 'node:crypto';
import {
  expect,
  request as playwrightRequest,
  test,
  type APIRequestContext,
} from '@playwright/test';
import { login as uiLogin } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

/**
 * Identity module (nubit/admin-bundle): invitations, second factor (TOTP), password recovery.
 *
 * Needs the module switched on at BOTH ends (it is compiled into the container):
 *   server: IDENTITY_MODULE_ENABLED=1 (+ cache rebuild)    runner: IDENTITY_MODULE_ENABLED=1
 * and a mail catcher: MAILPIT_URL (default http://localhost:8026). Skipped otherwise.
 *
 * Everything that enrols a second factor or changes a password happens on a user created by this
 * spec, never on the seeded one, so a failure cannot lock out the shared test account.
 */
test.skip(process.env.IDENTITY_MODULE_ENABLED !== '1', 'identity module is off');
test.describe.configure({ mode: 'serial' });

const BASE = getE2EBaseUrl();
const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8026';
const PASSWORD = 'Qa-pass-1234';
const NEW_PASSWORD = 'Qa-new-pass-5678';
const UI_PASSWORD = 'Ui-pass-12345';
const UI_NEW_PASSWORD = 'Ui-new-pass-67890';

// ── RFC 6238 (SHA-1, 6 digits, 30 s) ─────────────────────────────────────────
function base32Decode(input: string): Buffer {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const char of input.replace(/=+$/, '').toUpperCase())
    bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
}
function totp(secret: string, stepOffset = 0): string {
  const counter = Math.floor(Date.now() / 30_000) + stepOffset;
  const buffer = Buffer.alloc(8);
  buffer.writeBigUInt64BE(BigInt(counter));
  const hmac = createHmac('sha1', base32Decode(secret)).update(buffer).digest();
  const offset = hmac[hmac.length - 1] & 0xf;
  const code = ((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).toString();
  return code.padStart(6, '0');
}

// ── helpers ──────────────────────────────────────────────────────────────────
async function csrf(api: APIRequestContext): Promise<Record<string, string>> {
  const state = await api.storageState();
  const token = state.cookies.find((c) => c.name === 'CSRF_TOKEN')?.value;
  return token ? { 'X-CSRF-Token': decodeURIComponent(token) } : {};
}
async function newApi(): Promise<APIRequestContext> {
  return playwrightRequest.newContext({ baseURL: BASE });
}
async function login(
  api: APIRequestContext,
  username: string,
  password: string,
  totpCode?: string,
) {
  return api.post('/api/auth/login', {
    data: { username, password, ...(totpCode ? { totpCode } : {}) },
  });
}
async function post(api: APIRequestContext, url: string, data?: unknown) {
  return api.post(url, { data, headers: await csrf(api) });
}
async function mailTo(address: string, subject = ''): Promise<string> {
  const query = `to:${address}${subject ? ` subject:${subject}` : ''}`;
  for (let attempt = 0; attempt < 30; attempt += 1) {
    const list = (await (
      await fetch(`${MAILPIT}/api/v1/search?query=${encodeURIComponent(query)}`)
    ).json()) as { messages: { ID: string }[] };
    if (list.messages.length > 0) {
      const message = (await (
        await fetch(`${MAILPIT}/api/v1/message/${list.messages[0].ID}`)
      ).json()) as { Text: string };
      return message.Text;
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  throw new Error(`no e-mail for ${query}`);
}
const tokenFrom = (text: string): string => /token=([a-f0-9]+)/.exec(text)?.[1] ?? '';

const stamp = Date.now();
const email = `qa.${stamp}@example.test`;
let admin: APIRequestContext;
let username = '';
let userId = 0;
let uiUserId = 0;
let secret = '';
let recoveryCodes: string[] = [];

test.beforeAll(async () => {
  admin = await newApi();
  const seed = await getE2ESeed();
  const response = await login(admin, seed.username, seed.password);
  expect(response.status(), 'seeded admin must still be able to sign in with the module ON').toBe(
    200,
  );
});
test.afterAll(async () => {
  for (const id of [userId, uiUserId]) {
    if (id) await admin.delete(`/api/users/${id}`, { headers: await csrf(admin) });
  }
  await admin.dispose();
});

test('baseline: a user with no second factor signs in exactly as before', async () => {
  const seed = await getE2ESeed();
  const api = await newApi();
  expect((await login(api, seed.username, seed.password)).status()).toBe(200);
  expect((await login(api, seed.username, 'wrong-password')).status()).toBe(401);
  await api.dispose();
});

test.describe('invitations', () => {
  let inviteToken = '';

  test('an administrator invites by e-mail and the token travels only in the mail', async () => {
    const response = await post(admin, '/api/user-invitations', { email, roleId: 3 });
    expect(response.status()).toBe(201);
    const body = await response.json();
    expect(body.email).toBe(email);
    expect(JSON.stringify(body)).not.toMatch(/token/i);
    inviteToken = tokenFrom(await mailTo(email));
    expect(inviteToken).toHaveLength(64);
  });

  test('inviting an address that already has a pending account state is validated', async () => {
    expect(
      (await post(admin, '/api/user-invitations', { email: 'not-an-email', roleId: 3 })).status(),
    ).toBe(409);
    expect((await post(admin, '/api/user-invitations', { email, roleId: 99999 })).status()).toBe(
      422,
    );
  });

  test('the public preview and accept endpoints work without a session', async () => {
    const anon = await newApi();
    const preview = await anon.get(`/api/invitations/${inviteToken}`);
    expect(preview.status()).toBe(200);
    expect((await preview.json()).email).toBe(email);

    expect(
      (
        await anon.post(`/api/invitations/${inviteToken}/accept`, { data: { password: 'short' } })
      ).status(),
    ).toBe(400);
    expect((await anon.get('/api/invitations/' + 'f'.repeat(64))).status()).toBe(404);

    const accept = await anon.post(`/api/invitations/${inviteToken}/accept`, {
      data: { password: PASSWORD },
    });
    expect(accept.status()).toBe(201);
    username = (await accept.json()).username;
    expect(username).toBe(`qa.${stamp}`);

    // single use
    expect(
      (
        await anon.post(`/api/invitations/${inviteToken}/accept`, { data: { password: PASSWORD } })
      ).status(),
    ).toBe(400);
    await anon.dispose();
  });

  test('the invited user signs in, with the invited role, and cannot invite others', async () => {
    const api = await newApi();
    expect((await login(api, username, PASSWORD)).status()).toBe(200);
    expect(
      (
        await post(api, '/api/user-invitations', { email: `x.${stamp}@example.test`, roleId: 3 })
      ).status(),
    ).toBe(403);
    await api.dispose();

    const list = await admin.get('/api/users?itemsPerPage=200');
    const users = (await list.json()) as {
      id: number;
      username: string;
      email?: string;
      role: { id: number };
    }[];
    const created = users.find((u) => u.username === username);
    expect(created?.email).toBe(email);
    expect(created?.role.id).toBe(3);
    userId = created?.id ?? 0;
  });

  test('inviting an address that already has an account is refused', async () => {
    expect((await post(admin, '/api/user-invitations', { email, roleId: 3 })).status()).toBe(409);
  });
});

test.describe('second factor', () => {
  test('a pending enrolment does NOT change login (no lockout by abandoning the QR)', async () => {
    const api = await newApi();
    await login(api, username, PASSWORD);
    expect((await (await api.get('/api/auth/totp')).json()).enrolled).toBe(false);
    const begin = await post(api, '/api/auth/totp');
    expect(begin.status()).toBe(201);
    const body = await begin.json();
    secret = body.secret;
    recoveryCodes = body.recoveryCodes;
    expect(recoveryCodes).toHaveLength(10);

    const other = await newApi();
    expect(
      (await login(other, username, PASSWORD)).status(),
      'unconfirmed credential must not be enforced',
    ).toBe(200);
    await other.dispose();

    expect((await post(api, '/api/auth/totp/confirm', { code: '000000' })).status()).toBe(400);
    expect((await post(api, '/api/auth/totp/confirm', { code: totp(secret) })).status()).toBe(200);
    expect((await (await api.get('/api/auth/totp')).json()).enrolled).toBe(true);
    await api.dispose();
  });

  test('once confirmed, the code is required and replays are refused', async () => {
    const api = await newApi();
    const noCode = await login(api, username, PASSWORD);
    expect(noCode.status()).toBe(401);
    expect((await noCode.json()).message).toBe('Second factor required.');

    expect((await login(api, username, PASSWORD, '000000')).status()).toBe(401);
    expect(
      (await login(api, username, 'wrong-password', totp(secret, 1))).status(),
      'wrong password never reaches the code check',
    ).toBe(401);

    const code = totp(secret, 1); // the enrolment consumed the current window
    expect((await login(api, username, PASSWORD, code)).status()).toBe(200);
    expect(
      (await login(await newApi(), username, PASSWORD, code)).status(),
      'a spent code cannot be replayed',
    ).toBe(401);
    await api.dispose();
  });

  test('a recovery code signs in once and is then burned', async () => {
    const code = recoveryCodes[0];
    expect((await login(await newApi(), username, PASSWORD, code)).status()).toBe(200);
    expect((await login(await newApi(), username, PASSWORD, code)).status()).toBe(401);
  });

  test('the user can turn it off with a recovery code and then signs in with the password alone', async () => {
    const api = await newApi();
    expect((await login(api, username, PASSWORD, recoveryCodes[1])).status()).toBe(200);
    const stolenCookie = await api.delete('/api/auth/totp', {
      data: { code: '000000' },
      headers: await csrf(api),
    });
    expect(stolenCookie.status(), 'a session alone must not be able to disable the factor').toBe(
      400,
    );
    expect(
      (
        await api.delete('/api/auth/totp', {
          data: { code: recoveryCodes[2] },
          headers: await csrf(api),
        })
      ).status(),
    ).toBe(204);
    expect((await login(await newApi(), username, PASSWORD)).status()).toBe(200);
    await api.dispose();
  });

  test('an administrator can reset a colleague who lost the device', async () => {
    const api = await newApi();
    await login(api, username, PASSWORD);
    const begin = await (await post(api, '/api/auth/totp')).json();
    await post(api, '/api/auth/totp/confirm', { code: totp(begin.secret) });
    expect((await login(await newApi(), username, PASSWORD)).status()).toBe(401);

    expect(
      (await admin.delete(`/api/users/${userId}/totp`, { headers: await csrf(admin) })).status(),
    ).toBe(204);
    expect((await login(await newApi(), username, PASSWORD)).status()).toBe(200);

    // a cashier may not reset anyone
    const cashier = await newApi();
    await login(cashier, username, PASSWORD);
    expect(
      (
        await cashier.delete(`/api/users/${userId}/totp`, { headers: await csrf(cashier) })
      ).status(),
    ).toBe(403);
    await cashier.dispose();
    await api.dispose();
  });
});

test.describe('password recovery', () => {
  test('forgot-password never reveals whether the account exists', async () => {
    const anon = await newApi();
    const known = await anon.post('/api/auth/password/forgot', { data: { username } });
    const unknown = await anon.post('/api/auth/password/forgot', {
      data: { username: `nobody.${stamp}` },
    });
    expect(known.status()).toBe(204);
    expect(unknown.status()).toBe(204);
    await anon.dispose();
  });

  test('the mailed link resets the password once', async () => {
    const resetMail = await mailTo(email, 'Restablece');
    const token = tokenFrom(resetMail);
    expect(token).toHaveLength(64);

    const anon = await newApi();
    expect(
      (
        await anon.post('/api/auth/password/reset', { data: { token, password: 'short' } })
      ).status(),
    ).toBe(400);
    expect(
      (
        await anon.post('/api/auth/password/reset', { data: { token, password: NEW_PASSWORD } })
      ).status(),
    ).toBe(200);
    expect(
      (
        await anon.post('/api/auth/password/reset', { data: { token, password: NEW_PASSWORD } })
      ).status(),
      'single use',
    ).toBe(400);
    await anon.dispose();

    expect((await login(await newApi(), username, NEW_PASSWORD)).status()).toBe(200);
    expect((await login(await newApi(), username, PASSWORD)).status()).toBe(401);
  });
});

test.describe('login form (browser)', () => {
  test('asks for the code after a correct password, rejects a wrong one, accepts the right one', async ({
    page,
  }) => {
    const api = await newApi();
    await login(api, username, NEW_PASSWORD);
    const begin = await (await post(api, '/api/auth/totp')).json();
    await post(api, '/api/auth/totp/confirm', { code: totp(begin.secret) });
    await api.dispose();

    await page.goto(`${BASE}/#/login`);
    await expect(page.locator('form.login-form')).toBeVisible({ timeout: 25_000 });
    await page.locator('#login-username').fill(username);
    await page.locator('#login-password').fill(NEW_PASSWORD);
    await page.getByRole('button', { name: 'Ingresar' }).click();

    // The password was right, so this is a step, not an error.
    await expect(page.locator('#login-totp')).toBeVisible({ timeout: 15_000 });
    await expect(page.getByRole('alert')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Verificar' })).toBeVisible();

    await page.locator('#login-totp').fill('000000');
    await page.getByRole('button', { name: 'Verificar' }).click();
    await expect(page.getByRole('alert')).toContainText('El código no es válido');
    await expect(page.locator('#login-totp')).toBeVisible();

    await page.locator('#login-totp').fill(totp(begin.secret, 1));
    await page.getByRole('button', { name: 'Verificar' }).click();
    // Past password AND second factor: the next (normal) step is choosing the branch.
    await expect(page.getByRole('dialog', { name: 'Seleccionar local' })).toBeVisible({
      timeout: 20_000,
    });
  });
});

test.describe('invitation and recovery pages (browser)', () => {
  const uiEmail = `ui.${stamp}@example.test`;
  const uiUsername = `ui.${stamp}`;

  test('the invitation link lets the invitee choose a password', async ({ page }) => {
    expect(
      (await post(admin, '/api/user-invitations', { email: uiEmail, roleId: 2 })).status(),
    ).toBe(201);
    const token = tokenFrom(await mailTo(uiEmail, 'invitaron'));

    await page.goto(`${BASE}/#/accept-invitation?token=${token}`);
    await expect(page.getByText(`Crea tu contraseña para ${uiEmail}`)).toBeVisible({
      timeout: 25_000,
    });

    await page.getByLabel('Nueva contraseña').fill(UI_PASSWORD);
    await page.getByLabel('Repite la contraseña').fill('does-not-match');
    await page.getByRole('button', { name: 'Crear cuenta' }).click();
    await expect(page.getByRole('alert')).toContainText('no coinciden');

    await page.getByLabel('Repite la contraseña').fill(UI_PASSWORD);
    await page.getByRole('button', { name: 'Crear cuenta' }).click();
    await expect(page.getByText('Tu cuenta está lista')).toBeVisible({ timeout: 15_000 });
    await expect(page.locator('strong', { hasText: uiUsername })).toBeVisible();

    // the link is single use
    await page.goto(`${BASE}/#/accept-invitation?token=${token}`);
    await page.reload(); // a fresh load, as when opening the e-mail link again
    await expect(page.getByText('Invitación no válida')).toBeVisible({ timeout: 15_000 });

    const users = (await (await admin.get('/api/users?itemsPerPage=200')).json()) as {
      id: number;
      username: string;
    }[];
    uiUserId = users.find((u) => u.username === uiUsername)?.id ?? 0;
    expect(uiUserId).toBeGreaterThan(0);
  });

  test('a broken invitation link says so instead of showing a form', async ({ page }) => {
    await page.goto(`${BASE}/#/accept-invitation?token=${'e'.repeat(64)}`);
    await expect(page.getByText('Invitación no válida')).toBeVisible({ timeout: 25_000 });
    await expect(page.getByLabel('Nueva contraseña')).toHaveCount(0);
  });

  test('"forgot password" from the login page ends with a working new password', async ({
    page,
  }) => {
    await page.goto(`${BASE}/#/login`);
    await page.getByRole('link', { name: '¿Olvidaste tu contraseña?' }).click();
    await page.getByLabel('Usuario o correo').fill(uiUsername);
    await page.getByRole('button', { name: 'Enviar enlace' }).click();
    await expect(page.getByText('Si la cuenta existe')).toBeVisible({ timeout: 15_000 });

    const token = tokenFrom(await mailTo(uiEmail, 'Restablece'));
    await page.goto(`${BASE}/#/reset-password?token=${token}`);
    await page.getByLabel('Nueva contraseña').fill('short');
    await page.getByLabel('Repite la contraseña').fill('short');
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(page.getByRole('alert')).toContainText('al menos 8');

    await page.getByLabel('Nueva contraseña').fill(UI_NEW_PASSWORD);
    await page.getByLabel('Repite la contraseña').fill(UI_NEW_PASSWORD);
    await page.getByRole('button', { name: 'Guardar contraseña' }).click();
    await expect(page.getByText('Tu contraseña se actualizó')).toBeVisible({ timeout: 15_000 });

    expect((await login(await newApi(), uiUsername, UI_NEW_PASSWORD)).status()).toBe(200);
    expect((await login(await newApi(), uiUsername, UI_PASSWORD)).status()).toBe(401);
  });
});

test.describe('two-factor settings (browser)', () => {
  const uiUsername = `ui.${stamp}`;

  test('a user turns the second factor on from the menu, login then needs it, and can turn it off', async ({
    page,
  }) => {
    await uiLogin(page, uiUsername, UI_NEW_PASSWORD);
    await page.goto(`${BASE}/#/dashboard`); // the user menu lives in the admin shell, not the full-screen POS
    await page.getByRole('button', { name: 'Menú de usuario' }).click();
    await page.getByText('Verificación en dos pasos').click();
    const dialog = page.getByRole('dialog', { name: 'Verificación en dos pasos' });
    await expect(dialog).toBeVisible();

    await dialog.getByRole('button', { name: 'Activar' }).click();
    await expect(dialog.getByTestId('two-factor-secret')).toBeVisible();
    const secret = (await dialog.getByTestId('two-factor-secret').innerText()).replace(/\s/g, '');
    const codes = await dialog
      .getByTestId('two-factor-recovery-codes')
      .locator('li')
      .allInnerTexts();
    expect(codes).toHaveLength(10);

    // not in force until a real code proves the app works
    expect((await login(await newApi(), uiUsername, UI_NEW_PASSWORD)).status()).toBe(200);

    await dialog.getByLabel('Código de 6 dígitos').fill('000000');
    await dialog.getByRole('button', { name: 'Activar' }).click();
    await expect(dialog.getByRole('alert')).toContainText('El código no es válido');

    await dialog.getByLabel('Código de 6 dígitos').fill(totp(secret));
    await dialog.getByRole('button', { name: 'Activar' }).click();
    await expect(dialog.getByTestId('two-factor-enabled')).toBeVisible({ timeout: 15_000 });
    expect((await login(await newApi(), uiUsername, UI_NEW_PASSWORD)).status()).toBe(401);

    await dialog.getByRole('button', { name: 'Desactivar' }).click();
    await dialog.getByLabel('Código de verificación').fill('000000');
    await dialog.getByRole('button', { name: 'Desactivar' }).click();
    await expect(dialog.getByRole('alert')).toContainText('El código no es válido');
    expect(
      (await login(await newApi(), uiUsername, UI_NEW_PASSWORD)).status(),
      'still on after a bad code',
    ).toBe(401);

    await dialog.getByLabel('Código de verificación').fill(codes[0]);
    await dialog.getByRole('button', { name: 'Desactivar' }).click();
    await expect(dialog.getByTestId('two-factor-enabled')).toHaveCount(0, { timeout: 15_000 });
    expect((await login(await newApi(), uiUsername, UI_NEW_PASSWORD)).status()).toBe(200);
  });
});

test.describe('invite dialog (browser)', () => {
  test('an administrator invites from the users screen', async ({ page }) => {
    const seed = await getE2ESeed();
    const target = `dlg.${stamp}@example.test`;
    await uiLogin(page, seed.username, seed.password);
    await page.goto(`${BASE}/#/config/users`);
    await page.getByRole('button', { name: 'Invitar usuario' }).click();
    const dialog = page.getByRole('dialog', { name: 'Invitar usuario' });
    await expect(dialog).toBeVisible();

    // the super administrator role is not offered here
    await expect(dialog.getByRole('option', { name: 'Super Administrador' })).toHaveCount(0);
    await expect(dialog.getByRole('button', { name: 'Enviar invitación' })).toBeDisabled();

    await dialog.getByLabel('Correo').fill('not-an-email');
    await dialog.getByLabel('Rol').selectOption({ label: 'Cajero' });
    await dialog.getByRole('button', { name: 'Enviar invitación' }).click();
    await expect(dialog.getByRole('alert')).toContainText('correo válido');

    await dialog.getByLabel('Correo').fill(target);
    await dialog.getByRole('button', { name: 'Enviar invitación' }).click();
    await expect(dialog).toBeHidden({ timeout: 15_000 });

    const mail = await mailTo(target, 'invitaron');
    expect(mail).toContain('#/accept-invitation?token=');
  });
});
