import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { newCustomerButton, themeColor, waitForCustomersGrid } from './helpers/customers';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

type LookupResponse = {
  id?: string;
  name?: string;
  address?: string;
  active?: boolean;
  detail?: string;
};
async function setup(page: Page, mobile = false) {
  const pending: { doc: string; respond: (data: LookupResponse, status?: number) => void }[] = [];
  // Every external document lookup is intercepted, as are customer writes.
  await page.route('**/api/consults/**', async (route) => {
    const response = await new Promise<{ data: LookupResponse; status: number }>((resolve) =>
      pending.push({
        doc: new URL(route.request().url()).pathname.split('/').pop()!,
        respond: (data, status = 200) => resolve({ data, status }),
      }),
    );
    await route.fulfill({ status: response.status, json: response.data });
  });
  await page.route('**/api/customers**', (route) =>
    route.request().method() === 'GET' ? route.fallback() : route.abort(),
  );
  await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 960 });
  await page.emulateMedia({ colorScheme: mobile ? 'dark' : 'light' });
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/customer`);
  await waitForCustomersGrid(page);
  const add = newCustomerButton(page, mobile);
  await expect(add).toBeVisible({ timeout: 30000 });
  await add.click();
  const form = page.getByRole('dialog', { name: 'Nuevo cliente', exact: true });
  await expect(form).toBeVisible();
  return {
    pending,
    add,
    form,
    doc: form.getByLabel('RUC/DNI', { exact: true }),
    name: form.getByLabel('Razón social', { exact: true }),
    address: form.getByLabel('Dirección fiscal', { exact: true }),
    consult: form.getByRole('button', { name: 'Consultar RUC o DNI', exact: true }),
  };
}
const company = {
  id: '20100000009',
  name: 'Empresa consultada',
  address: 'Dirección consultada',
  active: true,
};

test('document lookup validates locally, preserves edits and supports inactive/error/retry feedback', async ({
  page,
}, testInfo) => {
  const { pending, form, doc, name, address, consult } = await setup(page);
  await doc.fill('123');
  await consult.click();
  await expect(form.getByRole('status')).toContainText('8 dígitos');
  expect(pending).toHaveLength(0);
  await doc.fill(company.id);
  await consult.click();
  await expect(consult).toBeDisabled();
  await expect(consult).toHaveAttribute('aria-busy', 'true');
  await expect(consult).toContainText('Consultando');
  await expect.poll(() => pending.length).toBe(1);
  await name.fill('Nombre editado manualmente');
  pending[0].respond(company);
  await expect(address).toHaveValue(company.address);
  await expect(name).toHaveValue('Nombre editado manualmente');
  await expect(form.getByRole('status')).toContainText('Se conservaron');
  await consult.click();
  await expect.poll(() => pending.length).toBe(2);
  pending[1].respond({ ...company, active: false });
  await expect(form.getByRole('alert')).toContainText('no se encuentra activo');
  await expect(name).toHaveValue('Nombre editado manualmente');
  await consult.click();
  await expect.poll(() => pending.length).toBe(3);
  pending[2].respond({ detail: 'Provider unavailable' }, 503);
  await expect(form.getByRole('alert')).toContainText('No se pudo consultar el RUC');
  await expect(page.locator('.app-toast')).toHaveCount(0);
  await consult.click();
  await expect.poll(() => pending.length).toBe(4);
  pending[3].respond(company);
  await expect(name).toHaveValue(company.name);
  await expect(form.getByRole('status')).toContainText('RUC activo');
  await expect(form.getByRole('status')).not.toContainText('Habido');
  await page.screenshot({
    path: testInfo.outputPath('customer-lookup-success.png'),
    animations: 'disabled',
  });
});

test('mobile dark lookup ignores late responses and earlier form sessions', async ({
  page,
}, testInfo) => {
  const { pending, add, form, doc, name, address, consult } = await setup(page, true);
  await address.fill('Dirección manual');
  await doc.fill(company.id);
  await consult.click();
  await expect.poll(() => pending.length).toBe(1);
  await doc.fill('12345678');
  await expect(consult).toBeEnabled();
  await consult.click();
  await expect.poll(() => pending.length).toBe(2);
  pending[1].respond({ ...company, id: '12345678', name: 'Persona consultada' });
  await expect(name).toHaveValue('Persona consultada');
  pending[0].respond(company);
  await expect(name).toHaveValue('Persona consultada');
  await expect(address).toHaveValue('Dirección manual');
  await expect(form.getByRole('status')).toContainText('DNI encontrado');
  await expect(form.getByRole('status')).toHaveCSS(
    'color',
    await themeColor(page, '--success-color'),
  );
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({
    path: testInfo.outputPath('customer-lookup-mobile-dark.png'),
    animations: 'disabled',
  });
  await consult.click();
  await expect.poll(() => pending.length).toBe(3);
  await form.getByRole('button', { name: 'Cancelar', exact: true }).click();
  await expect(form).toBeHidden();
  await add.click();
  await expect(form).toBeVisible();
  await doc.fill('12345678');
  await name.fill('Otro cliente sin consultar');
  const obsolete = page.waitForResponse((response) =>
    response.url().endsWith('/consults/dni/12345678'),
  );
  pending[2].respond({ ...company, id: '12345678', name: 'Respuesta de la sesión anterior' });
  await (await obsolete).finished();
  await expect(name).toHaveValue('Otro cliente sin consultar');
  await expect(form.getByRole('status')).toHaveCount(0);
  await expect(consult).toBeEnabled();
});
