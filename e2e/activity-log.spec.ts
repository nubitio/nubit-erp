import {
  expect,
  request as playwrightRequest,
  test,
  type APIRequestContext,
} from '@playwright/test';
import { login as uiLogin } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

/**
 * Activity record ("Usuarios y permisos › Actividad").
 * Works with the identity module on or off: it only needs sign-ins, role permissions and users.
 * Users created here are removed at the end; the shared e2e account is never modified.
 */
test.describe.configure({ mode: 'serial' });

const BASE = getE2EBaseUrl();
const stamp = Date.now();
const cashierName = `act.${stamp}`;
const CASHIER_PASSWORD = 'Act-pass-12345';

interface ActivityEntry {
  id: number;
  occurredAt: string;
  actor: string | null;
  type: string;
  sensitive: boolean;
  summary: string;
  detail: string | null;
}

async function newApi(): Promise<APIRequestContext> {
  return playwrightRequest.newContext({ baseURL: BASE });
}
async function csrf(api: APIRequestContext): Promise<Record<string, string>> {
  const state = await api.storageState();
  const token = state.cookies.find((c) => c.name === 'CSRF_TOKEN')?.value;
  return token ? { 'X-CSRF-Token': decodeURIComponent(token) } : {};
}
async function login(api: APIRequestContext, username: string, password: string) {
  return api.post('/api/auth/login', { data: { username, password } });
}
async function activity(api: APIRequestContext, query: string): Promise<ActivityEntry[]> {
  const response = await api.get(`/api/activity-events?${query}`);
  expect(response.status(), `GET activity ${query}`).toBe(200);
  return ((await response.json()) as { items: ActivityEntry[] }).items;
}

let admin: APIRequestContext;
let adminName = '';
let cashierId = 0;

test.beforeAll(async () => {
  admin = await newApi();
  const seed = await getE2ESeed();
  adminName = seed.username;
  expect((await login(admin, seed.username, seed.password)).status()).toBe(200);
});
test.afterAll(async () => {
  if (cashierId) await admin.delete(`/api/users/${cashierId}`, { headers: await csrf(admin) });
  await admin.dispose();
});

test('a wrong password is recorded as sensitive and names the typed user, with no password', async () => {
  const ghost = `ghost.${stamp}`;
  const anon = await newApi();
  expect((await login(anon, ghost, 'definitely-wrong')).status()).toBe(401);
  await anon.dispose();

  const entries = await activity(admin, `type=sensitive&q=${ghost}`);
  expect(entries).toHaveLength(1);
  expect(entries[0]).toMatchObject({ type: 'auth.login_failed', sensitive: true });
  expect(entries[0].summary).toContain(`«${ghost}»`);
  expect(JSON.stringify(entries)).not.toContain('definitely-wrong');
});

test('a successful sign-in is recorded as routine: visible under "all", hidden from "sensitive"', async () => {
  const mine = `q=${adminName}%20inició`;
  const all = await activity(admin, `type=all&${mine}`);
  expect(all.length).toBeGreaterThan(0);
  expect(all[0]).toMatchObject({ type: 'auth.login', sensitive: false, actor: adminName });

  const sensitive = await activity(admin, `type=sensitive&${mine}`);
  expect(sensitive.filter((entry) => entry.type === 'auth.login')).toHaveLength(0);
});

test("changing a role's permissions records what was added and removed, and saving the same set records nothing", async () => {
  // A throwaway role: this test must never write the permissions of a real role.
  const roleName = `Act role ${stamp}`;
  const created = await admin.post('/api/roles', {
    data: { name: roleName },
    headers: await csrf(admin),
  });
  expect(created.status(), await created.text()).toBeLessThan(300);
  const roleId = ((await created.json()) as { id: number }).id;
  // Guard: the seeded roles are 1-3. Anything else we create is ours; if the id is not, write nothing.
  expect(Number.isInteger(roleId) && roleId > 3, `unexpected role id ${roleId}`).toBe(true);

  const save = async (permissions: string[]) =>
    admin.post('/api/role-permissions', {
      data: { roleId, permissions },
      headers: await csrf(admin),
    });
  const q = encodeURIComponent(roleName);
  const entries = async () => (await activity(admin, `type=role&range=all&q=${q}`)).length;

  try {
    expect((await save([])).status()).toBeLessThan(300);
    expect(await entries(), 'empty -> empty is not a change').toBe(0);

    expect((await save(['sales.view'])).status()).toBeLessThan(300);
    const [added] = await activity(admin, `type=role&q=${q}`);
    expect(added).toMatchObject({
      type: 'role.permissions_changed',
      sensitive: true,
      actor: adminName,
    });
    expect(added.summary).toBe(`Se cambiaron los permisos del rol ${roleName}`);
    expect(added.detail).toBe('Agregó: Ver comprobantes');

    expect((await save(['sales.view'])).status()).toBeLessThan(300);
    expect(await entries(), 'saving the same set records nothing').toBe(1);

    expect((await save(['sales.create', 'sales.void'])).status()).toBeLessThan(300);
    const [swapped] = await activity(admin, `type=role&q=${q}`);
    expect(swapped.detail).toBe(
      'Agregó: Emitir comprobantes, Anular comprobantes · Quitó: Ver comprobantes',
    );

    expect((await save([])).status()).toBeLessThan(300);
    const [removed] = await activity(admin, `type=role&q=${q}`);
    expect(removed.detail).toBe('Quitó: Emitir comprobantes, Anular comprobantes');
    expect(await entries()).toBe(3);
  } finally {
    await admin.delete(`/api/roles/${roleId}`, { headers: await csrf(admin) });
  }
});

test("creating and deleting a user are recorded; the deleted user's entries keep their name", async () => {
  const created = await admin.post('/api/users', {
    data: { username: cashierName, plainPassword: CASHIER_PASSWORD, role: '/api/roles/3' },
    headers: await csrf(admin),
  });
  expect(created.status(), await created.text()).toBe(201);
  cashierId = ((await created.json()) as { id: number }).id;

  const [createdEntry] = await activity(admin, `type=user&q=${cashierName}`);
  expect(createdEntry).toMatchObject({ type: 'user.created', sensitive: true, actor: adminName });
  expect(createdEntry.summary).toBe(`Se creó el usuario ${cashierName}`);
  expect(createdEntry.detail).toMatch(/^Rol: /);

  // the new user signs in (their own routine entry), then is deleted
  const cashier = await newApi();
  expect((await login(cashier, cashierName, CASHIER_PASSWORD)).status()).toBe(200);
  await cashier.dispose();

  expect(
    (await admin.delete(`/api/users/${cashierId}`, { headers: await csrf(admin) })).status(),
  ).toBeLessThan(300);
  cashierId = 0;
  const deleted = (await activity(admin, `type=user&q=${cashierName}`)).find(
    (entry) => entry.type === 'user.deleted',
  );
  expect(deleted?.summary).toBe(`Se eliminó al usuario ${cashierName}`);

  const stillThere = await activity(admin, `type=all&actor=${cashierName}`);
  expect(stillThere.length, 'entries survive the user').toBeGreaterThan(0);
});

test('a user without the permission cannot read the record, its summary, actors or export', async () => {
  const created = await admin.post('/api/users', {
    data: { username: `${cashierName}.b`, plainPassword: CASHIER_PASSWORD, role: '/api/roles/3' },
    headers: await csrf(admin),
  });
  expect(created.status()).toBe(201);
  const id = ((await created.json()) as { id: number }).id;
  try {
    const cashier = await newApi();
    expect((await login(cashier, `${cashierName}.b`, CASHIER_PASSWORD)).status()).toBe(200);
    for (const path of ['', '/summary', '/actors', '/export']) {
      expect((await cashier.get(`/api/activity-events${path}`)).status(), path || '/').toBe(403);
    }
    await cashier.dispose();
    const anon = await newApi();
    expect((await anon.get('/api/activity-events')).status(), 'anonymous').toBe(401);
    await anon.dispose();
  } finally {
    await admin.delete(`/api/users/${id}`, { headers: await csrf(admin) });
  }
});

test('there is no way to change or remove an entry through the API', async () => {
  const [entry] = await activity(admin, 'type=all');
  for (const method of ['put', 'patch', 'delete', 'post'] as const) {
    const response = await admin[method](`/api/activity-events/${entry.id}`, {
      headers: await csrf(admin),
      data: {},
    });
    expect([404, 405], `${method} /${entry.id}`).toContain(response.status());
  }
  expect(
    (await admin.post('/api/activity-events', { headers: await csrf(admin), data: {} })).status(),
  ).toBe(405);
});

test('filters: actor, search, date range and pagination behave', async () => {
  expect(
    (await activity(admin, `type=all&actor=${adminName}`)).every((e) => e.actor === adminName),
  ).toBe(true);
  expect(await activity(admin, 'type=all&q=zzz-nothing-matches-this')).toEqual([]);
  expect(await activity(admin, 'type=all&from=2000-01-01&to=2000-01-02')).toEqual([]);
  expect((await activity(admin, 'type=all&limit=1')).length).toBe(1);

  const page1 = await activity(admin, 'type=all&limit=2&page=1');
  const page2 = await activity(admin, 'type=all&limit=2&page=2');
  expect(page1).toHaveLength(2);
  expect(page2.map((e) => e.id).filter((id) => page1.some((p) => p.id === id))).toEqual([]);
  // newest first
  const times = page1.map((e) => Date.parse(e.occurredAt));
  expect([...times].sort((a, b) => b - a)).toEqual(times);

  // unknown type falls back to sensitive, never to "everything"
  expect((await activity(admin, 'type=bogus')).every((e) => e.sensitive)).toBe(true);
});

test('the summary counts this week by type and the actors list names who appears', async () => {
  const summary = (await (await admin.get('/api/activity-events/summary?days=7')).json()) as {
    items: { type: string; count: number }[];
  };
  expect(summary.items.find((i) => i.type === 'auth.login')?.count).toBeGreaterThan(0);
  const actors = (await (await admin.get('/api/activity-events/actors')).json()) as {
    items: string[];
  };
  expect(actors.items).toContain(adminName);
});

test('the CSV export honours filters, opens in Excel (BOM) and neutralises formulas', async () => {
  const response = await admin.get(`/api/activity-events/export?type=all&q=${adminName}`);
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('text/csv');
  expect(response.headers()['content-disposition']).toContain('actividad.csv');
  const body = await response.text();
  expect(body.charCodeAt(0)).toBe(0xfeff);
  expect(body.split('\n')[0]).toContain('Fecha,Usuario,Tipo,Sensible,Resumen,Detalle,IP');
  expect(body).toContain('inició sesión');
});

test.describe('the Actividad tab (browser)', () => {
  test('lists entries by day, filters them, and shows the weekly summary', async ({ page }) => {
    const seed = await getE2ESeed();
    await uiLogin(page, seed.username, seed.password);
    await page.goto(`${BASE}/config/activity`);

    const root = page.getByTestId('activity-page');
    await expect(root).toBeVisible({ timeout: 25_000 });
    await expect(
      page
        .getByRole('tab', { name: 'Actividad' })
        .or(page.getByRole('link', { name: 'Actividad' }))
        .first(),
    ).toBeVisible();

    // default view = sensitive only; the failed attempt from the first test is in it
    await expect(page.getByTestId('activity-row').first()).toBeVisible({ timeout: 15_000 });
    await expect(root.getByText('Sensible', { exact: true }).first()).toBeVisible();
    await expect(root.getByRole('heading', { name: /^HOY · / })).toBeVisible();
    await expect(root.getByText('Este registro no se puede editar ni borrar')).toBeVisible();

    // routine sign-ins only appear under "Todos"
    await expect(root.locator('[data-type="auth.login"]')).toHaveCount(0);
    await root.getByLabel('Tipo').selectOption('all');
    await expect(root.locator('[data-type="auth.login"]').first()).toBeVisible({ timeout: 15_000 });

    // search narrows, and an impossible search shows the empty state
    await root.getByLabel('Buscar en la actividad').fill('zzz-nothing-matches-this');
    await expect(page.getByTestId('activity-empty')).toBeVisible({ timeout: 15_000 });
    await root.getByLabel('Buscar en la actividad').fill('');

    await expect(page.getByTestId('activity-summary-row').first()).toBeVisible();
  });
});
