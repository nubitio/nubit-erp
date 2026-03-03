# Orchestration Plan — SLICE-004

| Field        | Value                                                         |
|--------------|---------------------------------------------------------------|
| slice        | SLICE-004                                                     |
| title        | Corregir `Unknown column 'm.turno_id'` en `SalesV2BackfillCommand` |
| status       | in_progress                                                   |
| branch       | slice/004-fix-turno-id-backfill-command                       |
| created_at   | 2026-03-02                                                    |
| orchestrator | agt-orchestrate                                               |

---

## Summary

`SalesV2BackfillCommand.php` lines 104–110 reference a non-existent column
`m.turno_id` in a SQL subquery. Every tenant execution crashes with
`SQLSTATE[42S22]: Column not found: 1054 Unknown column 'm.turno_id'`.

The fix replaces the broken join with the correct one via `m.caja_id →
cs.cash_register_id` plus a time-window filter on `opened_at`/`closed_at`.

No schema migrations required. Change is PHP-only.

---

## Artifact Registry (this slice)

| ID           | Type        | Owner        | File / Description                                             |
|--------------|-------------|--------------|----------------------------------------------------------------|
| ART-012      | code-fix    | agt-backend  | `src/Command/SalesV2BackfillCommand.php` — subquery corrected |
| ART-013      | test        | agt-backend  | `tests/…/SalesV2BackfillCommandTest.php` — smoke/functional   |
| ART-014      | gate-report | agt-qa       | `make check` output + `altofilete` smoke result                |

---

## Waves

### Wave 1 — Backend Fix + Test (TASK-010, TASK-011) — parallel

Both tasks can execute in parallel since TASK-011 only adds a new test file and
does not depend on the exact implementation of the fix beyond the public SQL
change (the test stubs DB). They share the same working branch.

#### TASK-010 — Fix subquery in SalesV2BackfillCommand

```
ORCHESTRATOR → agt-backend
TASK:     TASK-010
SLICE:    SLICE-004
INPUTS:   src/Command/SalesV2BackfillCommand.php (lines 104–110), SLICE-004.md
EXPECTED OUTPUT: ART-012 type=code-fix
DEADLINE GATE:   make check must pass
---
In `src/Command/SalesV2BackfillCommand.php`, replace the buggy subquery for
`cash_session_id` (currently lines 104–110) that references the non-existent
column `m.turno_id`.

BEFORE (buggy):
    (
        SELECT cs.id
        FROM cash_sessions cs
        WHERE cs.legacy_cash_shift_id = m.turno_id
          AND cs.branch_id = m.branch_id
        LIMIT 1
    )  AS cash_session_id,

AFTER (correct):
    (
        SELECT cs.id
        FROM cash_sessions cs
        WHERE cs.cash_register_id = m.caja_id
          AND cs.branch_id        = m.branch_id
          AND cs.opened_at        <= COALESCE(m.fecha, m.date_created, NOW())
          AND (
              cs.closed_at IS NULL
              OR cs.closed_at >= COALESCE(m.fecha, m.date_created, NOW())
          )
        ORDER BY cs.opened_at DESC
        LIMIT 1
    )  AS cash_session_id,

Also update the comment above the subquery from
  "-- resolve cash_session_id from legacy_cash_shift_id; NULL when no matching session exists"
to
  "-- resolve cash_session_id via cash_register_id + time-window; NULL when no matching session exists"

Acceptance:
- The word `turno_id` must not appear anywhere in the updated file.
- `make phpstan` and `make phpcs` must pass on the modified file.
```

#### TASK-011 — Add smoke/functional test for cash_session_id resolution

```
ORCHESTRATOR → agt-backend
TASK:     TASK-011
SLICE:    SLICE-004
INPUTS:   ART-012, SLICE-004.md §10 T2
EXPECTED OUTPUT: ART-013 type=test
DEADLINE GATE:   make check must pass (phpunit)
---
Create a PHPUnit functional/smoke test for `SalesV2BackfillCommand` that covers:

1. A `movimiento` row with a valid `caja_id` that matches a `cash_sessions` row
   via `cash_register_id` and a time-window — verifies that `sales.cash_session_id`
   is populated correctly (not NULL, not throwing 42S22).

2. A `movimiento` row with `caja_id = NULL` — verifies that
   `sales.cash_session_id` is NULL and no exception is thrown.

Use an in-memory SQLite kernel test or mock the DBAL connection with a
ResultStatement stub. Keep the test self-contained (no live DB required).

File location: `tests/Command/SalesV2BackfillCommandTest.php`
(or the existing test file if one already exists — add new test methods there).

Acceptance:
- `make phpunit` passes with the new tests included.
- No existing test methods modified/deleted.
```

---

### Gate 1 — after Wave 1

Run `make check` (phpstan + phpcs + phpunit).

- **PASS** → proceed to Wave 2
- **FAIL** → return to responsible agent (max 2 retries before human escalation)

---

### Wave 2 — QA Gate (TASK-012)

#### TASK-012 — Gate: make check green + altofilete smoke

```
ORCHESTRATOR → agt-qa
TASK:     TASK-012
SLICE:    SLICE-004
INPUTS:   ART-012, ART-013, Gate-1 report
EXPECTED OUTPUT: ART-014 type=gate-report
DEADLINE GATE:   all acceptance criteria §11 satisfied
---
Verify SLICE-004 acceptance criteria:

1. Run `make check` (phpstan + phpcs + phpunit). All must pass green.
2. Confirm `turno_id` no longer appears in `SalesV2BackfillCommand.php`.
3. Confirm the subquery uses `cs.cash_register_id = m.caja_id` as primary join.
4. Confirm time-window filter is present:
   `cs.opened_at <= COALESCE(m.fecha, m.date_created, NOW())` and
   `(cs.closed_at IS NULL OR cs.closed_at >= COALESCE(m.fecha, m.date_created, NOW()))`.
5. If the `altofilete` tenant staging environment is accessible, execute:
   `bin/console app:sales:v2:backfill --tenant=altofilete --dry-run`
   and confirm no `42S22` error appears in the output.
6. Confirm no regressions in pre-existing command tests.

Report: produce a checklist with PASS/FAIL per acceptance criterion.
If any criterion fails, emit a BLOCKER with the failing criterion ID.
```

---

## Gate Checkpoints

| Gate    | Trigger                       | Criteria                                    |
|---------|-------------------------------|---------------------------------------------|
| Gate-1  | After Wave 1 completes        | `make check` green (phpstan + phpcs + phpunit) |
| Gate-2  | After Wave 2 / QA sign-off    | All 6 acceptance criteria in §11 PASS       |

---

## Escalation Policy

If the same gate fails twice consecutively for the same task, create a GitHub
issue labelled `blocked` and halt until human resolves.

---

## Events Log (populated during execution)

| Timestamp (UTC)      | Event           | Details                          |
|----------------------|-----------------|----------------------------------|
| (populated at start) | SliceStarted    | SLICE-004                        |
| (after T1 hand-off)  | TaskHandedOff   | TASK-010 → agt-backend           |
| (after T2 hand-off)  | TaskHandedOff   | TASK-011 → agt-backend           |
| (after Gate-1)       | GatePassed      | Gate-1 make check                |
| (after T3 hand-off)  | TaskHandedOff   | TASK-012 → agt-qa                |
| (after Gate-2)       | GatePassed      | Gate-2 all AC                    |
| (final)              | SliceCompleted  | SLICE-004                        |
