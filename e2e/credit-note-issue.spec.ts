import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const DOC = 'doc-f001-12345';

const origin = {
  id: DOC,
  documentType: '01',
  series: 'F001',
  number: '12345',
  issueDate: '2026-11-22T00:00:00+00:00',
  currency: 'PEN',
  total: '111.23',
  sunatStatus: '0',
  customer: { businessName: 'Comercial San Miguel S.A.C.', ruc: '20123456789' },
};
const lines = [
  {
    lineNumber: 1,
    productCode: 'PROD-000006',
    productName: 'Audífonos Bluetooth #6',
    quantity: '2.000000',
    unitPrice: '55.615000',
    taxPercentage: '18.00',
    total: '111.23',
  },
];

test('issues a credit note from its own screen: search, reason, quantities, restock, draft, preview', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  let issued: Record<string, unknown> | undefined;
  let drafted: Record<string, unknown> | undefined;
  let previewed = false;

  await page.route('**/api/credit-notes/eligible-documents*', (route) =>
    route.fulfill({
      json: [
        {
          id: DOC,
          fullNumber: 'F001-12345',
          documentType: '01',
          issueDate: '2026-11-22',
          customerName: 'Comercial San Miguel S.A.C.',
          customerDocument: '20123456789',
          currency: 'PEN',
          total: '111.23',
        },
      ],
    }),
  );
  await page.route(`**/api/tax-documents/${DOC}`, (route) => route.fulfill({ json: origin }));
  await page.route(`**/api/tax-documents/${DOC}/lines*`, (route) =>
    route.fulfill({ json: { member: lines } }),
  );
  await page.route('**/api/credit-notes/next-number*', (route) =>
    route.fulfill({ json: { series: 'FC01', number: '58' } }),
  );
  await page.route('**/api/credit-notes/drafts', (route) => {
    if (route.request().method() === 'PUT') {
      drafted = route.request().postDataJSON();
      return route.fulfill({
        json: {
          id: 'draft-1',
          ...drafted,
          relatedFullNumber: 'F001-12345',
          updatedAt: '2026-11-22T10:00:00+00:00',
        },
      });
    }
    return route.fulfill({ json: [] });
  });
  await page.route('**/api/credit-notes/preview', (route) => {
    previewed = true;
    return route.fulfill({
      status: 200,
      contentType: 'application/pdf',
      body: '%PDF-1.4\n%%EOF',
    });
  });
  await page.route('**/api/tax-documents', (route) => {
    if (route.request().method() !== 'POST') return route.fallback();
    issued = route.request().postDataJSON();
    return route.fulfill({ status: 201, json: { id: 'note-1' } });
  });

  await page.setViewportSize({ width: 1440, height: 960 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/billing/credit-notes/new`);

  await expect(page.getByRole('heading', { name: 'Emitir nota de crédito' })).toBeVisible();
  await expect(page.getByTestId('credit-note-submit')).toHaveCount(0);

  // 1. Pick the affected voucher; its data and the next number appear.
  await page.getByTestId('credit-note-document-picker').click();
  await page.getByRole('option', { name: /F001-12345/ }).click();
  await expect(page).toHaveURL(new RegExp(`/billing/credit-notes/new/${DOC}$`));
  await expect(page.getByText('Comercial San Miguel S.A.C.').first()).toBeVisible();
  await expect(page.getByTestId('credit-note-next-number')).toHaveText('FC01-00000058');

  await expect(page.getByTestId('credit-note-lines')).toContainText('Audífonos Bluetooth #6');

  // Nothing is issuable before reason, detail and a quantity exist.
  await expect(page.getByTestId('credit-note-preview')).toBeDisabled();

  // 2. Reason 07 turns on the per-line restock column and the stock toggle.
  await page.getByTestId('credit-note-reason').click();
  await page.getByRole('option', { name: /07/ }).click();
  await page.getByTestId('credit-note-detail').fill('Devolución de un producto defectuoso');

  // 3. Return one unit: totals follow the chosen quantity.
  await page
    .getByRole('button', { name: /Aumentar|Incrementar|más/i })
    .first()
    .click();
  await expect(page.getByTestId('credit-note-total')).toContainText('55.62');
  const restock = page.getByRole('checkbox', { name: /Reingresar .* a inventario/ });
  await expect(restock).toBeChecked();
  await restock.uncheck();

  // 4. Draft and preview send only the choices, never amounts.
  await page.getByTestId('credit-note-save-draft').click();
  await expect.poll(() => drafted).toBeDefined();
  expect(drafted).toMatchObject({
    relatedTaxDocument: DOC,
    reasonCode: '07',
    lines: [{ lineNumber: 1, quantity: '1', restock: false }],
  });
  await expect(page).toHaveURL(/\?draft=draft-1$/);
  const popup = page.context().waitForEvent('page');
  await page.getByTestId('credit-note-preview').click();
  await popup;
  expect(previewed).toBe(true);

  // 5. Issue: the payload carries the restock flag; the user lands on the new note.
  await page.getByTestId('credit-note-submit').click();
  await expect.poll(() => issued).toBeDefined();
  expect(issued).toMatchObject({
    reasonCode: '07',
    relatedTaxDocument: expect.stringContaining(DOC),
    lines: [expect.objectContaining({ lineNumber: 1, quantity: '1', restock: false })],
  });
  await expect(page).toHaveURL(/\/billing\/credit-notes\/note-1$/);
});

test('an annulling reason returns everything, locks the quantities and asks before issuing', async ({
  page,
}) => {
  const seed = await getE2ESeed();
  let issued = false;
  await page.route(`**/api/tax-documents/${DOC}`, (route) => route.fulfill({ json: origin }));
  await page.route(`**/api/tax-documents/${DOC}/lines*`, (route) =>
    route.fulfill({ json: { member: lines } }),
  );
  await page.route('**/api/credit-notes/next-number*', (route) =>
    route.fulfill({ json: { series: 'FC01', number: '58' } }),
  );
  await page.route('**/api/credit-notes/eligible-documents*', (route) =>
    route.fulfill({ json: [] }),
  );
  await page.route('**/api/tax-documents', (route) => {
    if (route.request().method() !== 'POST') return route.fallback();
    issued = true;
    return route.fulfill({ status: 201, json: { id: 'note-2' } });
  });

  await page.setViewportSize({ width: 1440, height: 960 });
  await login(page, seed.username, seed.password);
  await page.goto(`${getE2EBaseUrl()}/billing/credit-notes/new/${DOC}`);
  await expect(page.getByTestId('credit-note-lines')).toContainText('Audífonos Bluetooth #6');
  await expect(page.getByTestId('credit-note-next-number')).toHaveText('FC01-00000058');
  await page.getByTestId('credit-note-reason').click();
  await page.getByRole('option', { name: /06/ }).click();
  await expect(page.getByTestId('credit-note-reason')).toHaveValue(/06/);
  await page.getByTestId('credit-note-detail').fill('Cliente desistió de la compra');
  await expect(page.getByTestId('credit-note-total')).toContainText('111.23');
  await expect(
    page.getByText('devuelve todos los ítems completos y repone su stock'),
  ).toBeVisible();

  await page.getByTestId('credit-note-submit').click();
  const confirm = page.getByRole('alertdialog', { name: 'Anular la venta' });
  await expect(confirm).toBeVisible();
  expect(issued).toBe(false);
  await confirm.getByRole('button', { name: 'Emitir y anular' }).click();
  await expect.poll(() => issued).toBe(true);
});
