/**
 * Golden path fiscal E2E
 * ======================
 * POS venta → comprobante → envío SUNAT (stub) → CDR persistido → PDF
 *
 * Requiere stack Docker con E2E_SEED_ENABLED=1 (activa stub SUNAT sin Lycet).
 */

import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import {
  buildReceiptPdfUrl,
  createInvoiceForSale,
  getTaxDocument,
  getVoucherForSale,
  listTaxDocumentsForSale,
  loginViaApi,
  sendTaxDocument,
} from './helpers/fiscal';
import { completePosCashPayment, selectPosCustomer, waitForPosCheckoutReady } from './helpers/pos';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

test.describe('Golden path fiscal — POS a SUNAT y PDF', () => {
  test('venta POS, factura, envío SUNAT, CDR y PDF', async ({ page, request }) => {
    const seed = await getE2ESeed();

    await test.step('Login y abrir caja', async () => {
      await login(page, seed.username, seed.password);
      await ensureOpenCashSession(page, BASE_URL);
    });

    let saleId = '';

    await test.step('Venta en POS', async () => {
      await page.goto(`${BASE_URL}/#/pos`);
      await expect(page.getByTestId('pos-product-search')).toBeVisible({ timeout: 25000 });

      const skeleton = page.locator('.pos-skeleton-grid');
      try {
        await skeleton.waitFor({ state: 'visible', timeout: 5000 });
        await skeleton.waitFor({ state: 'hidden', timeout: 30000 });
      } catch {
        // catálogo ya listo
      }

      const searchInput = page.getByTestId('pos-product-search');
      await searchInput.fill(E2E_SEED.productName);

      const productCard = page
        .getByTestId('pos-product-card')
        .filter({ hasText: E2E_SEED.productName })
        .filter({ hasNotText: E2E_SEED.product2Name })
        .first();
      await productCard.waitFor({ state: 'visible', timeout: 15000 });

      const addBtn = productCard.getByTestId('pos-product-card-add');
      if (await addBtn.isVisible({ timeout: 2000 })) {
        await addBtn.click();
      } else {
        await productCard.click();
      }

      await expect(
        page.locator('.pos-line__name').filter({ hasText: E2E_SEED.productName }),
      ).toBeVisible({
        timeout: 8000,
      });

      await selectPosCustomer(page, {
        name: E2E_SEED.customerName,
        search: E2E_SEED.customerName,
      });

      // Deliberately dirty per-sale state. A successful checkout must not
      // carry any of it into the next customer/order.
      const optionsToggle = page.locator('.pos-footer-options__toggle');
      await optionsToggle.click();
      const emitInvoiceToggle = page.getByLabel('Emitir boleta o factura SUNAT');
      const defaultEmitInvoice = await emitInvoiceToggle.isChecked();
      await page
        .locator('.pos-footer-option-row')
        .filter({ hasText: 'Emitir boleta/factura SUNAT' })
        .locator('label')
        .click();
      await page.locator('.nb-chip').filter({ hasText: /^5%$/ }).click();
      const noteToggle = page.locator('.pos-note-section__toggle');
      await noteToggle.click();
      await page.getByLabel('Nota interna de la venta').fill('No debe pasar a la siguiente venta');

      await completePosCashPayment(page);
      await waitForPosCheckoutReady(page);

      const createSaleResponsePromise = page.waitForResponse(
        (response) =>
          /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
        { timeout: 20000 },
      );

      await page.getByTestId('pos-checkout-btn').click();

      const createSaleResponse = await createSaleResponsePromise;
      expect(createSaleResponse.ok(), 'POST /api/sales debe responder 2xx').toBeTruthy();

      const createdSale = (await createSaleResponse.json()) as { id?: string; '@id'?: string };
      saleId =
        createdSale.id ??
        (typeof createdSale['@id'] === 'string'
          ? (createdSale['@id'].match(/\/([^/]+)$/)?.[1] ?? '')
          : '');
      expect(saleId, 'la venta POS debe devolver un id').toBeTruthy();

      await expect(page.getByTestId('pos-postsale-title')).toBeVisible({ timeout: 20000 });
      await page.getByTestId('pos-postsale-new-sale').click();

      await expect(page.locator('.pos-cart-lines')).toContainText('Orden vacía');
      await expect(page.locator('.pos-footer-options__toggle')).toHaveAttribute(
        'aria-expanded',
        'false',
      );
      await expect(page.locator('.pos-note-section__toggle')).toHaveAttribute(
        'aria-expanded',
        'false',
      );
      await expect(page.getByTestId('pos-customer').locator('input').first()).not.toHaveValue(
        new RegExp(E2E_SEED.customerName, 'i'),
      );
      await page.locator('.pos-footer-options__toggle').click();
      await expect(page.getByLabel('Emitir boleta o factura SUNAT')).toBeChecked({
        checked: defaultEmitInvoice,
      });
      await expect(page.locator('.nb-chip').filter({ hasText: /^Ninguno$/ })).toHaveClass(
        /is-active/,
      );
      await page.locator('.pos-note-section__toggle').click();
      await expect(page.getByLabel('Nota interna de la venta')).toHaveValue('');
    });

    const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

    await test.step('Emitir comprobante desde la venta', async () => {
      const invoiceResponse = await createInvoiceForSale(request, BASE_URL, auth, saleId);
      expect(invoiceResponse.ok(), 'crear el comprobante fiscal debe responder 2xx').toBeTruthy();
    });

    let taxDocumentId = '';

    await test.step('Resolver tax document de la venta', async () => {
      const documents = await listTaxDocumentsForSale(request, BASE_URL, auth, saleId);
      expect(documents.length, 'debe existir al menos un tax_document').toBeGreaterThan(0);

      const primary = documents.find((doc) => doc.id) ?? documents[0];
      taxDocumentId = String(primary.id);
      expect(taxDocumentId).toBeTruthy();
    });

    await test.step('Enviar a SUNAT (stub E2E)', async () => {
      await sendTaxDocument(request, BASE_URL, auth, taxDocumentId);
    });

    await test.step('Verificar CDR y estado SUNAT aceptado', async () => {
      const document = await getTaxDocument(request, BASE_URL, auth, taxDocumentId);

      expect(document.sunatStatus, 'SUNAT status debe ser código de aceptación').toBe('0');
      expect(document.cdrPath, 'cdr_path debe persistirse en tax_document').toBeTruthy();
      expect(String(document.cdrPath)).toContain('R-');
      const events = await request.get(`${BASE_URL}/api/tax-documents/${taxDocumentId}/events`, {
        headers: auth.headers,
      });
      expect(events.status()).toBe(200);
      const history = await events.json();
      expect(history).toEqual(
        expect.arrayContaining([expect.objectContaining({ eventType: 'sunat_send', code: '0' })]),
      );
      expect(history.every((event: Record<string, unknown>) => !('payloadJson' in event))).toBe(
        true,
      );
      const consult = await request.get(`${BASE_URL}/api/tax-documents/${taxDocumentId}/consult`, {
        headers: auth.headers,
      });
      expect(consult.status()).toBe(200);
      const updatedEvents = await request.get(
        `${BASE_URL}/api/tax-documents/${taxDocumentId}/events`,
        { headers: auth.headers },
      );
      expect(await updatedEvents.json()).toEqual(
        expect.arrayContaining([
          expect.objectContaining({ eventType: 'sunat_consult', code: '0' }),
        ]),
      );
      const pdfUrl = buildReceiptPdfUrl(BASE_URL, document.documentType ?? '03', taxDocumentId);
      for (const kind of ['xml', 'cdr']) {
        const file = await request.get(pdfUrl.replace(/\/pdf$/, `/${kind}`));
        expect(file.status()).toBe(200);
        const bytes = await file.body();
        expect(bytes.length).toBeGreaterThan(0);
        if (kind === 'cdr') expect(bytes.subarray(0, 2).toString()).toBe('PK');
        else expect(bytes.toString()).toMatch(/^\s*<\?xml/);
      }
    });

    await test.step('Descargar PDF del comprobante', async () => {
      const voucher = await getVoucherForSale(request, BASE_URL, auth, saleId);
      expect(voucher.voucher_series).toBeTruthy();
      expect(voucher.voucher_number).toBeTruthy();

      const voucherType = voucher.voucher_type ?? '03';
      const pdfUrl = buildReceiptPdfUrl(BASE_URL, voucherType, taxDocumentId);
      const pdfResponse = await request.get(pdfUrl);

      expect(pdfResponse.ok(), 'PDF del comprobante debe descargarse').toBeTruthy();
      expect(pdfResponse.headers()['content-type'] ?? '').toContain('application/pdf');

      const pdfBytes = await pdfResponse.body();
      expect(pdfBytes.byteLength, 'PDF no debe estar vacío').toBeGreaterThan(100);
      expect(pdfBytes.subarray(0, 4).toString('utf8')).toBe('%PDF');
    });
  });
});
