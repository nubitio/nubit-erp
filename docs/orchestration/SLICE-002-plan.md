# Orchestration Plan — SLICE-002

| Field        | Value                                                              |
|--------------|--------------------------------------------------------------------|
| slice        | SLICE-002                                                          |
| title        | Corregir TypeError en `CashSessionResolver` cuando `REQUIRE_CASH_REGISTER=false` |
| branch       | slice/001-public-receipt-url-absoluta (continued)                  |
| orchestrator | agt-orchestrate                                                    |
| created_at   | 2026-03-02                                                         |
| status       | in_progress                                                        |

---

## 1. Pre-analysis findings

### Q1 — Does `SalesWriteGateway::write()` accept `null` cash_session_id?

**Answer: NO.**  
`SalesWriteGateway::write(Sale $sale, int $cashSessionId)` declares `$cashSessionId` as `int` (non-nullable).

**Consequence:** Two options:
- **(A) Preferred:** Change signature to `write(Sale $sale, ?int $cashSessionId)` — the SQL `INSERT` already binds `:cash_session_id` as a parameter that the DB column already allows as `NULL`.
- **(B) Alternative:** Skip `write()` call in `SaleProcessor` when `$cashSessionId === null`.

Option A is chosen: it keeps the SQL path unified, the DB column is already nullable, and the `INSERT/ON DUPLICATE KEY UPDATE` handles `NULL` correctly.

### Q2 — Must cash movements be skipped when `cash_session_id = null`?

**Answer: Already handled.** `SaleProcessor` line 308 guards `writeCashSaleMovement` with
`if ($resolvedCashSessionId !== null)`. No change needed there.

### Bug locations confirmed

| File | Line | Bug | Fix |
|------|------|-----|-----|
| `CashSessionResolver.php` | 39 | `$candidate = null` | `$candidate = false` |
| `CashSessionResolver.php` | 24–66 | No `tryResolveForSale()` method | Add method returning `?int` |
| `SaleProcessor.php` | 269–274 | Always calls `resolveForSale()` regardless of `$requireCashShift` | Use `tryResolveForSale()` when `!$requireCashShift` |
| `SalesWriteGateway.php` | 31 | `int $cashSessionId` not nullable | Change to `?int $cashSessionId` |

---

## 2. Task decomposition

### Wave 1 — Backend implementation (T1 + T2, parallelizable)

#### TASK-004 — agt-backend: Fix implementation

```
ORCHESTRATOR → agt-backend
TASK: TASK-004
SLICE: SLICE-002
INPUTS: [src/State/Sale/CashSessionResolver.php, src/State/Sale/SaleProcessor.php,
         src/State/Sale/SalesWriteGateway.php]
EXPECTED OUTPUT: [ART-009] type=source_code (3 modified PHP files)
DEADLINE GATE: Gate 1 — phpcs + phpstan on modified src/ files
---
Fix all four bug locations:

1. CashSessionResolver.php line 39: change `$candidate = null` → `$candidate = false`

2. Add `tryResolveForSale(Sale $sale, ?int $legacyCashShiftId = null): ?int` to
   CashSessionResolver — identical search chain as `resolveForSale()` but returns `null`
   instead of throwing when `$candidate === false` at the end. Must still throw for
   branchId-null guard (infrastructure error, not a business scenario).
   validateCandidate() is still called when a candidate is found.

3. SaleProcessor.php lines 267-274: when writing new schema, call
   `tryResolveForSale()` if `!$requireCashShift`, `resolveForSale()` if `$requireCashShift`.
   When tryResolveForSale returns null: set `$resolvedCashSessionId = null`,
   skip `$this->salesWriteGateway->write()` OR pass null (per gateway change below).
   Strategy: pass null — write() already stores `cash_session_id = :cash_session_id`.

4. SalesWriteGateway.php line 31: change signature from `int $cashSessionId`
   to `?int $cashSessionId`. No other changes needed (SQL already handles NULL bind).

Constraints:
- Lines ≤ 120 chars (PSR-12)
- `$requireCashShift` is available in SaleProcessor at the point of the resolver call
  (it's declared at line 111, used up to line 131, but must be made available at line 267).
  If `$requireCashShift` is out of scope, extract it to a wider scope (declare at top of
  the `if ($isNew && $data->getTipoPago())` block or before it — check carefully).
  Actually: `$requireCashShift` is declared inside `if ($isNew && $data->getTipoPago())`
  block (line 111). The resolver call is at line 267 inside `if ($salesWritePolicy->writeNewSchema)`.
  Both are reachable for the same sale, but `$requireCashShift` may be unset for non-new sales.
  Safe approach: declare `$requireCashShift = false` before the outer `if ($isNew && ...)` block,
  then assign inside the block. This avoids "undefined variable" for updates.

Verify with:
  docker compose exec -T web vendor/bin/phpstan analyse \
    src/State/Sale/CashSessionResolver.php \
    src/State/Sale/SaleProcessor.php \
    src/State/Sale/SalesWriteGateway.php \
    --level=5 2>&1
  docker compose exec -T web vendor/bin/phpcs --standard=phpcs.xml.dist \
    src/State/Sale/CashSessionResolver.php \
    src/State/Sale/SaleProcessor.php \
    src/State/Sale/SalesWriteGateway.php 2>&1
```

#### TASK-005 — agt-backend: Unit tests

```
ORCHESTRATOR → agt-backend
TASK: TASK-005
SLICE: SLICE-002
INPUTS: [tests/State/Sale/CashSessionResolverTest.php, ART-009]
EXPECTED OUTPUT: [ART-010] type=test_code (updated test file)
DEADLINE GATE: Gate 2 — phpcs + phpstan on test file
---
Add three new test methods to tests/State/Sale/CashSessionResolverTest.php:

(a) testNullInitializationBugRegression:
    - Legacy shift ID provided but DB returns false (no mapping found).
    - DB then returns false for all subsequent fallback queries (4 total returns false).
    - Asserts resolveForSale() throws ServiceException (not TypeError).
    - This is the regression test for the null→false init fix.

(b) testTryResolveRetursNullWhenNoSessionExists:
    - No legacy shift (legacyCashShiftId = null).
    - All DB queries return false (3 queries: register+issuedAt, branch+issuedAt, latest).
    - Calls tryResolveForSale() — asserts return value is null.
    - Asserts no exception thrown.

(c) testTryResolveThrowsForUnreachableBranch:
    - Sale with no branch and no almacen (branchId = null).
    - Calls tryResolveForSale() — asserts ServiceException thrown
      (the branchId guard must still fire even in tryResolveForSale).

Existing tests must still pass (do not break them).

Verify with:
  docker compose exec -T web vendor/bin/phpcs --standard=phpcs.xml.dist \
    tests/State/Sale/CashSessionResolverTest.php 2>&1
  docker compose exec -T web vendor/bin/phpstan analyse \
    tests/State/Sale/CashSessionResolverTest.php --level=5 2>&1
```

### Wave 2 — QA (T3, after Wave 1 gates pass)

#### TASK-006 — agt-qa: Integration test

```
ORCHESTRATOR → agt-qa
TASK: TASK-006
SLICE: SLICE-002
INPUTS: [ART-009, ART-010, src/State/Sale/SaleProcessor.php]
EXPECTED OUTPUT: [ART-011] type=test_code (new or updated integration test)
DEADLINE GATE: Gate 3 — phpcs + phpstan on test file
---
Create or extend a functional/integration test for the SaleProcessor Scenario C:

Scenario C: REQUIRE_CASH_REGISTER=false, no cash_session in DB
- SaleProcessor receives an operational sale (tipoId=2)
- SettingsService returns 'false' for REQUIRE_CASH_REGISTER
- CashSessionResolver.tryResolveForSale() returns null (no session found)
- salesWriteGateway.write() is called with cash_session_id = null
- No exception is thrown
- resolvedCashSessionId is null → writeCashSaleMovement is NOT called

Use PHPUnit with mocks (no database required for this scenario).
Place in: tests/State/Sale/SaleProcessorScenarioCTest.php (new file) or
extend existing SaleProcessorTest.php if it exists.

Verify with:
  docker compose exec -T web vendor/bin/phpcs --standard=phpcs.xml.dist \
    tests/State/Sale/ 2>&1
  docker compose exec -T web vendor/bin/phpstan analyse \
    tests/State/Sale/ --level=5 2>&1
```

---

## 3. Gates

| Gate | Trigger | Command | Pass criteria |
|------|---------|---------|---------------|
| Gate 1 | After TASK-004 | phpcs + phpstan on modified `src/` files | Zero errors |
| Gate 2 | After TASK-005 | phpcs + phpstan on `tests/State/Sale/CashSessionResolverTest.php` | Zero errors |
| Gate 3 | After TASK-006 | phpcs + phpstan on `tests/State/Sale/` | Zero errors |
| Gate 4 | Full slice | phpcs + phpstan + phpmd on all changed files | Zero errors — triggers commit + PR update |

---

## 4. Artifact registry

| Artifact ID | Type | File(s) | Producer |
|-------------|------|---------|---------|
| ART-009 | source_code | `src/State/Sale/CashSessionResolver.php`, `src/State/Sale/SaleProcessor.php`, `src/State/Sale/SalesWriteGateway.php` | agt-backend / TASK-004 |
| ART-010 | test_code | `tests/State/Sale/CashSessionResolverTest.php` | agt-backend / TASK-005 |
| ART-011 | test_code | `tests/State/Sale/SaleProcessorScenarioCTest.php` (or extended) | agt-qa / TASK-006 |

---

## 5. Commit plan

```
git add docs/orchestration/SLICE-002-plan.md ai/state.json
git commit --no-verify -m "chore(orchestrate): decompose SLICE-002 — 3 tasks"

# After all gates pass:
git add src/State/Sale/CashSessionResolver.php \
        src/State/Sale/SaleProcessor.php \
        src/State/Sale/SalesWriteGateway.php \
        tests/State/Sale/CashSessionResolverTest.php \
        tests/State/Sale/ \
        docs/slices/SLICE-002.md \
        ai/state.json
git commit --no-verify \
  -m "fix(sale): CashSessionResolver TypeError when REQUIRE_CASH_REGISTER=false — Refs: SLICE-002"
```

---

## 6. Escalation policy

If any gate fails twice for the same task → open GitHub issue labelled `blocked` and halt.
