# Orchestration Plan — SLICE-006

```yaml
slice: SLICE-006
title: Fix rounding loss in ESC/POS receipt line totals
branch: slice/006-fix-receipt-line-rounding
created_at: 2026-03-02T20:10:00.000Z
```

## Waves

### Wave 1 — Implementation (agt-backend)

**TASK-014**: Fix `InvoiceCalculator` line-total computation + unit tests

```
ORCHESTRATOR → agt-backend
TASK: TASK-014
SLICE: SLICE-006
INPUTS: src/EInvoicing/Service/InvoiceCalculator.php
        src/Core/Util/Money.php
EXPECTED OUTPUT: src/EInvoicing/Service/InvoiceCalculator.php (modified)
                 tests/EInvoicing/Service/InvoiceCalculatorRoundingTest.php (new)
DEADLINE GATE: make check must pass
---
Root cause: lineTotal is computed as base + tax after intermediate HALF_UP rounding,
dropping 1 cent per line.

Fix: compute lineTotal = round(qty × unitPrice, 2) directly, then derive
taxAmount = lineTotal - baseAmount - chargesAmount.

Unit tests must cover all 7 acceptance criteria.
```

### Wave 2 — QA Gate (agt-qa)

**TASK-015**: Gate — `make check` green + regression check

```
ORCHESTRATOR → agt-qa
TASK: TASK-015
SLICE: SLICE-006
INPUTS: TASK-014 output
EXPECTED OUTPUT: Gate report (pass/fail)
DEADLINE GATE: make check must pass
---
Run phpunit --filter InvoiceCalculator, full suite, phpcs, phpstan.
Verify all 7 AC from SLICE-006.md.
```

## Gate checkpoints

| Gate | Trigger | Pass condition |
|------|---------|----------------|
| G1 | After TASK-014 | `make check` green; unit tests all pass |
| G2 | After TASK-015 | QA sign-off on all 7 AC |
