import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

for (const mobile of [false, true]) {
  test(`customer drawer validates, retains server errors and saves once (${mobile ? 'mobile dark' : 'desktop light'})`, async ({
    page,
  }, testInfo) => {
    const seed = await getE2ESeed();
    await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 960 });
    await page.emulateMedia({ colorScheme: mobile ? 'dark' : 'light' });
    let writes = 0;
    let payload: Record<string, unknown> | undefined;
    let finish: (() => void) | undefined;
    // No customer writes leave the browser in this presentation test.
    await page.route('**/api/customers**', async (route) => {
      if (!['POST', 'PATCH', 'PUT', 'DELETE'].includes(route.request().method()))
        return route.fallback();
      writes++;
      payload = route.request().postDataJSON();
      if (writes === 1)
        return route.fulfill({
          status: 422,
          json: {
            violations: [
              { propertyPath: 'businessName', message: 'Ya existe un cliente con este nombre.' },
            ],
          },
        });
      await new Promise<void>((resolve) => {
        finish = resolve;
      });
      await route.fulfill({ status: 201, json: { ...payload, id: 'ui-test-customer' } });
    });
    await login(page, seed.username, seed.password);
    await page.goto(`${getE2EBaseUrl()}/#/customer`);
    const newButton = mobile
      ? page.locator('.nb-datagrid__fab')
      : page
          .locator('.nb-datagrid__toolbar')
          .getByRole('button', { name: /^(Nuevo|New|Agregar|Add)$/i })
          .first();
    await expect(newButton).toBeVisible({ timeout: 30000 });
    await newButton.click();
    const drawer = page.getByRole('dialog', { name: 'Nuevo cliente', exact: true });
    await expect(drawer).toBeVisible();
    const save = drawer.getByRole('button', { name: 'Guardar cliente', exact: true });
    await save.click();
    const document = drawer.getByLabel('RUC/DNI', { exact: true });
    await expect(document).toBeFocused();
    await expect(document).toHaveAttribute('aria-invalid', 'true');
    await expect(document).toHaveAttribute('aria-describedby', /hint.*error/);
    expect(writes).toBe(0);
    await document.fill('20100000009');
    await drawer.getByLabel('Razón social', { exact: true }).fill('Cliente de prueba UI');
    await drawer.getByLabel('Correo', { exact: true }).fill('correo-invalido');
    await save.click();
    await expect(drawer.getByLabel('Correo', { exact: true })).toBeFocused();
    expect(writes).toBe(0);
    await drawer.getByLabel('Correo', { exact: true }).fill('cliente@example.test');
    await save.click();
    await expect(
      drawer.getByText('Ya existe un cliente con este nombre.', { exact: true }),
    ).toBeVisible();
    await expect(drawer.getByLabel('Razón social', { exact: true })).toBeFocused();
    await expect(document).toHaveValue('20100000009');
    if (mobile)
      await expect(drawer.locator('.nb-form__error')).toHaveCSS('color', 'rgb(255, 159, 149)');
    const consult = drawer.getByRole('button', { name: 'Consultar RUC o DNI', exact: true });
    await consult.scrollIntoViewIfNeeded();
    const inputBox = (await document.boundingBox())!;
    const actionBox = (await consult.boundingBox())!;
    expect(inputBox.x + inputBox.width).toBeLessThanOrEqual(actionBox.x);
    expect(actionBox.x + actionBox.width).toBeLessThanOrEqual(mobile ? 390 : 1440);
    await page.screenshot({
      path: testInfo.outputPath('customer-form.png'),
      animations: 'disabled',
    });
    await drawer.getByLabel('Razón social', { exact: true }).fill('Cliente corregido UI');
    await save.click();
    await expect(save).toBeDisabled();
    await expect(drawer.getByRole('button', { name: 'Cancelar', exact: true })).toBeDisabled();
    await page.keyboard.press('Escape');
    await expect(drawer).toBeVisible();
    await expect.poll(() => writes).toBe(2);
    expect(payload).toMatchObject({
      businessName: 'Cliente corregido UI',
      ruc: '20100000009',
      email: 'cliente@example.test',
      isActive: true,
    });
    const refreshed = page.waitForResponse(
      (response) =>
        new URL(response.url()).pathname === '/api/customers' &&
        response.request().method() === 'GET',
    );
    finish!();
    await expect(drawer).toBeHidden();
    await (await refreshed).finished();
    await expect(page.locator('.nb-datagrid')).toHaveAttribute('aria-busy', 'false');
    if (!mobile) {
      const actions = page
        .locator('.nb-datagrid__table tbody tr')
        .getByRole('button', { name: /acciones/i })
        .first();
      await expect(actions).toBeVisible({ timeout: 30000 });
      await actions.click();
      await page.getByRole('menuitem', { name: /editar/i }).click();
      const edit = page.getByRole('dialog', { name: 'Editar cliente', exact: true });
      await expect(edit).toBeVisible();
      const originalName = await edit.getByLabel('Razón social', { exact: true }).inputValue();
      expect(originalName).not.toBe('');
      await edit.getByLabel('Razón social', { exact: true }).fill('Edición sin guardar');
      await edit.getByRole('button', { name: 'Cancelar', exact: true }).click();
      await expect(edit).toBeHidden();
      expect(writes).toBe(2);
    }
  });
}

test('product page focuses errors and edits existing values without saving', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  const writes: string[] = [];
  await page.route('**/api/products**', (route) => {
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(route.request().method())) {
      writes.push(route.request().method());
      return route.abort();
    }
    return route.fallback();
  });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/catalog/products/new`);
  const form = page.locator('.nb-crud-page-shell');
  await expect(form.getByRole('heading', { name: 'Nuevo producto', exact: true })).toBeVisible();
  await form.getByRole('button', { name: 'Guardar producto', exact: true }).click();
  const name = form.getByLabel('Nombre del producto', { exact: true });
  await expect(name).toBeFocused();
  await expect(name).toHaveAttribute('aria-invalid', 'true');
  await name.fill('Producto sin guardar');
  await expect(name).not.toHaveAttribute('aria-invalid', 'true');
  await expect(
    form.getByText('Puedes ingresarlo con un lector de códigos.', { exact: true }),
  ).toBeVisible();
  await page.screenshot({
    path: testInfo.outputPath('product-form-validation.png'),
    animations: 'disabled',
  });
  expect(writes).toEqual([]);
  await form.getByRole('button', { name: 'Cancelar', exact: true }).last().click();
  const card = page.locator('.nb-datagrid__card').first();
  await expect(card).toBeVisible({ timeout: 30000 });
  await card.getByRole('button', { name: /acciones/i }).click();
  await page.getByRole('menuitem', { name: /editar/i }).click();
  await expect(form.getByRole('heading', { name: 'Editar producto', exact: true })).toBeVisible();
  await expect(name).not.toHaveValue('');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.emulateMedia({ colorScheme: 'dark' });
  await expect(page.locator('.app')).toHaveClass(/screen-x-small/);
  await expect(page.locator('.nb-admin-bottom-nav')).toBeVisible();
  await expect(
    form.getByRole('button', { name: 'Guardar producto', exact: true }),
  ).toBeInViewport();
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({
    path: testInfo.outputPath('product-edit-mobile-dark.png'),
    animations: 'disabled',
  });
  expect(writes).toEqual([]);
});
