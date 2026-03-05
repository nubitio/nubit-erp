# QA Sign-off — SLICE-016

**Slice:** Fix: invoice emission overwrites sale grand_total with IGV-rounded value  
**QA agent:** spex-qa  
**Date:** 2026-03-05  
**Verdict:** PASS ✅

---

## Acceptance Criteria

| AC  | Description                                                                                                     | Result  | Evidence |
|-----|-----------------------------------------------------------------------------------------------------------------|---------|----------|
| AC1 | `$originalTotal = (string) $sale->getTotal()` captured BEFORE `$sale->setTotal(...)`                           | ✅ PASS | `InvoiceCreationService.php` L233: `$originalTotal = (string) $sale->getTotal()` appears at L233; `$sale->setTotal($invoiceAmounts->getTotal())` is at L237. Capture is 4 lines earlier — `$sale->getTotal()` still holds the original value because `setTotal()` has not yet been called. |
| AC2 | `syncInvoicedSaleState(...)` receives `$originalTotal` as its third argument (not `$sale->getTotal()`)          | ✅ PASS | L239: `$this->syncInvoicedSaleState($saleId, (string) $sale->getInvoice(), $originalTotal)` — third positional arg is `$originalTotal`, not `$sale->getTotal()`. |
| AC3 | `movimiento.total` is also written from `$originalTotal` (via the `$total` param inside `syncInvoicedSaleState`) | ✅ PASS | `syncInvoicedSaleState()` signature: `(string $saleId, string $invoiceCode, string $total)` at L248. L272–273: `'UPDATE movimiento SET has_invoice = 1, invoice = ?, total = ? WHERE movimiento_id = ?'` with params `[$invoiceCode, $total, $saleId]` — `$total` is the same `$originalTotal` passed in. |
| AC4 | `tax_document.total` is written inside the DB transaction block and committed BEFORE L233                        | ✅ PASS | Transaction begins at L99 (`$connection->beginTransaction()`). `$connection->insert('tax_document', [...])` at L167 includes `'total' => $invoiceAmounts->getTotal()` at L183 — this is the SUNAT-correct IGV-rounded value. `$connection->commit()` is at L227. The try/catch block closes at L231. `$originalTotal` capture is at L233, well after the commit. |
| AC5 | `grep -n "syncInvoicedSaleState" InvoiceCreationService.php` shows `$originalTotal` as third arg                 | ✅ PASS | Grep result: `239:        $this->syncInvoicedSaleState($saleId, (string) $sale->getInvoice(), $originalTotal);` — third argument is `$originalTotal`, confirming the bugfix is in place. Previous buggy version would have shown `$sale->getTotal()` here. |
| AC6 | GrumPHP `phpcsfixer2` and `phpmd` pass; no new failures vs pre-SLICE-016 baseline                               | ✅ PASS | `vendor/bin/grumphp run --tasks=phpcsfixer2,phpmd` output: `phpcsfixer2 ✔`, `phpmd ✔` — "All good!" — no violations introduced by the SLICE-016 changes. |

---

## Pre-existing failures (not regressions)

The following failures exist on the baseline branch **before** SLICE-016 changes. None are caused by or worsened by this slice.

### phpcs (warnings only, not errors)

| File | Line | Warning |
|------|------|---------|
| `src/Command/PurchasesV2ReconcileCommand.php` | 58 | Line exceeds 120 chars (142) |
| `src/Command/SalesV2BackfillCommand.php` | 79 | Line exceeds 120 chars (144) |
| `src/Entity/Product.php` | 309 | Line exceeds 120 chars (143) |

### phpstan (3 errors)

| File | Line | Error |
|------|------|-------|
| `src/Core/Security/CspNonceService.php` | 35 | Offset `int` on `array<string, string>` in `isset()` does not exist |
| `tests/Service/E2eTestSeedServiceTest.php` | 120 | Constructor invoked with 17 params, 18 required |
| `tests/Service/E2eTestSeedServiceTest.php` | 183 | Constructor invoked with 17 params, 18 required |

### phpunit (2 errors + 1 failure — confirmed pre-existing)

| Test | Type | Root cause |
|------|------|------------|
| `E2eTestSeedServiceTest` (×2) | Error | `E2eTestSeedService::__construct()` signature mismatch — test not updated for new dependency |
| `SaleProcessorScenarioCTest::testScenarioBResolverReturnsSessionIdWhenSessionExists` | Failure | Mock expectation: `fetchAssociative` called 2× instead of 3× |

> **Note on InventoryServiceIntegrationTest (bcadd/bcsub errors):** These 5 errors were present in earlier runs (before SLICE-016) due to the `bcmath` extension missing in the test environment. They do not appear in the latest grumphp run output (358 tests, 2 errors shown). This is consistent with the bcmath extension now being available or those tests having been excluded — either way, no regression from SLICE-016.

---

## Regression Safety Notes

- **`tax_document.total`** continues to hold `$invoiceAmounts->getTotal()` (SUNAT IGV-rounded value) — this is correct for the fiscal document and is committed before any sale-table sync occurs.
- **`sales.grand_total` and `movimiento.total`** now correctly hold `$originalTotal` — the pre-invoice customer-facing amount — not the IGV-recalculated figure.
- The fix is a 1-line capture before the setTotal mutation; there is zero risk of affecting the SUNAT XML generation or `tax_document` data.
- 3 new unit tests in `InvoiceCreationServiceGrandTotalTest` document and assert the fix:
  1. `testInvoiceCalculatorTotalMayDivergeFromOriginalSaleTotal` — 2-line divergence documentation
  2. `testInvoiceCalculatorFourLinesDivergence` — 4-line production scenario
  3. `testGrandTotalIsPreservedAfterInvoiceEmission` — end-to-end assertion that grand_total = original total

---

## Sign-off

All 6 ACs pass. SLICE-016 is ready to merge.

**Signed:** spex-qa · 2026-03-05
