# ADR-018 — Inventory Service Cutover: InventoryLedgerService → InventoryService V2

## Status

Accepted

## Date

2026-04-02

---

## Context

Two inventory service implementations currently coexist in the codebase:

| Service | Location | Lines | Tests |
|---|---|---|---|
| `InventoryLedgerService` (legacy) | `src/Service/InventoryLedgerService.php` | 673 | None |
| `InventoryService` (V2) | `src/Inventory/Core/Service/InventoryService.php` | ~354 | Partial (integration) |

`SaleInventoryOrchestrator` bridges them: V2 `InventoryService` is called first; if it returns without error, `InventoryLedgerService` is bypassed for movement writing (but may still be consulted for reads).

### Problems with the dual-service state

1. **Confusion about ownership** — developers adding new features are unsure which service to call.
2. **Divergent behaviour** — `InventoryLedgerService` uses `float` arithmetic and legacy table names; V2 uses `bcmath` and the new `stock_movements` schema.
3. **Double-write risk** — If the orchestrator flag is misconfigured, both services can write to overlapping tables causing double-counted movements.
4. **`InventoryLedgerService` has zero tests** — it cannot be safely changed.

---

## Decision

### Phase 1 — Freeze (current sprint)

- No new feature code may call `InventoryLedgerService` directly.
- `InventoryLedgerService` is annotated `@deprecated` in its class docblock.
- All lot deduction paths in `InventoryLedgerService` now delegate through `LotDeductionService` (already done) which uses bcmath.

### Phase 2 — V2 coverage (next sprint)

- Write integration tests for `InventoryService` V2 that cover: `recordMovement`, `getOnHand`, `reserve`, `release`, and the idempotency key path.
- Achieve ≥ 80% line coverage on `src/Inventory/Core/Service/InventoryService.php`.

### Phase 3 — Shadow mode (after Phase 2 passes CI)

- Run `InventoryService` V2 in "shadow mode": both services execute, but only V2 result is returned. V2 and legacy results are compared in a log/metric; discrepancies fire a `StockReconciliationDriftDetected` event.
- Shadow mode is controlled by a feature flag: `INVENTORY_V2_SHADOW=1` (env var).

### Phase 4 — Hard cutover

- Remove `InventoryLedgerService` and `SaleInventoryOrchestrator` bridge.
- Remove the `INVENTORY_V2_SHADOW` env var.
- Delete `InventoryLedgerService.php`.
- Migration: verify `stock_movements` is the sole authoritative table; drop any legacy movement columns that have been superseded.

### Phase 5 — Legacy column cleanup

- Drop `producto.stock` denormalized counter column once all stock reads go through `stock_items`.
- Archive the `stock_balances` fallback table.

---

## Consequences

### Positive

- Single, well-tested, bcmath-safe inventory path.
- No more float rounding errors in FIFO/FEFO lot deduction.
- Cleaner codebase: removes ~673 lines of untested legacy code.

### Negative / Risks

- Phase 3 shadow mode adds temporary overhead (two writes per movement).
- The `producto.stock` column is read by the Android POS (`efact-app`) — Android must migrate to the REST `/inventory/stock-items` endpoint before Phase 5 proceeds.

### Rollback

- Phases 1-2 are purely additive; rollback is trivial.
- Phase 3 can be disabled by unsetting `INVENTORY_V2_SHADOW`.
- Phase 4 is irreversible; it requires a successful Phase 3 with zero discrepancies for ≥ 2 weeks in production.

---

## Related

- ADR-008 — V2 Schema Migration (table-level context)
- ADR-009 — Inventory Ledger Read Model (stock_items design)
- `src/Service/InventoryLedgerService.php` — legacy service (to be deleted in Phase 4)
- `src/Inventory/Core/Service/InventoryService.php` — V2 target
- `src/Inventory/Infrastructure/SaleInventoryOrchestrator.php` — bridge (to be deleted in Phase 4)
