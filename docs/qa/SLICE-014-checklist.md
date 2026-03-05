# SLICE-014 QA Verification Checklist
## Sale Series/Number — Atomic Counter + V2 Propagation

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Slice ID         | SLICE-014                                          |
| Priority         | P1                                                 |
| QA Agent         | spex-qa                                            |
| Artifact         | A014-5                                             |
| Date             | 2026-03-05                                         |
| Gate             | `make check` / CI pipeline (`grumphp` + PHPUnit)   |
| Status           | PENDING — awaiting deployment verification         |

---

## Table of Contents

1. [Pre-flight Checks — Deployment Order](#1-pre-flight-checks--deployment-order)
2. [AC1 — Concurrent Uniqueness](#2-ac1--concurrent-uniqueness)
3. [AC2 — AUTHORITATIVE Mode](#3-ac2--authoritative-mode)
4. [AC3 — DUAL_WRITE Mode](#4-ac3--dual_write-mode)
5. [AC4 — Backfill Correctness](#5-ac4--backfill-correctness)
6. [AC5 — Sequence Seed High-Water Mark](#6-ac5--sequence-seed-high-water-mark)
7. [AC6 — Cash Movement Description](#7-ac6--cash-movement-description)
8. [Risk Callout — R1 Row Contention Under High TPS](#8-risk-callout--r1-row-contention-under-high-tps)
9. [Sign-Off Summary](#9-sign-off-summary)

---

## 1. Pre-flight Checks — Deployment Order

> ⚠️ **Migrations must run in strict order.** Deploying application code before
> the schema migration (T014-1) will crash on `INSERT INTO sale_sequences` and
> on `sales.serie` / `sales.numero` column references. Switching to AUTHORITATIVE
> mode before the data migration (T014-4) runs will leave backfillable `sales`
> rows with `NULL` serie/numero.

### 1.1 Required Deployment Sequence

```
Step 1 — Run Version20260305100000_sale_sequences_and_serie_numero (T014-1)
          Creates: sale_sequences table
          Alters:  sales — adds serie VARCHAR(10), numero VARCHAR(20), index

Step 2 — Deploy application code (T014-2: SaleUtils + T014-3: SalesWriteGateway)
          Requires sale_sequences and sales.serie/numero to already exist

Step 3 — Run Version20260305110000_backfill_sale_serie_numero (T014-4)
          Backfills: sales.serie, sales.numero from movimiento
          Seeds:     sale_sequences high-water marks from movimiento

Step 4 — (Optional — switch to AUTHORITATIVE) Set sales_v2_mode = 'v2_authoritative'
          MUST NOT happen before Step 3 — otherwise new sales compete with
          un-seeded sequences and could re-issue already-used numbers
```

### 1.2 Verify Schema Migration Ran (Step 1)

```sql
-- sale_sequences table must exist
SHOW TABLES LIKE 'sale_sequences';
-- Expected: returns exactly 1 row: 'sale_sequences'

-- sales table must have serie and numero columns
SHOW COLUMNS FROM sales LIKE 'serie';
SHOW COLUMNS FROM sales LIKE 'numero';
-- Expected: each returns 1 row with Type='varchar(10)' / 'varchar(20)', Null='YES'

-- Index must exist
SHOW INDEX FROM sales WHERE Key_name = 'idx_sales_serie_numero';
-- Expected: 2 rows (one for serie, one for numero columns in composite index)
```

**PASS:** All three queries return non-empty results.  
**FAIL:** Any empty result → Step 1 migration did not run. Run manually:
```bash
php bin/console doctrine:migrations:execute \
  'DoctrineMigrations\Version20260305100000_sale_sequences_and_serie_numero' \
  --up --no-interaction
```

### 1.3 Verify Data Migration Ran (Step 3)

```sql
-- sale_sequences must be non-empty (or legitimately empty if no movimiento data exists)
SELECT COUNT(*) AS sequence_count FROM sale_sequences;
-- Expected: ≥ 0 (if 0, verify movimiento has no non-null serie values — see AC5)

-- Idempotency: re-running must not change the max sequences downward
SELECT serie, last_number FROM sale_sequences ORDER BY serie;
-- Record this baseline before any AUTHORITATIVE mode is enabled
```

---

## 2. AC1 — Concurrent Uniqueness

### Criterion
≥20 parallel `POST /api/sales` on the same series must all return distinct `numero` values.

### What Was Implemented
`SaleUtils::getSaleNumber()` (`src/State/Sale/SaleUtils.php`, lines 58–83) executes
an atomic `INSERT ... ON DUPLICATE KEY UPDATE last_number = last_number + 1` inside
an explicit transaction, then reads back `last_number` in the **same transaction**.
Because the `INSERT/UPDATE` acquires a row-level exclusive lock on the `sale_sequences`
primary key, concurrent callers serialize at the InnoDB row lock — no two transactions
can increment and read the same value.

### Verification Command

```bash
# Option A — xargs parallel HTTP calls (requires valid auth token and running app)
AUTH="Bearer <token>"
SERIES="B001"

seq 1 20 | xargs -P20 -I{} \
  curl -s -o /dev/null -w "%{http_code}\n" \
  -X POST https://app.example.com/api/sales \
  -H "Authorization: $AUTH" \
  -H "Content-Type: application/json" \
  -d '{"warehouseId":"<id>","items":[...],"paymentMethodId":1}' \
  | sort | uniq -c
# Expected output: 20 lines of "200" (or the sale success code), no errors

# Option B — inspect resulting DB rows directly after concurrent inserts
SELECT
    serie,
    numero,
    COUNT(*) AS occurrences
FROM sales
WHERE serie = 'B001'
  AND created_at >= NOW() - INTERVAL 5 MINUTE
GROUP BY serie, numero
HAVING occurrences > 1;
-- Expected: 0 rows (no duplicate serie+numero pairs)
```

### SQL Spot-Check (direct DB — no app needed)

```sql
-- Simulate N increments in sequence to verify counter monotonicity
SELECT serie, last_number FROM sale_sequences WHERE serie = 'B001';
-- After 20 new sales on B001: last_number should equal (seed_value + 20)
```

### PASS
- All 20 HTTP responses return success (HTTP 2xx).
- The `HAVING occurrences > 1` query returns **0 rows**.
- `sale_sequences.last_number` for the tested serie increased by exactly 20.

### FAIL
- Any duplicate in the `HAVING` query → the atomic lock is not working.  
  Check: Is `sale_sequences` table using InnoDB engine?
  ```sql
  SELECT ENGINE FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_sequences';
  -- Expected: InnoDB
  ```
- Duplicate caused by a connection not in the transaction pool → check `Doctrine\DBAL\Connection`
  configuration for `persistent_connections`.
- HTTP errors → check application logs:
  `tail -f var/log/prod.log | grep "getSaleNumber\|sale_sequences"`

---

## 3. AC2 — AUTHORITATIVE Mode

### Criterion
With `sales_v2_mode = 'v2_authoritative'`:
create 1 sale → `movimiento` has **NO** new row → `sales` row has non-null `serie` AND `numero`
→ `sale_sequences.last_number` incremented by 1.

### What Was Implemented
- `SalesWritePolicyResolver` (`src/State/Sale/SalesWritePolicyResolver.php`, lines 33–40)
  sets `skipLegacyPersist: true, writeNewSchema: true, strict: true` when mode is `v2_authoritative`.
- `SaleProcessor` (lines 218–232) skips `$this->persistProcessor->process()` when
  `$skipLegacyPersist` is `true`.
- `SaleUtils::fillSaleValues()` (lines 88–118) calls `getSaleNumber()` which increments
  `sale_sequences` regardless of whether a `movimiento` row is created.
- `SalesWriteGateway::write()` (lines 88–177) `INSERT`s `serie` and `numero` into `sales`.

### Pre-condition
```sql
-- Record baseline BEFORE the test sale
SELECT last_number FROM sale_sequences WHERE serie = '<test_serie>';
-- Note this value as :baseline_number
```

### Verification Queries

```sql
-- 1. Confirm NO new movimiento row was created for this sale
SELECT COUNT(*) AS legacy_rows
FROM movimiento
WHERE movimiento_id = '<new_sale_id>';
-- Expected: 0

-- 2. Confirm sales row has non-null serie and numero
SELECT id, serie, numero
FROM sales
WHERE id = '<new_sale_id>';
-- Expected: serie IS NOT NULL, numero IS NOT NULL
-- (e.g. serie='B001', numero='1234')

-- 3. Confirm sale_sequences incremented by exactly 1
SELECT last_number
FROM sale_sequences
WHERE serie = '<test_serie>';
-- Expected: :baseline_number + 1
```

### How to Set Mode (for testing)

```sql
-- Set to v2_authoritative (the setting key is 'sales_v2_mode')
UPDATE options SET value = 'v2_authoritative' WHERE name = 'sales_v2_mode';
-- After test, restore:
UPDATE options SET value = 'dual_write' WHERE name = 'sales_v2_mode';
```

Or via the CLI baseline command:
```bash
php bin/console app:sales-v2:baseline --mode=v2_authoritative
```

### PASS
1. `legacy_rows = 0`
2. `serie IS NOT NULL AND numero IS NOT NULL` for the new `sales` row
3. `last_number = :baseline_number + 1`

### FAIL
- `legacy_rows > 0` → `skipLegacyPersist` is not being set; verify the `options` table
  row `name = 'sales_v2_mode'` has value `'v2_authoritative'` (exact string, lowercase).
- `serie IS NULL` or `numero IS NULL` → `SalesWriteGateway::write()` was not called,
  or `SaleUtils::fillSaleValues()` was not reached.  
  Check logs: `grep "Skipping legacy\|Starting sale processing\|Sales V2 runtime mode" var/log/prod.log`
- `last_number` unchanged → transaction in `getSaleNumber()` rolled back; see exception in logs.

---

## 4. AC3 — DUAL_WRITE Mode

### Criterion
With `sales_v2_mode = 'dual_write'`:
create 1 sale → `movimiento.serie == sales.serie` AND `movimiento.numero == sales.numero`.

### What Was Implemented
- In `dual_write` mode, `SaleProcessor` persists `movimiento` **and** calls
  `SalesWriteGateway::write()`.
- `SaleUtils::fillSaleValues()` sets `Sale::$serie` and `Sale::$numero` on the entity
  before `movimiento` is persisted — the same field values flow into both tables.
- `SalesWriteGateway::write()` binds `:serie => $sale->getSerie()` and
  `:numero => $sale->getNumero()` (lines 181–182).
- The `ON DUPLICATE KEY UPDATE` clause uses `COALESCE(VALUES(serie), serie)` and
  `COALESCE(VALUES(numero), numero)` to preserve existing values on re-runs
  (lines 156–157).

### Verification Query

```sql
-- Both tables must agree on serie and numero for the newly created sale
SELECT
    m.movimiento_id,
    m.serie  AS mov_serie,
    m.numero AS mov_numero,
    s.serie  AS sales_serie,
    s.numero AS sales_numero,
    CASE
        WHEN m.serie = s.serie AND m.numero = s.numero THEN 'PASS'
        ELSE 'FAIL'
    END AS result
FROM movimiento m
INNER JOIN sales s ON s.legacy_movimiento_id = m.movimiento_id
WHERE m.movimiento_id = '<new_sale_id>';
```

### PASS
- `result = 'PASS'`
- `mov_serie = sales_serie` (e.g. both `'B001'`)
- `mov_numero = sales_numero` (e.g. both `'47'`)

### FAIL
- `result = 'FAIL'` → possible causes:
  - `sales` row not created → `SalesWriteGateway::write()` failed silently (check logs
    for `"Sales V2 dual-write failed"`).
  - `sales.serie IS NULL` → `COALESCE` on UPDATE preserved a pre-existing NULL;
    check if the row existed before from a partial previous write.
  - `movimiento.serie` and `sales.serie` differ → the entity was mutated between the
    two writes (should not happen; both use the same `Sale` object reference).
- No `sales` row at all → V2 write disabled: check `app_option_sales_v2_dual_write_enabled`
  in the `options` table.

---

## 5. AC4 — Backfill Correctness

### Criterion
After both migrations run: the count of `sales` rows that are joined to `movimiento`
and still have `NULL` serie when `movimiento.serie` is non-empty must equal **0**.

### What Was Implemented
`Version20260305110000_backfill_sale_serie_numero::up()` runs:
```sql
UPDATE sales s
INNER JOIN movimiento m ON m.movimiento_id = s.legacy_movimiento_id
SET s.serie  = m.serie,
    s.numero = m.numero
WHERE s.serie IS NULL
  AND m.serie IS NOT NULL
  AND m.serie != ''
```
The `WHERE s.serie IS NULL` guard makes the migration **idempotent** — safe to re-run.

### Exact Verification Query (from AC4 specification)

```sql
SELECT COUNT(*) AS unbackfilled
FROM sales s
INNER JOIN movimiento m ON m.movimiento_id = s.legacy_movimiento_id
WHERE s.serie IS NULL
  AND m.serie IS NOT NULL
  AND m.serie != '';
```

### PASS
`unbackfilled = 0`

### FAIL
`unbackfilled > 0` → the backfill migration did not run, or ran before the
`sales.serie` column was added (schema migration must precede data migration).

**Remediation:**
```bash
# Re-run data migration (safe — idempotent):
php bin/console doctrine:migrations:execute \
  'DoctrineMigrations\Version20260305110000_backfill_sale_serie_numero' \
  --up --no-interaction
```

Then re-run the verification query. If still non-zero:
```sql
-- Inspect a failing sample:
SELECT s.id, s.legacy_movimiento_id, m.serie, m.numero
FROM sales s
INNER JOIN movimiento m ON m.movimiento_id = s.legacy_movimiento_id
WHERE s.serie IS NULL
  AND m.serie IS NOT NULL
  AND m.serie != ''
LIMIT 10;
```
Check for `legacy_movimiento_id` mismatches or character encoding issues on `serie`.

---

## 6. AC5 — Sequence Seed High-Water Mark

### Criterion
For each distinct `serie` in `movimiento`:
`sale_sequences.last_number >= MAX(CAST(numero AS UNSIGNED))` for that serie.

### What Was Implemented
`Version20260305110000_backfill_sale_serie_numero::up()` runs:
```sql
INSERT INTO sale_sequences (serie, last_number)
SELECT   serie,
         MAX(CAST(numero AS UNSIGNED))
FROM     movimiento
WHERE    serie  IS NOT NULL
  AND    serie  != ''
  AND    numero REGEXP '^[0-9]+$'
GROUP BY serie
ON DUPLICATE KEY UPDATE
    last_number = GREATEST(last_number, VALUES(last_number))
```
`GREATEST()` prevents overwriting a counter that was already advanced by new sales
if there was a time gap between the schema migration and the data migration.

### Verification Query

```sql
-- All series in movimiento must have a sequence entry with last_number >= the max movimiento numero
SELECT
    m.serie,
    MAX(CAST(m.numero AS UNSIGNED)) AS max_movimiento_numero,
    ss.last_number                  AS seeded_last_number,
    CASE
        WHEN ss.last_number IS NULL
          THEN 'FAIL — no sequence row'
        WHEN ss.last_number < MAX(CAST(m.numero AS UNSIGNED))
          THEN 'FAIL — counter below high-water mark'
        ELSE 'PASS'
    END AS result
FROM movimiento m
LEFT JOIN sale_sequences ss ON ss.serie = m.serie
WHERE m.serie  IS NOT NULL
  AND m.serie  != ''
  AND m.numero REGEXP '^[0-9]+$'
GROUP BY m.serie, ss.last_number
HAVING result != 'PASS';
```

### PASS
Query returns **0 rows** (no failing series).

### FAIL
Any returned rows indicate the seeding `INSERT ... ON DUPLICATE KEY UPDATE` did not
run, or a `serie` value in `movimiento` was not matched (encoding/collation mismatch).

**Remediation:**
```bash
php bin/console doctrine:migrations:execute \
  'DoctrineMigrations\Version20260305110000_backfill_sale_serie_numero' \
  --up --no-interaction
```

**Note on non-numeric numero values:** The seed SQL filters `numero REGEXP '^[0-9]+$'`
— `movimiento` rows with non-numeric `numero` (e.g. `'F001-00001'`) are intentionally
excluded from seeding. Verify that such series do not need sequence tracking:
```sql
SELECT DISTINCT serie
FROM movimiento
WHERE serie IS NOT NULL
  AND serie != ''
  AND (numero IS NULL OR numero NOT REGEXP '^[0-9]+$')
ORDER BY serie;
```

---

## 7. AC6 — Cash Movement Description

### Criterion
With DUAL_WRITE + cash payment sale:
`cash_movements.description = 'Venta {serie}-{numero}'` (not `'Venta en efectivo'`).

### What Was Implemented
`SaleProcessor` (lines 317–328) builds the description before calling
`SalesWriteGateway::writeCashSaleMovement()`:
```php
$serie  = $data->getSerie() ?? '';
$numero = $data->getNumero() ?? '';
$saleRef = $serie !== '' && $numero !== '' ? "{$serie}-{$numero}" : null;
// ...
description: $saleRef !== null ? "Venta {$saleRef}" : 'Venta en efectivo',
```
This branch is reached only when `$resolvedCashSessionId !== null` AND the sale has
a positive cash collected amount. `SalesWriteGateway::writeCashSaleMovement()` accepts
`$description` as an explicit parameter and inserts it directly (line 604).

The **legacy** `cash_movements` row (via `createCashMovementForSale()`, line 465) uses
`'Venta en efectivo N° {serie}-{numero}'` — but that path is only taken when
`isLegacyCashMovementDisabled()` returns `false` (i.e. non-AUTHORITATIVE mode).

The AC specifically targets the **V2** `cash_movements` table populated by
`SalesWriteGateway::writeCashSaleMovement()`.

### Pre-conditions
- `sales_v2_mode = 'dual_write'` (or `'v2_authoritative'` — both call `writeCashSaleMovement`)
- Sale uses a cash payment method (tip_pago that resolves to cash)
- A `cash_session` exists for the user's open cash shift

### Verification Query

```sql
-- Find the cash_movements row written by SalesWriteGateway for the test sale
SELECT
    cm.sale_id,
    cm.description,
    s.serie,
    s.numero,
    CONCAT('Venta ', s.serie, '-', s.numero) AS expected_description,
    CASE
        WHEN cm.description = CONCAT('Venta ', s.serie, '-', s.numero)
          THEN 'PASS'
        ELSE 'FAIL'
    END AS result
FROM cash_movements cm
INNER JOIN sales s ON s.id = cm.sale_id
WHERE cm.sale_id = '<new_sale_id>'
  AND cm.movement_type = 'SALE_IN';
```

### PASS
- `result = 'PASS'`
- `description` is exactly `'Venta B001-47'` (matching `serie` + `-` + `numero`)

### FAIL
- `description = 'Venta en efectivo'` → `serie` or `numero` was empty/NULL at the time
  `SaleProcessor` built the description. Check:
  ```sql
  SELECT serie, numero FROM sales WHERE id = '<new_sale_id>';
  ```
  If both are non-null, the issue is in processor ordering (cash movement written before
  `fillSaleValues()` completes — not possible in current code flow, but worth confirming
  with a log trace).
- No `cash_movements` row at all → `resolvedCashSessionId` was null (no open cash session
  for this sale context), or the cash amount resolved to zero.
- `description = 'Venta en efectivo N° B001-47'` (note `N° `) → you are looking at the
  **legacy** `cash_movements` row, not the V2 `cash_movements` row. Confirm by checking
  `movement_type = 'SALE_IN'` and the `idempotency_key` pattern (V2 row uses
  `md5('cash-sale:{saleId}:{cashSessionId}')` as the UUID seed).

---

## 8. Risk Callout — R1 Row Contention Under High TPS

### Risk Description
`sale_sequences` has one row per `serie`. Under very high throughput (e.g. >200 concurrent
sales/second on the same series) all writers compete for the **same row lock**. This is
intentional — it serializes counter increments — but can cause:
- Increased lock wait times (`innodb_lock_wait_timeout`, default 50 s)
- Deadlock candidates if other transactions hold locks on related rows and attempt a
  cross-table join while `sale_sequences` is locked
- Response latency spikes visible as P99 on `POST /api/sales`

### Why It Is Acceptable
The `sale_sequences` transaction in `getSaleNumber()` is intentionally minimal:
one `INSERT ... ON DUPLICATE KEY UPDATE` + one `SELECT` + commit. No joins, no
sub-queries. Lock hold time is sub-millisecond under normal conditions. The
existing `movimiento MAX()+1` approach had the same contention problem **plus**
a wider window (full `movimiento` table scan under `FOR UPDATE`).

### Monitoring Queries

```sql
-- 1. Check current InnoDB lock waits related to sale_sequences
SELECT
    r.trx_id            AS waiting_trx_id,
    r.trx_mysql_thread_id AS waiting_thread,
    b.trx_id            AS blocking_trx_id,
    b.trx_mysql_thread_id AS blocking_thread,
    b.trx_started       AS blocking_started,
    TIMESTAMPDIFF(SECOND, b.trx_started, NOW()) AS blocking_age_s
FROM information_schema.INNODB_LOCK_WAITS w
JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id
JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id;
-- Alert threshold: blocking_age_s > 5 seconds

-- 2. Check for lock wait timeouts in recent logs (application-level)
-- In application log (var/log/prod.log):
-- grep "No se pudo generar el número de comprobante" var/log/prod.log | tail -20
-- Each match = a getSaleNumber() transaction that rolled back under lock pressure

-- 3. Deadlock detection
SHOW ENGINE INNODB STATUS;
-- Look for "DEADLOCK" in the output; check if sale_sequences appears in the victim section
```

### Escalation Threshold
- >1% of `POST /api/sales` requests returning 500 with `"No se pudo generar el número de comprobante"` within a 5-minute window
- Lock wait count on `sale_sequences` row consistently > 0 at P50

### Mitigation Options (if escalated)
1. **Multiple series per location** — distribute load by registering more `serie` codes
   per warehouse (reduces concurrent writers per row).
2. **Batch pre-allocation** — pre-allocate blocks of N numbers in the counter row;
   release them from a local in-memory queue. Trades exactness for throughput.
3. **Redis atomic counter** — replace DB row lock with `INCR` on a Redis key
   (requires separate sync-back mechanism for durability).

---

## 9. Sign-Off Summary

| AC   | Description                              | Pass Condition                                                  | Status  |
|------|------------------------------------------|-----------------------------------------------------------------|---------|
| AC1  | Concurrent uniqueness (≥20 parallel)     | 0 duplicate `(serie, numero)` pairs in `sales`                  | PENDING |
| AC2  | AUTHORITATIVE mode — no movimiento row   | `movimiento` count = 0, `sales.serie/numero` non-null, seq +1   | PENDING |
| AC3  | DUAL_WRITE mode — fields agree           | `movimiento.serie == sales.serie` AND `movimiento.numero == sales.numero` | PENDING |
| AC4  | Backfill correctness                     | `unbackfilled = 0` (exact SQL from criterion)                   | PENDING |
| AC5  | Sequence seed high-water mark            | 0 series with `last_number < MAX(movimiento.numero)`            | PENDING |
| AC6  | Cash movement description                | `cash_movements.description = 'Venta {serie}-{numero}'`         | PENDING |

### Gate: `make check` / CI pipeline

The project gate is the CI pipeline defined in `.github/workflows/ci-main.yml`:

```bash
# Backend gate (runs GrumPHP + PHPUnit with coverage):
docker compose exec -T web ./vendor/bin/grumphp run --no-interaction
docker compose exec -T web php -d pcov.enabled=1 ./vendor/bin/phpunit -c phpunit.coverage.xml
php scripts/ci/check-php-coverage.php var/coverage/php/clover.xml 8.0

# Frontend gate:
yarn lint && yarn tsc --noEmit && yarn test

# E2E gate (after migrations + seed):
yarn test:e2e
```

> **All AC verifications listed above are SQL/shell post-deployment checks** to be
> executed against the target environment after the CI pipeline passes. They are
> not automated PHPUnit tests — they require a live database with real or seeded data.

### Source Artifacts Reviewed

| Artifact | File                                                                 | Reviewed |
|----------|----------------------------------------------------------------------|----------|
| A014-1   | `migrations/Version20260305100000_sale_sequences_and_serie_numero.php` | ✅       |
| A014-2   | `src/State/Sale/SaleUtils.php`                                        | ✅       |
| A014-3   | `src/State/Sale/SalesWriteGateway.php`                                | ✅       |
| A014-4   | `migrations/Version20260305110000_backfill_sale_serie_numero.php`     | ✅       |
| —        | `src/State/Sale/SaleProcessor.php` (cash movement builder, lines 317–328, 445–471) | ✅ |
| —        | `src/State/Sale/SalesWritePolicyResolver.php` (mode resolution)       | ✅       |
| —        | `src/State/Sale/SalesWriteMode.php` (constants: `legacy`, `dual_write`, `v2_authoritative`) | ✅ |
