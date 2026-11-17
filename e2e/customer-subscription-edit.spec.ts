import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

type HydraRecord = { '@id'?: string; id?: string };
type HydraCollection<T extends HydraRecord> = { 'hydra:member'?: T[]; member?: T[] } | T[];

async function api<T>(
  page: Page,
  path: string,
  init?: RequestInit,
): Promise<{ body: T; status: number }> {
  return page.evaluate(
    async ({ init, path }) => {
      const storedToken = localStorage.getItem('auth_token');
      const token = storedToken ? (JSON.parse(storedToken) as string) : null;
      const response = await fetch(path, {
        ...init,
        headers: {
          Accept: 'application/ld+json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          ...(init?.body
            ? {
                'Content-Type':
                  init?.method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json',
              }
            : {}),
          ...init?.headers,
        },
      });
      const body = (await response.json()) as T;

      return { body, status: response.status };
    },
    { init, path },
  );
}

function members<T extends HydraRecord>(payload: HydraCollection<T>): T[] {
  return Array.isArray(payload) ? payload : (payload['hydra:member'] ?? payload.member ?? []);
}

function iri(record: HydraRecord, resource: string): string {
  return record['@id'] ?? `/api/${resource}/${record.id ?? ''}`;
}

function identifier(record: HydraRecord): string | undefined {
  return record.id ?? record['@id']?.split('/').at(-1);
}

test('editar una suscripción no devuelve 500', async ({ page }) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);

  const branches = await api<HydraCollection<HydraRecord>>(page, '/api/branches?itemsPerPage=1');
  expect(branches.status).toBe(200);
  const branch = members(branches.body)[0];
  expect(branch).toBeDefined();

  const branchSelection = await api<{ token?: string }>(page, '/api/auth/select-branch', {
    method: 'POST',
    body: JSON.stringify({ branchId: identifier(branch!), response_mode: 'json' }),
  });
  expect(branchSelection.status).toBe(200);
  if (branchSelection.body.token) {
    await page.evaluate(
      (token) => localStorage.setItem('auth_token', JSON.stringify(token)),
      branchSelection.body.token,
    );
  }

  const [products, warehouses] = await Promise.all([
    api<HydraCollection<HydraRecord>>(page, '/api/products?itemsPerPage=1'),
    api<HydraCollection<HydraRecord>>(page, '/api/warehouses?itemsPerPage=1'),
  ]);

  for (const response of [products, warehouses]) {
    expect(response.status).toBe(200);
  }

  const product = members(products.body)[0];
  const warehouse = members(warehouses.body)[0];
  expect(product).toBeDefined();
  expect(warehouse).toBeDefined();

  const suffix = Date.now();
  const customer = await api<HydraRecord>(page, '/api/customers', {
    method: 'POST',
    body: JSON.stringify({
      businessName: `Cliente E2E ${suffix}`,
      ruc: `20${String(suffix).slice(-9)}`,
    }),
  });
  expect(customer.status).toBe(201);

  const plan = await api<HydraRecord>(page, '/api/subscription-plans', {
    method: 'POST',
    body: JSON.stringify({
      amount: '10.00',
      billingCycle: 'MONTHLY',
      branch: iri(branch!, 'branches'),
      isActive: true,
      name: `Plan E2E ${suffix}`,
      product: iri(product!, 'products'),
      warehouse: iri(warehouse!, 'warehouses'),
    }),
  });
  expect(plan.status).toBe(201);

  const subscription = await api<HydraRecord>(page, '/api/customer-subscriptions', {
    method: 'POST',
    body: JSON.stringify({
      autoRenew: true,
      customer: iri(customer.body, 'customers'),
      nextBillingDate: '2026-10-01',
      plan: iri(plan.body, 'subscription-plans'),
      serviceReference: `e2e-${suffix}`,
      startDate: '2026-09-01',
      status: 'active',
    }),
  });
  expect(subscription.status).toBe(201);

  const updated = await api<HydraRecord>(page, iri(subscription.body, 'customer-subscriptions'), {
    method: 'PATCH',
    body: JSON.stringify({ serviceReference: `edited-e2e-${suffix}` }),
  });
  expect(updated.status).toBe(200);

  await page.goto(`${getE2EBaseUrl()}/subscriptions/customer-subscriptions`);
  await expect(page.getByText('Suscripciones', { exact: true }).first()).toBeVisible();
});
