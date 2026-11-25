import { expect, test } from '@playwright/test';

test('el login sigue funcionando si el navegador evalúa dos veces la entrada de la app', async ({
  page,
}) => {
  const doubleMountWarnings: string[] = [];
  page.on('console', (message) => {
    if (message.text().includes('already been passed to createRoot')) {
      doubleMountWarnings.push(message.text());
    }
  });

  await page.route('**/api/auth/login', (route) =>
    route.fulfill({
      status: 401,
      json: { message: 'Credenciales inválidas' },
    }),
  );

  await page.goto('/login');
  await expect(page.locator('form.login-form')).toBeVisible();
  await page.evaluate(async () => {
    const entry = [...document.querySelectorAll<HTMLScriptElement>('script[type="module"][src]')]
      .map((script) => new URL(script.src))
      .find(({ pathname }) => /\/app(?:\.tsx|-[^/]+\.js)$/.test(pathname));
    if (!entry) throw new Error('No se encontró el módulo de entrada de la app.');
    entry.searchParams.set('duplicate-bootstrap-test', '1');
    await import(entry.href);
  });

  await page.getByLabel('Usuario', { exact: true }).fill('usuario-de-prueba');
  await page.getByLabel('Contraseña', { exact: true }).fill('clave-de-prueba');
  const [response] = await Promise.all([
    page.waitForResponse(
      (candidate) =>
        new URL(candidate.url()).pathname === '/api/auth/login' &&
        candidate.request().method() === 'POST',
      { timeout: 5000 },
    ),
    page.getByRole('button', { name: 'Ingresar' }).click(),
  ]);

  expect(response.status()).toBe(401);
  await expect(page.getByRole('button', { name: 'Ingresar' })).toBeEnabled();
  expect(doubleMountWarnings).toEqual([]);
});

test('si otra pestaña retiene la sesión, el login permite reintentar en vez de girar indefinidamente', async ({
  context,
  page,
}) => {
  const oldTab = await context.newPage();
  await oldTab.goto('/login');
  await oldTab.evaluate(
    () =>
      new Promise<void>((locked) => {
        void navigator.locks.request('nubit-auth-session', () => {
          locked();
          return new Promise(() => {});
        });
      }),
  );

  let loginRequests = 0;
  await page.route('**/api/auth/login', (route) => {
    loginRequests++;
    return route.fulfill({ status: 401, json: { message: 'Credenciales inválidas' } });
  });
  await page.goto('/login');
  await page.getByLabel('Usuario', { exact: true }).fill('usuario-de-prueba');
  await page.getByLabel('Contraseña', { exact: true }).fill('clave-de-prueba');
  const submit = page.getByRole('button', { name: 'Ingresar' });
  await submit.click();

  await expect(page.locator('.login-form [role=alert]')).toContainText(
    'Cierra las otras pestañas',
    {
      timeout: 8000,
    },
  );
  await expect(submit).toBeEnabled();
  expect(loginRequests).toBe(0);

  await oldTab.close();
  await submit.click();
  await expect(page.locator('.login-form [role=alert]')).toContainText('Credenciales inválidas');
  expect(loginRequests).toBe(1);
});
