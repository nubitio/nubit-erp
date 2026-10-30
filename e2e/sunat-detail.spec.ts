import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

test('fiscal detail gates actions, preserves rejection history and validates downloads', async ({
  page,
}, testInfo) => {
  const seed = await getE2ESeed();
  let code = '0';
  let writes = 0;
  let invalidXml = true;
  // All fiscal mutations are intercepted; no document reaches SUNAT.
  await page.route('**/api/tax-documents/**', async (route) => {
    const url = new URL(route.request().url());
    if (route.request().method() !== 'GET') {
      writes++;
      return route.abort();
    }
    if (url.pathname.endsWith('/events'))
      return route.fulfill({
        json: [
          {
            id: 1,
            eventType: 'sunat_send',
            code,
            message: 'Respuesta registrada',
            createdAt: '2026-10-29T12:00:00-05:00',
          },
        ],
      });
    return route.fulfill({
      json: {
        id: 'test-document',
        sunatStatus: code,
        sunatMessage: 'Respuesta registrada',
        cdrPath: '/cdr/test.zip',
      },
    });
  });
  await page.route('**/api/sales/**', (route) => {
    if (route.request().method() !== 'GET') {
      writes++;
      return route.abort();
    }
    return route.fallback();
  });
  await page.route(`**/api/sales/${seed.saleId}`, (route) =>
    route.fulfill({
      json: {
        id: seed.saleId,
        status: 'COMPLETED',
        currency: 'PEN',
        grandTotal: '25.50',
        subtotal: '25.50',
        discountTotal: '0',
        taxTotal: '0',
        paidTotal: '25.50',
        balanceDue: '0',
        paymentState: 'paid',
        sunatState: code === '0' ? 'accepted' : 'pending',
        sunatStatus: code,
        hasInvoice: true,
        taxDocumentId: 'test-document',
        invoiceFullNumber: 'F001-000001',
        publicReceiptUrl: '/receipt/test-document',
        customer: { businessName: 'Cliente de prueba' },
        payments: [],
      },
    }),
  );
  await page.route(`**/api/sales/${seed.saleId}/items*`, (route) =>
    route.fulfill({ json: { member: [] } }),
  );
  await page.route('**/receipt/test-document/xml', (route) =>
    route.fulfill({
      contentType: invalidXml ? 'text/html' : 'application/xml',
      body: invalidXml ? '<html>Unavailable</html>' : '<?xml version="1.0"?><Invoice />',
    }),
  );
  await page.route('**/receipt/test-document/cdr', (route) =>
    route.fulfill({ contentType: 'application/zip', body: Buffer.from([80, 75, 3, 4, 0, 0]) }),
  );
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/#/billing/sales/${seed.saleId}`);
  await expect(page.getByRole('button', { name: 'Consultar CDR', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Enviar a SUNAT', exact: true })).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Reemplazar rechazado', exact: true })).toHaveCount(
    0,
  );
  await expect(page.getByText('Respuesta registrada').last()).toBeVisible();
  await page.getByRole('button', { name: 'Descargar XML', exact: true }).click();
  await expect(
    page.getByText('El archivo todavía no está disponible.', { exact: true }),
  ).toBeVisible();
  invalidXml = false;
  const download = page.waitForEvent('download');
  await page.getByRole('button', { name: 'Descargar XML', exact: true }).click();
  expect((await download).suggestedFilename()).toBe('F001-000001.xml');
  code = '2335';
  await page.reload();
  const replace = page.getByRole('button', { name: 'Reemplazar rechazado', exact: true });
  await expect(replace).toBeVisible();
  await replace.click();
  const dialog = page.getByRole('alertdialog', {
    name: 'Reemplazar comprobante rechazado',
    exact: true,
  });
  await expect(dialog).toContainText('se conservará en el historial');
  await dialog.getByRole('button', { name: 'Cancelar', exact: true }).click();
  expect(writes).toBe(0);
  await page.emulateMedia({ colorScheme: 'dark' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(10, 11, 13)');
  await expect(page.locator('.nb-alert--danger')).toHaveCSS('color', 'rgb(255, 159, 149)');
  await page.screenshot({
    path: testInfo.outputPath('sunat-rejected-neutral-dark.png'),
    animations: 'disabled',
    fullPage: true,
  });
  code = '4000';
  await page.reload();
  await expect(page.getByText('Con observaciones', { exact: true }).last()).toBeVisible();
  await expect(replace).toHaveCount(0);
  await expect(page.getByRole('button', { name: 'Enviar a SUNAT', exact: true })).toHaveCount(0);
  code = '';
  await page.reload();
  await expect(page.getByRole('button', { name: 'Enviar a SUNAT', exact: true })).toBeVisible();
  expect(writes).toBe(0);
});
