import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

// A 1x1 PNG, enough for the server to accept it as an image.
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
  'base64',
);

/** Signs in and opens "Ajustes" from the user menu. */
async function openSettings(page: Page) {
  const seed = await getE2ESeed();
  await page.setViewportSize({ width: 1288, height: 900 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/`);
  await page.getByRole('button', { name: 'Menú de usuario' }).click();
  await page.getByRole('menuitem', { name: 'Ajustes' }).click();
  const dialog = page.getByRole('dialog', { name: 'Ajustes' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('heading', { name: 'Perfil', exact: true })).toBeVisible();
  return dialog;
}

test('the user menu opens Ajustes and no longer carries password or two-step entries', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/`);
  await page.getByRole('button', { name: 'Menú de usuario' }).click();
  const menu = page.getByRole('dialog', { name: 'Menú de usuario' });
  await expect(menu.getByRole('menuitem', { name: 'Ajustes' })).toBeVisible();
  await expect(menu.getByRole('menuitem', { name: 'Cambiar contraseña' })).toHaveCount(0);
  await expect(menu.getByRole('menuitem', { name: 'Verificación en dos pasos' })).toHaveCount(0);
  await expect(menu.getByRole('menuitem', { name: 'Mi perfil' })).toHaveCount(0);
});

test('Ajustes is a dialog over the current page, and Esc closes it', async ({ page }) => {
  const dialog = await openSettings(page);
  await expect(page).toHaveURL(/[?&]settings=account/);
  await page.keyboard.press('Escape');
  await expect(dialog).toHaveCount(0);
  await expect(page).not.toHaveURL(/settings=/);
});

test('a link with ?settings= opens the dialog on that section; the header has no appearance menu', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/dashboard?settings=appearance`);
  const dialog = page.getByRole('dialog', { name: 'Ajustes' });
  await expect(dialog.getByRole('group', { name: 'Densidad de la interfaz' })).toBeVisible();
  await expect(dialog.getByRole('group', { name: 'Tema' })).toBeVisible();
  await expect(dialog.getByText('Color de acento')).toHaveCount(0);
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button', { name: 'Apariencia' })).toHaveCount(0);
});

test('Seguridad holds the password and two-step entries', async ({ page }) => {
  const dialog = await openSettings(page);
  await dialog
    .getByRole('navigation', { name: 'Secciones de ajustes' })
    .getByRole('button', { name: 'Seguridad' })
    .click();
  await expect(dialog.getByRole('button', { name: 'Cambiar contraseña' })).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Configurar' })).toBeVisible();
  await dialog.getByRole('button', { name: 'Cambiar contraseña' }).click();
  await expect(page.getByRole('dialog', { name: 'Cambiar contraseña' })).toBeVisible();
});

test('the settings list shows the account pages and, to an admin, plan and organization', async ({
  page,
}) => {
  const dialog = await openSettings(page);
  const nav = dialog.getByRole('navigation', { name: 'Secciones de ajustes' });
  for (const name of ['Perfil', 'Seguridad', 'Apariencia']) {
    await expect(nav.getByRole('button', { name })).toBeVisible();
  }
  // The e2e user administers its tenant.
  await expect(nav.getByRole('button', { name: 'Uso' })).toBeVisible();
  await expect(nav.getByRole('button', { name: 'Plan y facturación' })).toBeVisible();
  await expect(nav.getByRole('link', { name: 'Empresa' })).toBeVisible();

  await nav.getByRole('button', { name: 'Uso' }).click();
  await expect(page).toHaveURL(/[?&]settings=usage/);
  await expect(dialog.getByRole('heading', { name: 'Uso', exact: true })).toBeVisible();
  await expect(dialog.locator('.settings-row').filter({ hasText: 'Usuarios' })).toBeVisible();

  // An organization page leaves the dialog behind.
  await nav.getByRole('link', { name: 'Empresa' }).click();
  await expect(page).toHaveURL(/\/config\/company/);
  await expect(page.getByRole('dialog', { name: 'Ajustes' })).toHaveCount(0);
});

test('the profile name is saved, shown in the user menu and can be restored', async ({ page }) => {
  const dialog = await openSettings(page);
  const name = dialog.locator('#profile-name');
  const original = await name.inputValue();

  await name.fill('Perfil E2E');
  await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(page.getByText('Datos guardados')).toBeVisible();

  await name.fill(original);
  await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(page.getByText('Datos guardados')).toBeVisible();
});

test('an invalid phone is explained under its field', async ({ page }) => {
  const dialog = await openSettings(page);
  await dialog.locator('#profile-phone').fill('abc');
  await dialog.locator('#profile-name').fill('Cualquiera');
  await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
  await expect(dialog.getByText('El teléfono ingresado no es válido.')).toBeVisible();
  await dialog.getByRole('button', { name: 'Descartar' }).click();
});

test('changing the e-mail needs the current password', async ({ page }) => {
  const dialog = await openSettings(page);
  // The photo row can also offer "Cambiar"; take the one in the e-mail row.
  await dialog
    .locator('.settings-row')
    .filter({ hasText: 'Correo' })
    .getByRole('button', { name: 'Cambiar' })
    .click();
  const email = page.getByRole('dialog', { name: 'Cambiar correo' });
  await email.locator('#profile-email').fill('otro@example.com');
  await email.locator('#profile-email-password').fill('no-es-la-contraseña');
  await email.getByRole('button', { name: 'Cambiar correo' }).click();
  await expect(email.getByText('La contraseña actual no es correcta.')).toBeVisible();
});

test('a photo can be uploaded, is shown, and removed', async ({ page }) => {
  const dialog = await openSettings(page);

  await dialog.getByTestId('profile-photo-input').setInputFiles({
    name: 'foto.png',
    mimeType: 'image/png',
    buffer: PNG,
  });
  await expect(page.getByText('Foto actualizada')).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Quitar' })).toBeVisible();
  await expect(page.locator('img.erp-user-avatar').first()).toBeVisible();

  // A file that only claims to be an image is refused by the server.
  await dialog.getByTestId('profile-photo-input').setInputFiles({
    name: 'falso.png',
    mimeType: 'image/png',
    buffer: Buffer.from('<?php echo 1;'),
  });
  await expect(dialog.getByText('Sube una imagen PNG, JPG o WebP.')).toBeVisible();

  await dialog.getByRole('button', { name: 'Quitar' }).click();
  await expect(page.getByText('Foto eliminada')).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Subir foto' }).first()).toBeVisible();
});
