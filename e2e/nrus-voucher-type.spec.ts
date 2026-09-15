/**
 * NRUS voucher type E2E
 * =====================
 * Un tenant en régimen NRUS debe emitir siempre Boleta (nunca Factura), aun
 * cuando el cliente de la venta tenga RUC de 11 dígitos — InvoiceCreationService
 * fuerza esa regla independientemente de lo que decidiría el flujo automático
 * por longitud de RUC. Bajo régimen GENERAL, el mismo cliente sigue recibiendo
 * Factura como antes.
 *
 * Requiere stack Docker con E2E_SEED_ENABLED=1. Este spec cambia `taxRegime`
 * del tenant de prueba en vivo; SIEMPRE lo revierte a GENERAL en un `finally`
 * para no dejar sucio el estado para specs posteriores (p.ej.
 * golden-path-fiscal.spec.ts asume régimen GENERAL).
 */

import { expect, test, type APIRequestContext } from '@playwright/test';
import { login } from './helpers/auth';
import { ensureOpenCashSession } from './helpers/cashbox';
import {
  createInvoiceForSale,
  getTaxDocument,
  listTaxDocumentsForSale,
  loginViaApi,
  type AuthSession,
} from './helpers/fiscal';
import { completePosCashPayment, selectPosCustomer, waitForPosCheckoutReady } from './helpers/pos';
import { E2E_SEED } from './support/seedConstants';
import { getE2EBaseUrl, getE2ESeed } from './support/e2eSeed';

const BASE_URL = getE2EBaseUrl();

async function setTaxRegime(request: APIRequestContext, auth: AuthSession, regime: 'GENERAL' | 'NRUS') {
  const response = await request.patch(`${BASE_URL}/api/tenant/settings`, {
    headers: { ...auth.headers, 'Content-Type': 'application/merge-patch+json' },
    data: { taxRegime: regime },
  });
  expect(response.ok(), `PATCH tenant/settings taxRegime=${regime} debe responder 2xx`).toBeTruthy();
  const body = (await response.json()) as { taxRegime?: string };
  expect(body.taxRegime, `taxRegime debe persistir como ${regime}`).toBe(regime);
}

async function sellToSeedCustomer(page: import('@playwright/test').Page): Promise<string> {
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

  await expect(page.locator('.pos-line__name').filter({ hasText: E2E_SEED.productName })).toBeVisible({
    timeout: 8000,
  });

  // E2E_SEED.customerDni es en realidad un RUC de 11 dígitos (nombre heredado
  // del seed) — es el caso que dispara Factura bajo GENERAL / Boleta forzada
  // bajo NRUS.
  await selectPosCustomer(page, {
    name: E2E_SEED.customerName,
    search: E2E_SEED.customerName,
  });

  // "Emitir boleta/factura SUNAT" defaults to the tenant's `autoInvoice`
  // setting, which the E2E seed tenant has OFF — turn it on explicitly so the
  // sale is created with makeInvoice=true (otherwise /invoice returns 400).
  await page.locator('.pos-footer-options__toggle').click();
  const emitInvoiceToggle = page.getByLabel('Emitir boleta o factura SUNAT');
  if (!(await emitInvoiceToggle.isChecked())) {
    await page
      .locator('.pos-footer-option-row')
      .filter({ hasText: 'Emitir boleta/factura SUNAT' })
      .locator('label')
      .click();
  }
  await expect(emitInvoiceToggle).toBeChecked();

  await completePosCashPayment(page);
  await waitForPosCheckoutReady(page);

  const createSaleResponsePromise = page.waitForResponse(
    (response) => /\/api\/sales(?:\?.*)?$/.test(response.url()) && response.request().method() === 'POST',
    { timeout: 20000 },
  );

  await page.getByTestId('pos-checkout-btn').click();

  const createSaleResponse = await createSaleResponsePromise;
  expect(createSaleResponse.ok(), 'POST /api/sales debe responder 2xx').toBeTruthy();

  const createdSale = (await createSaleResponse.json()) as { id?: string; '@id'?: string };
  const saleId =
    createdSale.id ??
    (typeof createdSale['@id'] === 'string' ? (createdSale['@id'].match(/\/([^/]+)$/)?.[1] ?? '') : '');
  expect(saleId, 'la venta POS debe devolver un id').toBeTruthy();

  await expect(page.getByTestId('pos-postsale-title')).toBeVisible({ timeout: 20000 });

  return saleId;
}

test.describe('NRUS — régimen tributario fuerza tipo de comprobante', () => {
  test('GENERAL emite Factura a cliente con RUC; NRUS fuerza Boleta para el mismo cliente', async ({
    page,
    request,
  }) => {
    const seed = await getE2ESeed();

    await login(page, seed.username, seed.password);
    await ensureOpenCashSession(page, BASE_URL);

    const auth = await loginViaApi(request, BASE_URL, seed.username, seed.password);

    try {
      await test.step('Régimen GENERAL + cliente con RUC → Factura', async () => {
        await setTaxRegime(request, auth, 'GENERAL');

        const saleId = await sellToSeedCustomer(page);
        const invoiceResponse = await createInvoiceForSale(request, BASE_URL, auth, saleId);
        if (!invoiceResponse.ok()) {
          // eslint-disable-next-line no-console
          console.log('[nrus-voucher-type] invoice error body:', await invoiceResponse.text());
        }
        expect(invoiceResponse.ok(), 'crear el comprobante fiscal debe responder 2xx').toBeTruthy();

        const documents = await listTaxDocumentsForSale(request, BASE_URL, auth, saleId);
        expect(documents.length, 'debe existir al menos un tax_document').toBeGreaterThan(0);

        const taxDocumentId = String(documents[0].id);
        const document = await getTaxDocument(request, BASE_URL, auth, taxDocumentId);
        expect(document.documentType, 'GENERAL + cliente con RUC debe emitir Factura (01)').toBe('01');
      });

      await page.getByTestId('pos-postsale-new-sale').click();

      await test.step('Régimen NRUS + mismo cliente con RUC → Boleta forzada', async () => {
        await setTaxRegime(request, auth, 'NRUS');

        const saleId = await sellToSeedCustomer(page);
        const invoiceResponse = await createInvoiceForSale(request, BASE_URL, auth, saleId);
        if (!invoiceResponse.ok()) {
          // eslint-disable-next-line no-console
          console.log('[nrus-voucher-type] invoice error body:', await invoiceResponse.text());
        }
        expect(invoiceResponse.ok(), 'crear el comprobante fiscal debe responder 2xx').toBeTruthy();

        const documents = await listTaxDocumentsForSale(request, BASE_URL, auth, saleId);
        expect(documents.length, 'debe existir al menos un tax_document').toBeGreaterThan(0);

        const taxDocumentId = String(documents[0].id);
        const document = await getTaxDocument(request, BASE_URL, auth, taxDocumentId);
        expect(
          document.documentType,
          'NRUS debe forzar Boleta (03) aunque el cliente tenga RUC',
        ).toBe('03');
      });
    } finally {
      // Nunca dejar el tenant de prueba en NRUS: specs posteriores (p.ej.
      // golden-path-fiscal) asumen régimen GENERAL.
      await setTaxRegime(request, auth, 'GENERAL');
    }
  });
});
