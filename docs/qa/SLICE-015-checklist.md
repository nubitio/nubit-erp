# QA Sign-off — SLICE-015

**Slice:** Drop legacy `nota` and `comprobante` tables  
**QA agent:** spex-qa  
**Date:** 2026-03-05  
**Verdict:** PASS ✅

---

## Acceptance Criteria

| AC  | Description                                                   | Result   | Evidence |
|-----|---------------------------------------------------------------|----------|----------|
| AC1 | `DROP TABLE IF EXISTS comprobante` present in migration file  | ✅ PASS  | `migrations/Version20260305120000_drop_nota_comprobante.php` line 26: `$this->addSql('DROP TABLE IF EXISTS comprobante');` |
| AC2 | `DROP TABLE IF EXISTS nota` present in migration file         | ✅ PASS  | Same file line 27: `$this->addSql('DROP TABLE IF EXISTS nota');` |
| AC3 | Migration detected by Doctrine as available/new               | ✅ PASS  | `doctrine:migrations:status` output row: `DoctrineMigrations\Version20260305120000DropNotaComprobante` shown as "Latest" (not yet executed — new migration) |
| AC4 | No `UPDATE comprobante` / `UPDATE nota` in `PopulateBranchIdCommand.php` | ✅ PASS  | `grep -rn "UPDATE comprobante\|UPDATE nota" src/Command/PopulateBranchIdCommand.php` → exit 1 (no matches). File has 103 lines with zero references to `comprobante` or `nota`. |
| AC5 | No bare `'comprobante'` in `TenantSchemaNormalizeCommand.php` | ✅ PASS  | `grep -n "'comprobante'" src/Command/TenantSchemaNormalizeCommand.php` → exit 1 (no matches). Permitted survivors `'tipo_comprobante'` / `'local_comprobante'` not present either. |
| AC6 | No `'nota'` in `TenantSchemaNormalizeCommand.php`             | ✅ PASS  | `grep -n "'nota'" src/Command/TenantSchemaNormalizeCommand.php` → exit 1 (no matches). |
| AC7 | No `TRUNCATE TABLE comprobante` in `SeedTestDataCommand.php`  | ✅ PASS  | `grep -n "TRUNCATE TABLE comprobante" src/Command/SeedTestDataCommand.php` → exit 1 (no matches). Replaced by `truncateIfExists($connection, 'comprobante')` at line 170. |
| AC8 | GrumPHP: phpcsfixer2 and phpmd pass; no new failures from SLICE-015 | ✅ PASS  | `phpcsfixer2` ✔, `phpmd` ✔. Failures: phpcs (3 line-length warnings), phpstan (3 errors), phpunit (7 errors + 1 failure) are all pre-existing — none touch SLICE-015 files. |
| AC9 | No test file contains DML against `nota` or `comprobante` tables | ✅ PASS  | `grep -rn "nota\|comprobante" tests/` returns 5 hits — all are Spanish-language string literals in `assertSame` / `assertTrue` assertion messages ("El comprobante ya esta informado", "La venta ya tiene un comprobante emitido"), not DML targeting those tables. |

---

## Pre-existing failures (not regressions)

The following tool failures exist on the baseline branch **before** SLICE-015 changes. None are caused by or worsened by this slice.

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

### phpunit (7 errors + 1 failure)
| Test | Type | Root cause |
|------|------|------------|
| `InventoryServiceIntegrationTest` (×5) | Error | Undefined `bcadd()` / `bcsub()` — `bcmath` extension missing in test environment |
| `E2eTestSeedServiceTest` (×2) | Error | `E2eTestSeedService::__construct()` signature mismatch — test not updated for new dependency |
| `SaleProcessorScenarioCTest::testScenarioBResolverReturnsSessionIdWhenSessionExists` | Failure | Mock expectation: `fetchAssociative` called 2× instead of 3× |

All of the above are confirmed pre-existing on the baseline branch and are **not** attributable to SLICE-015 changes.

---

## Migration Safety Notes

- `down()` calls `$this->abortIf(true, ...)` — rollback is intentionally blocked. This is correct per SLICE-015 spec (data destruction is deliberate).
- `DROP TABLE IF EXISTS` prevents errors on tenant DBs that may have already dropped these tables manually.
- The migration filename `Version20260305120000_drop_nota_comprobante.php` matches Doctrine's naming convention; class name `Version20260305120000DropNotaComprobante` is correctly detected.

---

## Sign-off

All 9 ACs pass. SLICE-015 is ready to merge.

**Signed:** spex-qa · 2026-03-05
