# ADR-018 — Inventory Service Cutover: InventoryService V2

## Status

Accepted — hard cutover implemented for the legacy service

## Date

2026-04-02

---

## Context

The codebase previously had two inventory service implementations:

| Service | Location | Status |
|---|---|---|
| Legacy ledger service | removed | Deleted after quarantine; no production callers remain. |
| `InventoryService` (V2) | `src/Inventory/Core/Service/InventoryService.php` | Authoritative inventory write/read surface. |

The dual-service state caused ownership confusion, divergent float/bcmath behavior, and double-write risk. The cutover migrated Sales and stock reads to V2 behind a seam before deleting the legacy implementation.

---

## Decision

`InventoryService` V2 is the single production inventory service.

Implemented cutover state:

- `SaleInventoryOrchestrator` no longer imports a concrete legacy inventory service. It depends on `SalesInventoryPortInterface` as the Sales-facing seam.
- `SalesInventoryPortInterface` is wired to `InventoryServiceSalesInventoryPort`, which maps sale/purchase items into V2 `MovementDraft` records and delegates to `InventoryService::recordMovement()` / `getOnHand()`.
- `ProductStockService` no longer has a legacy fallback. Reads with product/warehouse IDs go through `InventoryService::getOnHand()`, then fall back only to `ProductStockRepository` when IDs are unavailable.
- The legacy service and its legacy-only tests have been deleted.
- `ContextBoundaryTest` guards against reintroducing `InventoryLedgerService` references.

The Sales-facing seam naming has been cleaned up: production code uses `SalesInventoryPortInterface` and `InventoryServiceSalesInventoryPort`, with no remaining legacy ledger terminology in the active inventory path.

---

## Consequences

### Positive

- Single production inventory path for Sales movements.
- No production dependency on the deleted legacy service.
- Lower double-write risk and clearer ownership.
- Future V2 behavior changes can be tested at `InventoryService`/adapter seams without touching Sales.

### Negative / Risks

- The `producto.stock` denormalized counter may still be read by external clients such as Android POS (`efact-app`); legacy column cleanup remains separate.
- Some fallback read models such as `stock_balances` may still exist until schema/data cleanup is planned.
- The Sales-facing port still accepts `SaleRecord` / `SaleItem`, so Inventory is not fully isolated from Sales entities yet.

### Rollback

The deleted legacy PHP service cannot be restored at runtime without reverting this change. Rollback is a code revert. Database structures were not dropped in this cutover slice.

---

## Related

- ADR-008 — V2 Schema Migration (table-level context)
- ADR-009 — Inventory Ledger Read Model (`stock_items` design)
- `src/Inventory/Core/Service/InventoryService.php` — V2 authoritative service
- `src/Inventory/Adapter/InventoryServiceSalesInventoryPort.php` — Sales-facing adapter to V2
- `src/Sales/State/SaleInventoryOrchestrator.php` — Sales orchestration using the inventory seam
