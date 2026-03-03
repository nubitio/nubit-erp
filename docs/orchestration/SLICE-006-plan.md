# Orchestration Plan — SLICE-006

| Field        | Value                                                                                          |
|--------------|------------------------------------------------------------------------------------------------|
| slice        | SLICE-006                                                                                      |
| title        | Corregir `NotNullConstraintViolationException` en `SalesV2BackfillCommand` (`caja_id NULL` y `cash_session_id NULL`) |
| status       | in_progress                                                                                    |
| branch       | slice/006-fix-notnull-backfill-command                                                         |
| created_at   | 2026-03-03                                                                                     |
| orchestrator | agt-orchestrate                                                                                |

---

## Summary

`SalesV2BackfillCommand.php` has two failure modes that cause `SQLSTATE[23000]:
Integrity constraint violation` during backfill execution:

**Mode A:** `m.caja_id IS NULL` in `movimiento` → the INSERT projects
`m.caja_id AS cash_register_id` → violates `cash_register_id INT NOT NULL` in
`sales` → whole transaction rolls back, zero rows migrated (observed in tenant
`arcacevic`).

**Mode B:** The `cash_session_id` subquery returns NULL (no matching
`cash_sessions` row) → violates `cash_session_id INT NOT NULL` in `sales`. This
is inconsistent with SLICE-004 §5 Escenario B which documents that
`cash_session_id = NULL` is the *expected* correct behaviour when no matching
session exists.

**Two coordinated fixes:**
- **Fix A (PHP):** Add `AND m.caja_id IS NOT NULL` to the INSERT `WHERE` clause.
  Pre-count skipped rows and emit a `$io->warning(...)` if any were skipped.
- **Fix B (schema):** New migration `Version20260303000000_sales_cash_session_nullable.php`
  makes `sales.cash_session_id` nullable (`INT DEFAULT NULL`), managing the
  `fk_sales_cash_session_v2` FK with the idempotent helper pattern from
  `Version20260228103000`.

---

## Artifact Registry (this slice)

| ID      | Type         | Owner       | File / Description                                                          |
|---------|--------------|-------------|-----------------------------------------------------------------------------|
| ART-021 | code-fix     | agt-backend | `src/Command/SalesV2BackfillCommand.php` — Fix A: `AND m.caja_id IS NOT NULL` + skipped-rows counter + warning |
| ART-022 | migration    | agt-db      | `migrations/Version20260303000000_sales_cash_session_nullable.php` — Fix B: make `cash_session_id` nullable |
| ART-023 | test         | agt-backend | `tests/Command/SalesV2BackfillCommandTest.php` — new Scenario A (NULL caja_id skipped + warning) and Scenario B (NULL caja_id NOT NULL but no session → inserted with NULL cash_session_id) |
| ART-024 | gate-report  | agt-qa      | GrumPHP output + AC checklist for all 9 acceptance criteria                 |

---

## Waves

### Wave 1 — Parallel (TASK-019, TASK-020)

TASK-019 (PHP fix) and TASK-020 (migration) are fully independent: one touches
only `src/Command/`, the other only `migrations/`. They share the same working
branch and can execute simultaneously.

#### TASK-019 — Fix A: `AND m.caja_id IS NOT NULL` + skipped-rows warning in SalesV2BackfillCommand

```
ORCHESTRATOR → agt-backend
TASK:     TASK-019
SLICE:    SLICE-006
INPUTS:   src/Command/SalesV2BackfillCommand.php (full file, 252 lines), SLICE-006.md
EXPECTED OUTPUT: ART-021 type=code-fix
DEADLINE GATE:   make check must pass (phpstan level 5, phpcs, phpmd)
---
Read `src/Command/SalesV2BackfillCommand.php` in full (252 lines). Make exactly
three coordinated changes:

CHANGE 1 — Pre-count skipped rows (insert BEFORE line 59, i.e. before the
`$this->connection->beginTransaction()` call):

    $skipped = (int) $this->connection->fetchOne(
        <<<'SQL'
        SELECT COUNT(*)
        FROM movimiento m
        WHERE m.tipo_id = 2
          AND m.almacen_id IS NOT NULL
          AND m.branch_id IS NOT NULL
          AND m.caja_id IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM sales s
              WHERE BINARY s.legacy_movimiento_id = BINARY m.movimiento_id
          )
        SQL
    );

CHANGE 2 — Filter NULL caja_id rows from INSERT (in the WHERE clause of the
INSERT INTO sales … SELECT … FROM movimiento m, currently lines 163-170):

    Add `  AND m.caja_id IS NOT NULL` as a new line AFTER `  AND m.branch_id IS NOT NULL`
    and BEFORE `  AND NOT EXISTS (`.

    The resulting WHERE block must read exactly:
        WHERE m.tipo_id = 2
          AND m.almacen_id IS NOT NULL
          AND m.branch_id IS NOT NULL
          AND m.caja_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM sales s
              WHERE BINARY s.legacy_movimiento_id = BINARY m.movimiento_id
          )

CHANGE 3 — Emit warning after $io->success (after line 248, inside
executeTenantCommand, before `return Command::SUCCESS`):

    if ($skipped > 0) {
        $io->warning(sprintf(
            'Skipped %d movimiento row(s) with NULL caja_id (no cash_register assigned).',
            $skipped,
        ));
    }

Verification after editing:
- Run: `docker exec efact-web vendor/bin/phpstan analyse src/Command/SalesV2BackfillCommand.php --level=5`
  → must exit 0 with no errors
- Run: `docker exec efact-web vendor/bin/phpmd src/Command/SalesV2BackfillCommand.php ansi phpmd.xml.dist`
  → must exit 0 with no new violations

Acceptance criteria (from SLICE-006 §11):
- AC1: The word `caja_id IS NOT NULL` appears in the INSERT WHERE clause.
- AC2/AC3: `$skipped` variable is declared before `beginTransaction()`; the
  warning is printed after `$io->success()` when `$skipped > 0`.
- No other lines of the file are modified.
```

#### TASK-020 — Fix B: Create migration Version20260303000000_sales_cash_session_nullable.php

```
ORCHESTRATOR → agt-db
TASK:     TASK-020
SLICE:    SLICE-006
INPUTS:   migrations/Version20260228103000_cash_session_fk_v2_only.php (185 lines, reference pattern), SLICE-006.md §8
EXPECTED OUTPUT: ART-022 type=migration
DEADLINE GATE:   make check must pass (phpstan, phpcs, phpmd)
---
Create the file `migrations/Version20260303000000_sales_cash_session_nullable.php`.

Copy the four private helper methods (`tableExists`, `foreignKeyExists`,
`dropForeignKeyIfExists`, `addForeignKeyIfMissing`) verbatim from
`Version20260228103000_cash_session_fk_v2_only.php`.

Class name: `Version20260303000000_sales_cash_session_nullable`
Namespace: `DoctrineMigrations`

getDescription():
    'SLICE-006: make sales.cash_session_id nullable — aligns schema with documented NULL behaviour from SLICE-004. WARNING: down() is lossy if NULL rows exist.'

up(Schema $schema):
    Step 1: if (!$this->tableExists('sales')) { return; }
    Step 2: Fix orphaned FK references BEFORE dropping the FK:
        $this->addSql(<<<'SQL'
            UPDATE sales
            SET cash_session_id = NULL
            WHERE cash_session_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM cash_sessions cs
                  WHERE cs.id = sales.cash_session_id
              )
        SQL);
    Step 3: $this->dropForeignKeyIfExists('sales', 'fk_sales_cash_session_v2');
    Step 4: $this->addSql('ALTER TABLE sales MODIFY cash_session_id INT DEFAULT NULL');
    Step 5: $this->addForeignKeyIfMissing('sales', 'fk_sales_cash_session_v2', '(cash_session_id)', 'cash_sessions (id)');

down(Schema $schema):
    Step 1: if (!$this->tableExists('sales')) { return; }
    Step 2: (Lossy — documented) Set NULLs to placeholder 0 so NOT NULL succeeds:
        $this->addSql('UPDATE sales SET cash_session_id = 0 WHERE cash_session_id IS NULL');
    Step 3: $this->dropForeignKeyIfExists('sales', 'fk_sales_cash_session_v2');
    Step 4: $this->addSql('ALTER TABLE sales MODIFY cash_session_id INT NOT NULL');
    Step 5: $this->addForeignKeyIfMissing('sales', 'fk_sales_cash_session_v2', '(cash_session_id)', 'cash_sessions (id)');

Key idempotency guarantees:
- `tableExists('sales')` guard prevents failure on tenants without the `sales` table.
- `dropForeignKeyIfExists` / `addForeignKeyIfMissing` helpers are copied verbatim
  from Version20260228103000 — they check `information_schema` before acting.
- The UPDATE in step 2 of up() handles the Q2 risk (orphaned FK references that
  would cause `fk_sales_cash_session_v2` re-addition to fail).

Acceptance criteria (from SLICE-006 §11):
- AC5: After applying up(), `sales.cash_session_id` is `INT DEFAULT NULL`.
- AC6: After applying up(), `fk_sales_cash_session_v2` exists on `sales`.
- AC4 (indirect): With this migration applied, rows with `cash_session_id = NULL`
  can be inserted without SQLSTATE[23000].
```

---

### Gate 1 — After Wave 1

Run `docker exec efact-web vendor/bin/grumphp run` inside the container.

- **PASS** → proceed to Wave 2
- **FAIL (new errors only)** → return to responsible agent (max 2 retries before human escalation)
- **FAIL (pre-existing baseline: bcadd/bcsub extension missing, SaleProcessorScenarioCTest mock count)** → commit with `--no-verify` and document in commit message; proceed to Wave 2

---

### Wave 2 — Sequential (TASK-021, TASK-022)

Wave 2 tasks run after Gate 1. TASK-021 must complete before TASK-022.

#### TASK-021 — Update tests: Scenario A (NULL caja_id skipped + warning) and Scenario B (no session → NULL cash_session_id inserted)

```
ORCHESTRATOR → agt-backend
TASK:     TASK-021
SLICE:    SLICE-006
INPUTS:   ART-021, ART-022, tests/Command/SalesV2BackfillCommandTest.php (237 lines)
EXPECTED OUTPUT: ART-023 type=test
DEADLINE GATE:   phpunit passes (new tests green, no existing tests broken)
---
Read `tests/Command/SalesV2BackfillCommandTest.php` (237 lines). Add two new
test methods. Do NOT modify or delete existing tests.

IMPORTANT: The existing `mockConnectionForSuccessfulInsert` mock stubs
`fetchOne` to return `1` for ALL calls. After TASK-019 is applied, the command
now makes an ADDITIONAL `fetchOne` call (the `SELECT COUNT(*)` for the skipped
counter) BEFORE `beginTransaction()`. The mock must handle this correctly.

The `fetchOne` call sequence in the updated command (for the non-reset path) is:
  Call 1: SELECT 1 FROM sales LIMIT 1          → table-existence check
  Call 2: SELECT 1 FROM sale_items LIMIT 1     → table-existence check
  Call 3: SELECT 1 FROM movimiento LIMIT 1     → table-existence check
  Call 4: SELECT 1 FROM movimiento_detalle LIMIT 1 → table-existence check
  Call 5: SELECT COUNT(*) … caja_id IS NULL … → returns $skipped count

Since existing tests use `->method('fetchOne')->willReturn(1)` (returns `1` for
ALL fetchOne calls), call 5 returns `1` — meaning `$skipped = 1` — which will
trigger the warning output. The existing `testCommandSucceedsAndDoesNotThrow42S22`
test only asserts `Command::SUCCESS` and string presence of 'backfill completed',
'headers=5', 'items=12' — it does NOT assert the warning is absent, so it will
still pass.

NEW TEST A — `testNullCajaIdRowIsSkippedWithWarning`:
  Purpose: Verify that when the skipped-counter returns N > 0, the warning
  message is present in the command output.

  Mock setup:
  - `fetchOne`: return `1` for calls 1–4 (table-existence), return `3` for call 5
    (3 rows with NULL caja_id to be skipped).
    Use a `$callCount` counter in the `willReturnCallback` closure.
  - `beginTransaction`, `commit`: stub normally.
  - `executeStatement`: first call (headers INSERT) returns 7, second call
    (items INSERT) returns 14.

  Assertions:
  - `assertSame(Command::SUCCESS, $exitCode)`
  - `assertStringContainsString('Skipped 3 movimiento row(s) with NULL caja_id', $tester->getDisplay())`
  - `assertStringContainsString('headers=7', $tester->getDisplay())`

NEW TEST B — `testNullCajaIdNotNullButNoSessionInsertsWithNullCashSessionId`:
  Purpose: Verify that when caja_id IS NOT NULL but no matching cash_session
  exists (subquery returns NULL → cash_session_id = NULL), the command succeeds
  and the SQL does NOT contain a filter that would exclude such rows.

  Mock setup:
  - `fetchOne`: return `1` for all calls (table checks pass, skipped count = 1,
    which triggers the warning — that is acceptable).
  - `beginTransaction`, `commit`: stub normally.
  - `executeStatement`: capture the SQL for the first call (headers INSERT);
    return 1 for headers, 2 for items.

  Assertions:
  - `assertSame(Command::SUCCESS, $exitCode)`
  - Assert the captured INSERT SQL does NOT contain `cash_session_id IS NOT NULL`
    (i.e. we are NOT filtering out rows with no session — they are inserted with NULL).
  - Assert the captured INSERT SQL DOES contain `caja_id IS NOT NULL`
    (Fix A filter is present).

Verify no phpcs violations after editing the file.
```

#### TASK-022 — Final gate: grumphp green + AC checklist

```
ORCHESTRATOR → agt-qa
TASK:     TASK-022
SLICE:    SLICE-006
INPUTS:   ART-021, ART-022, ART-023, Gate-1 report
EXPECTED OUTPUT: ART-024 type=gate-report
DEADLINE GATE:   all 9 acceptance criteria in SLICE-006 §11 PASS
---
Run the final quality gate and verify all SLICE-006 acceptance criteria.

Step 1 — Run GrumPHP:
  docker exec efact-web vendor/bin/grumphp run

  Interpret results:
  - Any NEW phpstan, phpcs, phpmd, or phpunit failures (beyond the known
    baseline) → BLOCKER, return to responsible agent.
  - Known baseline failures (bcadd/bcsub extension missing, SaleProcessorScenarioCTest
    mock count) → acceptable, note in gate report.

Step 2 — Verify acceptance criteria by inspection:

AC1: Confirm `src/Command/SalesV2BackfillCommand.php` WHERE clause contains
     `AND m.caja_id IS NOT NULL`.
AC2: Confirm rows with `caja_id IS NULL` are NOT inserted (they are pre-counted
     and skipped by the WHERE filter).
AC3: Confirm `$io->warning(sprintf('Skipped %d movimiento row(s) with NULL caja_id (no cash_register assigned).', $skipped))` is present after `$io->success(...)`.
AC4: Confirm `migrations/Version20260303000000_sales_cash_session_nullable.php`
     exists and `up()` modifies column to `INT DEFAULT NULL`.
AC5: Confirm `MODIFY cash_session_id INT DEFAULT NULL` is present in migration up().
AC6: Confirm `addForeignKeyIfMissing('sales', 'fk_sales_cash_session_v2', ...)` is called after the MODIFY in up().
AC7: Confirm INSERT SQL in the command still projects `m.caja_id AS cash_register_id`
     (Scenario C unaffected).
AC8: Confirm GrumPHP reports no new warnings on phpstan, phpcs, phpmd.
AC9: Confirm no existing test methods in `SalesV2BackfillCommandTest.php` are
     missing or have been modified; new tests are added only.

Produce a checklist: AC1–AC9 each as PASS or FAIL with one-line evidence.
If any criterion is FAIL, emit BLOCKER with criterion ID.
```

---

## Gate Checkpoints

| Gate   | Trigger                           | Criteria                                                          |
|--------|-----------------------------------|-------------------------------------------------------------------|
| Gate-1 | After Wave 1 (TASK-019, TASK-020) | `grumphp run` green (or only pre-existing baseline failures)      |
| Gate-2 | After Wave 2 / QA sign-off        | All 9 acceptance criteria in SLICE-006 §11 PASS; PR created       |

---

## Escalation Policy

If the same gate fails twice consecutively for the same task (excluding the known
pre-existing baseline failures), create a GitHub issue labelled `blocked` and
halt until a human resolves.

---

## Git Protocol

| Moment                                     | Git action                                                                                |
|--------------------------------------------|-------------------------------------------------------------------------------------------|
| Start of slice                             | `git checkout main && git checkout -b slice/006-fix-notnull-backfill-command` ✅ done      |
| After plan + state decomposition committed | `git add docs/orchestration/SLICE-006-plan.md ai/state.json && git commit -m "chore(orchestrate): decompose SLICE-006 — 4 tasks"` |
| Gate 1 passes                              | `git add ai/state.json && git commit -m "chore(state): gate 1 passed — SLICE-006"`       |
| After first grumphp green                  | `gh pr create --title "fix: SLICE-006 — corregir NotNullConstraintViolation en SalesV2BackfillCommand" --base main --head slice/006-fix-notnull-backfill-command --body "..."` |

**NEVER `git push`** — the human decides when to push to remote.

---

## Events Log (populated during execution)

| Timestamp (UTC)      | Event                  | Details                               |
|----------------------|------------------------|---------------------------------------|
| (populated at start) | SliceStarted           | SLICE-006                             |
| (after T1 hand-off)  | TaskHandedOff          | TASK-019 → agt-backend (Fix A)        |
| (after T2 hand-off)  | TaskHandedOff          | TASK-020 → agt-db (Fix B migration)   |
| (after Gate-1)       | GatePassed             | Gate-1 grumphp                        |
| (after T3 hand-off)  | TaskHandedOff          | TASK-021 → agt-backend (tests)        |
| (after T4 hand-off)  | TaskHandedOff          | TASK-022 → agt-qa (final gate)        |
| (after Gate-2)       | GatePassed             | Gate-2 all 9 AC                       |
| (final)              | SliceCompleted         | SLICE-006                             |
