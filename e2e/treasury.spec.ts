import { expect, test, type APIRequestContext } from '@playwright/test';
import { parseApiCollection } from './helpers/api';
import { login } from './helpers/auth';
import { loginAtSeedBranchViaApi } from './helpers/fiscal';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

type Account = {
  id: string;
  kind: 'cash_register' | 'bank' | 'wallet' | 'suspense';
  name: string;
  currency: string;
  balance: string;
};

type Summary = {
  currencies: Array<{
    currency: string;
    position: { total: string; cash: string; wallets: string; suspense: string };
    payables: { balance: string };
  }>;
  attention: { accountsWithoutOpeningBalance: string[] };
};

type Headers = Record<string, string>;

/**
 * Cuentas de dinero (ADR-021): la función se activa, los pagos a proveedores mueven dinero por la billetera
 * asignada o por la caja abierta (y su reversa lo devuelve), una transferencia mueve saldo entre cuentas y el
 * resumen del dueño lo refleja.
 *
 * Treasury queda activada solo durante este spec: con ella, un pago en efectivo exige una caja abierta y otros
 * specs pagan sin una, así que se vuelve a apagar al terminar.
 */
test('cuentas de dinero: pagos por billetera y por caja, reversas, transferencia y resumen', async ({
  page,
  request,
}) => {
  const seed = await getE2ESeed();
  const auth = await loginAtSeedBranchViaApi(request, BASE_URL, seed.username, seed.password);
  const headers: Headers = auth.headers;
  const suffix = String(Date.now()).slice(-6);

  const [suppliers, branches, categories, methods] = await Promise.all([
    getCollection<{ id: string; ruc?: string }>(request, headers, 'suppliers'),
    getCollection<{ id: string; name?: string }>(request, headers, 'branches'),
    getCollection<{ id: number }>(request, headers, 'expense-categories'),
    getCollection<{ id: number; name?: string }>(request, headers, 'payment-methods'),
  ]);
  const supplier = suppliers.find((item) => item.ruc === E2E_SEED.supplierRuc);
  const branch = branches.find((item) => item.name === E2E_SEED.branchName);
  const category = categories[0];
  const cash = methods.find((item) => /efectivo/i.test(item.name ?? ''));
  const yape = methods.find((item) => /yape/i.test(item.name ?? ''));
  if (!supplier || !branch || !category || !cash || !yape) {
    throw new Error('Faltan datos E2E para las cuentas de dinero');
  }

  const selection = await request.post(`${BASE_URL}/api/auth/select-branch`, {
    headers,
    data: { branchId: branch.id, response_mode: 'json' },
  });
  expect(selection.ok(), await selection.text()).toBeTruthy();
  headers.Authorization = `Bearer ${((await selection.json()) as { token: string }).token}`;

  const accounts = async () =>
    (
      (await (await request.get(`${BASE_URL}/api/treasury/accounts`, { headers })).json()) as {
        accounts: Account[];
      }
    ).accounts;
  const balanceOf = async (id: string) =>
    Number((await accounts()).find((account) => account.id === id)?.balance);
  const summary = async () =>
    (await (await request.get(`${BASE_URL}/api/treasury/summary`, { headers })).json()) as Summary;
  const pen = async () => (await summary()).currencies.find((row) => row.currency === 'PEN');

  async function createBill(): Promise<string> {
    const today = new Date().toISOString().slice(0, 10);
    const response = await request.post(`${BASE_URL}/api/supplier-bills`, {
      headers,
      data: {
        supplier: `/api/suppliers/${supplier!.id}`,
        branch: `/api/branches/${branch!.id}`,
        category: `/api/expense-categories/${category!.id}`,
        documentType: 'FACTURA',
        series: 'F001',
        number: String(Math.floor(Math.random() * 1e8)).padStart(8, '0'),
        issueDate: today,
        dueDate: today,
        currency: 'PEN',
        amount: '118.00',
        note: 'Cuentas de dinero E2E',
      },
    });
    const body = await response.text();
    expect(response.ok(), body).toBeTruthy();

    return (JSON.parse(body) as { id: string }).id;
  }

  async function pay(billId: string, amount: string, methodId: number): Promise<number> {
    const response = await request.post(`${BASE_URL}/api/supplier-bills/${billId}/pay`, {
      headers,
      data: { id: billId, amount, paymentMethodId: methodId },
    });
    expect(response.ok(), await response.text()).toBeTruthy();
    const bill = (await (
      await request.get(`${BASE_URL}/api/supplier-bills/${billId}`, { headers })
    ).json()) as { payments: Array<{ id: number; amount: string; reversedAt?: string | null }> };

    return bill.payments.filter((payment) => !payment.reversedAt).slice(-1)[0]!.id;
  }

  async function reverse(billId: string, paymentId: number) {
    const response = await request.post(
      `${BASE_URL}/api/supplier-bills/${billId}/reverse-payment`,
      { headers, data: { id: billId, paymentId } },
    );
    expect(response.ok(), await response.text()).toBeTruthy();
  }

  try {
    // 1. Activar crea las cuentas que el negocio ya tiene.
    const activation = await request.put(`${BASE_URL}/api/treasury/capability`, {
      headers,
      data: { enabled: true },
    });
    expect(activation.ok(), await activation.text()).toBeTruthy();
    const initial = await accounts();
    expect(initial.some((account) => account.kind === 'cash_register')).toBeTruthy();
    expect(initial.some((account) => account.kind === 'suspense')).toBeTruthy();

    // 2. Dos billeteras, saldo inicial (una sola vez) y Yape asignado a la primera.
    const wallet = await createWallet(request, headers, `Yape E2E ${suffix}`);
    const other = await createWallet(request, headers, `Plin E2E ${suffix}`);
    const opening = await request.post(
      `${BASE_URL}/api/treasury/accounts/${wallet.id}/opening-balance`,
      { headers, data: { amount: '500.00' } },
    );
    expect(opening.status()).toBe(201);
    const again = await request.post(
      `${BASE_URL}/api/treasury/accounts/${wallet.id}/opening-balance`,
      { headers, data: { amount: '10.00' } },
    );
    expect(again.status(), 'el saldo inicial se registra una sola vez').toBe(422);
    const mapping = await request.put(`${BASE_URL}/api/treasury/payment-method-accounts`, {
      headers,
      data: {
        branchId: branch.id,
        paymentMethodId: yape.id,
        currency: 'PEN',
        accountId: wallet.id,
      },
    });
    expect(mapping.ok(), await mapping.text()).toBeTruthy();
    expect(await balanceOf(wallet.id)).toBe(500);

    // 3. Un pago por Yape sale de la billetera asignada; su reversa lo devuelve a la misma cuenta.
    const billA = await createBill();
    const paymentA = await pay(billA, '40.00', yape.id);
    expect(await balanceOf(wallet.id)).toBe(460);
    const movements = (await (
      await request.get(`${BASE_URL}/api/treasury/accounts/${wallet.id}/movements`, { headers })
    ).json()) as {
      movements: Array<{ signedAmount: string; source: string; correctsMovementId: string | null }>;
    };
    expect(
      movements.movements.some(
        (movement) =>
          movement.source === 'supplier_payment' && Number(movement.signedAmount) === -40,
      ),
    ).toBeTruthy();

    await reverse(billA, paymentA);
    expect(await balanceOf(wallet.id)).toBe(500);
    const afterReversal = (await (
      await request.get(`${BASE_URL}/api/treasury/accounts/${wallet.id}/movements`, { headers })
    ).json()) as {
      movements: Array<{ signedAmount: string; source: string; correctsMovementId: string | null }>;
    };
    expect(
      afterReversal.movements.some(
        (movement) =>
          movement.source === 'supplier_payment_reversal' &&
          Number(movement.signedAmount) === 40 &&
          movement.correctsMovementId !== null,
      ),
      'la reversa es un movimiento que corrige al original',
    ).toBeTruthy();

    // 4. Un pago en efectivo sale de la caja abierta (el arqueo lo ve) y la reversa lo devuelve.
    const cashBefore = Number((await pen())?.position.cash);
    const billB = await createBill();
    const drawersBefore = (await accounts()).filter((account) => account.kind === 'cash_register');
    const paymentB = await pay(billB, '20.00', cash.id);
    expect(Number((await pen())?.position.cash)).toBeCloseTo(cashBefore - 20, 2);
    // La caja del usuario es la que se movió con su pago en efectivo.
    const drawer = (await accounts()).find(
      (account) =>
        account.kind === 'cash_register' &&
        Number(account.balance) !== Number(drawersBefore.find((d) => d.id === account.id)?.balance),
    );
    expect(drawer, 'el pago en efectivo movió la caja abierta del usuario').toBeTruthy();
    await reverse(billB, paymentB);
    expect(Number((await pen())?.position.cash)).toBeCloseTo(cashBefore, 2);

    // 5. Una transferencia mueve saldo entre dos cuentas; entre la misma cuenta se rechaza.
    const transfer = await request.post(`${BASE_URL}/api/treasury/transfers`, {
      headers,
      data: { fromAccountId: wallet.id, toAccountId: other.id, amount: '100.00', reference: 'E2E' },
    });
    expect(transfer.status(), await transfer.text()).toBe(201);
    expect(await balanceOf(wallet.id)).toBe(400);
    expect(await balanceOf(other.id)).toBe(100);
    const same = await request.post(`${BASE_URL}/api/treasury/transfers`, {
      headers,
      data: { fromAccountId: wallet.id, toAccountId: wallet.id, amount: '1.00' },
    });
    expect(same.status()).toBe(422);

    // 5b. Depositar efectivo de la caja abierta en una billetera: el arqueo lo ve salir y la billetera lo recibe.
    const drawerBefore = Number((await pen())?.position.cash);
    const deposit = await request.post(`${BASE_URL}/api/treasury/transfers`, {
      headers,
      data: {
        fromAccountId: drawer!.id,
        toAccountId: wallet.id,
        amount: '15.00',
        reference: 'Depósito E2E',
      },
    });
    expect(deposit.status(), await deposit.text()).toBe(201);
    expect(Number((await pen())?.position.cash)).toBeCloseTo(drawerBefore - 15, 2);
    expect(await balanceOf(wallet.id)).toBe(415);

    // 6. El resumen del dueño refleja el dinero y avisa de lo que falta.
    const row = await pen();
    expect(Number(row?.position.wallets)).toBeGreaterThanOrEqual(500);
    expect((await summary()).attention.accountsWithoutOpeningBalance).not.toContain(wallet.name);
    expect((await summary()).attention.accountsWithoutOpeningBalance).toContain(other.name);

    // 7. Las pantallas muestran lo mismo.
    await login(page, seed.username, seed.password);
    await page.goto(`${BASE_URL}/treasury`);
    await expect(page.getByText(wallet.name).first()).toBeVisible({ timeout: 20000 });
    await page.goto(`${BASE_URL}/finance-summary`);
    await expect(page.getByText('Resumen de finanzas').first()).toBeVisible({ timeout: 20000 });
    await expect(page.getByText('Qué atender').first()).toBeVisible();
  } finally {
    const off = await request.put(`${BASE_URL}/api/treasury/capability`, {
      headers,
      data: { enabled: false },
    });
    expect(off.ok(), 'Treasury debe quedar apagada para los demás specs').toBeTruthy();
  }
});

async function getCollection<T>(request: APIRequestContext, headers: Headers, resource: string) {
  const response = await request.get(`${BASE_URL}/api/${resource}?itemsPerPage=100`, { headers });
  expect(response.ok(), `${resource} debe responder 2xx`).toBeTruthy();

  return parseApiCollection<T>(await response.json());
}

async function createWallet(
  request: APIRequestContext,
  headers: Headers,
  name: string,
): Promise<Account> {
  const response = await request.post(`${BASE_URL}/api/treasury/accounts`, {
    headers,
    data: { name, currency: 'PEN' },
  });
  expect(response.status(), await response.text()).toBe(201);

  return (await response.json()) as Account;
}
