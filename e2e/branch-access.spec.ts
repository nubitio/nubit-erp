import { randomUUID } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { loginViaApi, selectBranchViaApi } from './helpers/fiscal';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';
import { E2E_SEED } from './support/seedConstants';

test('La consulta de otra sucursal respeta las asignaciones durante una sesión abierta', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  const base = getE2EBaseUrl();
  const admin = await loginViaApi(request, base, seed.username, seed.password);
  const suffix = randomUUID().slice(0, 8);

  const options = await request.get(`${base}/api/auth/operating-branches`, {
    headers: admin.headers,
  });
  expect(options.ok()).toBeTruthy();
  const branches = (await options.json()) as { id: string; name: string }[];
  const seededBranch = branches.find((branch) => branch.name === E2E_SEED.branchName);

  const branchB = branches.find((branch) => branch.id !== seededBranch?.id);
  if (!seededBranch || !branchB) throw new Error('El fixture necesita dos sucursales activas.');
  const branchA = seededBranch;

  const username = `branches-${suffix}`;
  const password = randomUUID();
  const createdUser = await request.post(`${base}/api/users`, {
    headers: admin.headers,
    data: { username, plainPassword: password, role: '/api/roles/1' },
  });
  expect(createdUser.status(), 'crear el usuario de prueba').toBe(201);
  const user = (await createdUser.json()) as { id: number };

  async function configure(operatingBranchIds: string[], readOnlyBranchIds: string[]) {
    const response = await request.put(`${base}/api/users/${user.id}/branch-access`, {
      headers: admin.headers,
      data: { operatingBranchIds, readOnlyBranchIds, defaultBranchId: branchA.id },
    });
    expect(response.status(), 'guardar acceso a sucursales').toBe(200);
  }

  try {
    await configure([branchA.id], [branchB.id]);
    const query = new URLSearchParams({ branchId: branchB.id });
    const centralWarehouses = `${base}/api/purchasing/warehouses?${query}`;
    await login(page, username, password);
    expect((await page.request.get(centralWarehouses)).status()).toBe(403);
    await page.goto(`${base}/billing/consult`);
    const selector = page.getByRole('combobox', { name: 'Sucursales autorizadas para ventas' });
    await expect(selector.getByRole('option', { name: branchB.name })).toHaveCount(1);
    const report = page.waitForResponse((response) => {
      const url = new URL(response.url());
      return (
        url.pathname === '/api/sales/consultation' &&
        url.searchParams.get('branchId') === branchB.id
      );
    });
    await selector.selectOption(branchB.id);
    expect((await report).status()).toBe(200);

    const exportResponse = await page.request.get(
      `${base}/api/sales/report?${query}&report=detailed-sales`,
    );
    expect(exportResponse.status()).toBe(200);
    expect(exportResponse.headers()['content-type']).toContain('spreadsheetml');

    await configure([branchA.id, branchB.id], []);
    const session = await loginViaApi(request, base, username, password);
    const operating = await selectBranchViaApi(request, base, session, branchA.id);
    expect((await request.get(centralWarehouses, { headers: operating.headers })).status()).toBe(
      200,
    );
    const branchBSession = await selectBranchViaApi(request, base, operating, branchB.id);
    await configure([branchA.id], []);
    const revoked = await request.get(`${base}/api/sales/consultation?${query}`, {
      headers: branchBSession.headers,
    });
    expect(revoked.status()).toBe(403);
    const stale = await request.get(`${base}/api/sales/reports/monthly-summary`, {
      headers: branchBSession.headers,
    });
    expect(stale.status()).toBe(400);
  } finally {
    await request.delete(`${base}/api/users/${user.id}`, { headers: admin.headers });
  }
});
