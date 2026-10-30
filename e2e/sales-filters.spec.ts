import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('sales filter operators fit their cells and remain usable by keyboard', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1440, height: 960 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/billing/sales`);
  const grid = page.locator('.nb-datagrid').first();
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
  await grid.getByRole('button', { name: 'Filtros', exact: true }).click();
  const filters = page.getByRole('dialog', { name: 'Filtros', exact: true });
  await expect(filters.getByRole('combobox', { name: 'Cliente', exact: true })).toHaveAttribute(
    'placeholder',
    'Todos',
  );
  const customer = filters.getByRole('combobox', { name: 'Cliente', exact: true });
  await filters.getByRole('button', { name: 'Abrir Cliente', exact: true }).click();
  await page.getByRole('option', { name: 'Cliente CI E2E', exact: true }).click();
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
  const clearCustomer = filters.getByRole('button', { name: 'Limpiar Cliente', exact: true });
  const openCustomer = filters.getByRole('button', { name: 'Abrir Cliente', exact: true });
  const clearBox = (await clearCustomer.boundingBox())!;
  const openBox = (await openCustomer.boundingBox())!;
  const customerBox = (await customer.boundingBox())!;
  expect(
    Math.abs(clearBox.y + clearBox.height / 2 - customerBox.y - customerBox.height / 2),
  ).toBeLessThan(1);
  expect(
    Math.abs(openBox.y + openBox.height / 2 - customerBox.y - customerBox.height / 2),
  ).toBeLessThan(1);
  expect(openBox.x - clearBox.x - clearBox.width).toBeGreaterThanOrEqual(2);
  expect(openBox.x + openBox.width).toBeLessThanOrEqual(customerBox.x + customerBox.width);
  await page.screenshot({
    path: testInfo.outputPath('filter-icons-selected.png'),
    animations: 'disabled',
  });
  await clearCustomer.click();
  await expect(customer).toHaveValue('');
  await expect(clearCustomer).toHaveCount(0);
  const emptyOpenBox = (await openCustomer.boundingBox())!;
  expect(emptyOpenBox.x).toBe(openBox.x);
  const operator = page.locator('#nb-datagrid-filter-op-grandTotal');
  await expect(operator).toHaveAccessibleName('Filtrar Total: Igual');
  const box = (await operator.boundingBox())!;
  expect(box.width).toBeLessThanOrEqual(32);
  expect(box.height).toBeGreaterThanOrEqual(24);
  const input = filters.getByRole('spinbutton', { name: 'Filtrar Total', exact: true });
  const inputBox = (await input.boundingBox())!;
  expect(box.x).toBeGreaterThanOrEqual(inputBox.x);
  expect(box.x + box.width).toBeLessThan(inputBox.x + inputBox.width);
  await operator.click();
  await expect(page.getByRole('option', { name: 'Igual', exact: true })).toHaveAttribute(
    'aria-selected',
    'true',
  );
  await expect(
    page.getByRole('option', { name: 'Igual', exact: true }).locator('.nb-dropdown__selected'),
  ).toBeVisible();
  await page.screenshot({
    path: testInfo.outputPath('filter-operator-light.png'),
    animations: 'disabled',
  });
  await page.getByRole('option', { name: 'Mayor que', exact: true }).click();
  await expect(operator).toHaveAccessibleName('Filtrar Total: Mayor que');
  await input.fill('999999');
  await expect(grid).toContainText('No hay resultados');
  await operator.click();
  await expect(page.getByRole('listbox')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(operator).toBeFocused();
  await input.locator('..').getByRole('button', { name: 'Limpiar filtro', exact: true }).click();
  await expect(input).toHaveValue('');
  await expect(grid).toHaveAttribute('aria-busy', 'false', { timeout: 30000 });
});
