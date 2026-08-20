# ADR-010: Inventory Read-Model Cutover — `stock_items` Canonical, `ProductStock` Deprecated

Date recorded: 2026-08-19 (retroactive — documents an in-progress migration; see Context and Amendment)
Status: Accepted — **Partially implemented (cutover in progress, not complete)**

## Context

Reconstructed from `docs/PRD.md`'s "ADR-009 / ADR-018: inventory ledger/read model y cutover de servicio de inventario" citation (two numbers cited together, treated here as one decision — both collide with unrelated future-numbered space, not with any existing `docs/adr/` file).

ADR-003 (real, existing) established `stock_movements` as an append-only ledger — current stock levels must be derived from it, never stored as directly-mutated state. That leaves open *how* the derived "current stock" read model is produced and which table is the one application code should query.

## Decision

### Ledger → read-model projection

`stock_movements` (append-only, `App\Inventory\ApiResource\StockMovement` — confirmed to have no setters, immutable after construction) is the source of truth. `StockItem` (`src/Inventory/Entity/StockItem.php`) is the canonical read model: current on-hand quantity per product/warehouse.

Projection is **batch, not event-driven**: `StockRebuilderJob::rebuild()` (`src/Inventory/Core/Service/StockRebuilderJob.php`) runs an aggregate SQL statement — `INSERT INTO stock_items (...) SELECT ... SUM(qty_delta_base) FROM stock_movements ... ON DUPLICATE KEY UPDATE` — rather than a Doctrine event listener updating `stock_items` synchronously on every movement insert. This makes `stock_items` rebuildable from the ledger on demand (consistent with ADR-003's stated benefit), at the cost of not being guaranteed real-time-consistent between rebuilds.

### Legacy mirror kept alongside, explicitly deprecated

`ProductStock` (`src/Inventory/Entity/ProductStock.php`) is the pre-existing read model this one replaces. It carries `deprecationReason: 'Legacy stock mirror. Use stock_items via InventoryService/ProductStockService as the source of truth.'` — but it is not dead code. `ProductStockService::updateStockForProductInWarehouse()` still actively writes to it (`persist()`+`flush()`), and it has two confirmed live consumers: `src/Catalog/State/ProductStockController.php` and `src/AI/Tool/ProductTools.php` (an MCP tool the AI assistant calls). Commit `ab52766c` ("chore(stock): strengthen canonical stock_items path; deprecate legacy mirror") is the point this migration started.

## Consequences

**Positive**
- `stock_items` is rebuildable from the immutable ledger at any time — a corrupted or drifted read model is a recoverable operational incident, not data loss.
- Batch projection is simpler to reason about and cheaper than a synchronous per-movement listener for a table that's read far more often than written.

**Negative**
- **The cutover is incomplete.** Two read models (`stock_items` canonical, `ProductStock` legacy) are written and read in production simultaneously, with no tracked date or condition for retiring the second one. Anyone reading current stock through `ProductStockController` or the AI's `ProductTools` sees the legacy mirror, not the canonical projection — those two paths could disagree if the batch rebuild and the legacy dual-write ever drift.
- Batch (not event-driven) projection means `stock_items` can be stale between rebuild runs — any code assuming it reflects the very latest movement in real time is making an assumption the architecture doesn't guarantee.
- No migration end-date or dual-write removal plan exists anywhere in the repo for `ProductStock`. This ADR does not set one — that's a product/ownership decision, not an architectural one — but flags that "deprecated" here has meant "still fully live" for long enough that a reader should not assume it's inert.

## Alternatives Considered

**Event-driven synchronous projection** (a Doctrine/Messenger listener updating `stock_items` on every `stock_movements` insert) — not the path taken; batch rebuild was chosen instead, likely for simplicity and to avoid coupling the write path's latency to a projection update. Not documented anywhere as an explicit rejection — inferred from what was actually built.

**Immediate full cutover (delete `ProductStock` at the same time `stock_items` was introduced)** — not done. The dual-write period suggests either a deliberate safety-net rollout (verify `stock_items` before removing the fallback) or a stalled migration that ran out of follow-through. The repo has no record of which.
