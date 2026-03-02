# Orchestration Plan — SLICE-005

```yaml
slice: SLICE-005
title: Restore and enhance SalesV2ReconcileCommand
branch: slice/005-restore-sales-v2-reconcile-command
created_at: 2026-03-02T20:05:00.000Z
```

## Waves

### Wave 1 — Implementation (agt-backend)

**TASK-012**: Restore `SalesV2ReconcileCommand` with per-sale rounding pass + unit tests

```
ORCHESTRATOR → agt-backend
TASK: TASK-012
SLICE: SLICE-005
INPUTS: git show cb3c29e4^:src/Command/SalesV2ReconcileCommand.php (full source recovered)
        src/Command/PurchasesV2ReconcileCommand.php (reference pattern)
        src/Core/Application/Command/TenantAwareCommand.php
EXPECTED OUTPUT: src/Command/SalesV2ReconcileCommand.php
                 tests/Command/SalesV2ReconcileCommandTest.php
DEADLINE GATE: make check must pass
---
Restore the file at src/Command/SalesV2ReconcileCommand.php with:

1. Exact CLI contract from cb3c29e4^ (already recovered):
   - Command name: app:sales:v2:reconcile
   - Options: --days (default 7), --tolerance (default 0.01)
   - Cron: #[AsCronTask('30 2 * * *', arguments: '--days=7 --tolerance=0.01')]
   - Extends TenantAwareCommand, uses executeTenantCommand()
   - Day-level pass: compares movimiento (tipo_id=2) vs sales by DATE(fecha)/DATE(issued_at)
   - TableNotFoundException graceful skip (warning + SUCCESS)
   - Exits FAILURE if any day-level mismatch found

2. New per-sale rounding pass (added after day-level pass):
   - Query: JOIN movimiento m ON sales.legacy_movimiento_id = m.movimiento_id
     WHERE m.tipo_id=2 AND m.fecha >= :from AND ABS(m.total - s.grand_total) > :tolerance
     ORDER BY diff DESC LIMIT 100
   - If any rows found: print a table (movimiento_id, legacy_total, v2_total, diff)
     and set $hasMismatch = true
   - Only run this pass when day-level passes cleanly OR always (both are acceptable)

3. Unit tests in tests/Command/SalesV2ReconcileCommandTest.php using
   CommandTester + mock Connection:
   - Day mismatch detected (count diff)
   - Day mismatch detected (amount diff > tolerance)
   - Day matches within tolerance → exits SUCCESS
   - TableNotFoundException → exits SUCCESS with warning
   - Per-sale rounding mismatch detected → exits FAILURE
   - --days window: rows outside window not included

Lines must be ≤ 120 chars (PSR-12). Run make check to verify before done.
```

### Wave 2 — QA Gate (agt-qa)

**TASK-013**: Gate — `make check` green

```
ORCHESTRATOR → agt-qa
TASK: TASK-013
SLICE: SLICE-005
INPUTS: TASK-012 output (SalesV2ReconcileCommand.php + test file)
EXPECTED OUTPUT: Gate report (pass/fail)
DEADLINE GATE: make check must pass
---
Run: docker compose exec -T web vendor/bin/phpunit --filter SalesV2ReconcileCommandTest
Run: docker compose exec -T web vendor/bin/phpcs src/Command/SalesV2ReconcileCommand.php
Run: docker compose exec -T web vendor/bin/phpstan analyse src/Command/SalesV2ReconcileCommand.php
Verify all 7 acceptance criteria from SLICE-005.md are met.
Report pass/fail per criterion.
```

## Gate checkpoints

| Gate | Trigger | Pass condition |
|------|---------|----------------|
| G1 | After TASK-012 | `make check` green; unit tests all pass |
| G2 | After TASK-013 | QA sign-off on all 7 AC |
