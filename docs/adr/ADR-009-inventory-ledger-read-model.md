# ADR-009 — Inventory: Append-Only Ledger + Materialized Read Model

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact requires real-time inventory tracking across multiple warehouses per tenant. The requirements are:

1. **Auditability** — Every stock change (sale, purchase, adjustment, transfer, recipe consumption) must be traceable to its source document.
2. **Accuracy** — Quantities must use exact decimal arithmetic (no floating-point drift). Stock can be fractional (e.g., 1.5 kg of rice).
3. **Idempotency** — Replaying a sale sync (offline POS retrying) must not double-deduct stock.
4. **Multi-unit** — Products can be sold in different units (e.g., sold by the box, stocked in units). The inventory must track quantities in a base unit.
5. **Lot tracking** — Some products require FIFO or FEFO lot deduction (batch/expiry tracking for pharmacies and food businesses).
6. **Composite products (BOM)** — A "product" may be a recipe that consumes raw ingredients on sale (e.g., a restaurant combo). Stock movements must be generated for the components, not the parent.
7. **Read performance** — `GET /api/products` showing stock levels must be fast; a full ledger replay on every read would be too slow.

**Alternatives for the read model:**

| Approach | Notes |
|----------|-------|
| **Ledger-only (replay on read)** | Always accurate; slow for large tables; O(n) per product per query |
| **Balance-only (no ledger)** | Fast reads; zero auditability; cannot reconstruct history |
| **Ledger + materialized balance (chosen)** | Ledger is the source of truth; balance table is the read model updated atomically on each write |

---

## Decision

Implement a **two-table inventory system**:

1. **`stock_movements`** — Append-only ledger. Every stock change is a row. Never deleted. Never updated.
2. **`stock_items`** — Materialized read model. One row per (warehouse, product). `qty_on_hand` and `qty_reserved` updated atomically in the same transaction as each movement insert.

### `stock_movements` Schema

```sql
CREATE TABLE stock_movements (
    id              VARCHAR(36) PRIMARY KEY,           -- UUID v7
    warehouse_id    VARCHAR(36) NOT NULL,
    product_id      VARCHAR(36) NOT NULL,
    occurred_at     DATETIME NOT NULL,
    movement_type   ENUM('PURCHASE','SALE','ADJUSTMENT','TRANSFER_IN','TRANSFER_OUT','RECIPE_CONSUMPTION'),
    quantity_delta  DECIMAL(18,6) NOT NULL,            -- signed; negative = deduction
    qty_delta_base  DECIMAL(18,6) NOT NULL,            -- in base unit
    qty_original    DECIMAL(18,6) NOT NULL,            -- in the unit the movement was recorded in
    unit_id         INT NULL,                          -- unit of the original qty
    source_type     VARCHAR(50),                       -- 'sale', 'purchase', 'adjustment', etc.
    source_id       VARCHAR(36),                       -- ID of the source document
    note            TEXT NULL,
    idempotency_key VARCHAR(64) UNIQUE,                -- prevents double-deduction on retry
    sync_status     VARCHAR(20) DEFAULT 'SYNCED',
    version_no      INT DEFAULT 1,
    last_modified_at DATETIME,
    created_at      DATETIME
);
```

### `stock_items` Schema (Read Model)

```sql
CREATE TABLE stock_items (
    warehouse_id     VARCHAR(36) NOT NULL,
    product_id       VARCHAR(36) NOT NULL,
    qty_on_hand      DECIMAL(18,6) NOT NULL DEFAULT 0,
    qty_reserved     DECIMAL(18,6) NOT NULL DEFAULT 0,
    updated_at       DATETIME,
    last_movement_id VARCHAR(36),                      -- FK to stock_movements (for debugging)
    version          INT DEFAULT 1,
    PRIMARY KEY (warehouse_id, product_id)
);
```

### `InventoryService` Design

The `InventoryService` is the **single public surface** for all inventory operations:

```php
// Write path
$inventoryService->recordMovement($draft, $sale, InventoryBehavior::TRACKED);

// Read path
$onHand = $inventoryService->getOnHand($warehouseId, $productId);

// Pre-flight validation
$result = $inventoryService->validateForSale($product, $warehouseId, $qty, $unitId);

// Reservation (pending orders)
$inventoryService->reserve($warehouseId, $productId, $qtyBase);
$inventoryService->release($warehouseId, $productId, $qtyBase);
```

### Idempotency Key

```php
private function buildIdempotencyKey(MovementDraft $draft): string
{
    return hash('sha256', implode('|', [
        $draft->sourceType,
        $draft->sourceId,
        $draft->movementType->value,
        $draft->warehouseId,
        $draft->productId,
    ]));
}
```

A duplicate movement (identical source + type + warehouse + product) hits a `UNIQUE` constraint on `idempotency_key`, is caught as `UniqueConstraintViolationException`, and is silently skipped — the transaction is rolled back and no error is returned.

### Enricher Chain (BOM Explosion)

For COMPOSITE products, `BomExplosionService` implements `StockMovementEnricherInterface`. The enricher chain replaces the parent product's `MovementDraft` with a list of component drafts:

```php
// COMPOSITE product: instead of recording 1 unit of "Burger Combo"
// record movements for: 1 bun, 150g beef, 30g lettuce, 1 sauce
$movements = $enricher->enrichSaleMovements($sale, [$parentDraft]);
// → [$bunDraft, $beefDraft, $lettuceDraft, $sauceDraft]
```

### Lot Deduction (FIFO/FEFO)

`LotDeductionService` selects lots in FIFO (oldest received first) or FEFO (nearest expiry first) order and generates one `MovementDraft` per lot consumed. This satisfies pharmaceutical expiry date requirements.

### Decimal Arithmetic

All qty arithmetic uses PHP's `bcmath` extension (via `bcadd`, `bcsub`, `bcmul`) with 6 decimal places. `float` is never used for inventory quantities.

### Read Chain (Fallback)

`InventoryService::getOnHand()` falls back gracefully:
1. Try `stock_items` (V2 read model) first.
2. Fall back to `stock_balances` (intermediate V1.5 read model, kept during migration).
3. Return `'0'` if neither exists.

---

## Consequences

### Positive

- **Complete audit trail** — Every stock change is recorded with source document reference. Disputes ("why is my stock of product X at 5?") can be answered by querying `stock_movements WHERE product_id = X`.
- **Fast reads** — `stock_items` is a single-row lookup by (warehouse, product) for the hot path.
- **Idempotent sync** — Offline POS sale replays cannot double-deduct stock.
- **Exact arithmetic** — `bcmath` prevents the floating-point errors that plagued the original system.
- **BOM support** — Composite products (restaurant recipes) are handled transparently by the enricher chain.
- **Lot tracking** — FIFO/FEFO deduction satisfies pharmacy and food business requirements.

### Negative

- **Read model drift risk** — If `stock_items` and `stock_movements` get out of sync (e.g. a bug in `upsertStockItem`), `ReconciliationService` must be run to rebuild the read model from the ledger.
- **Write amplification** — Each sale deduction is 2 writes (insert to `stock_movements` + upsert to `stock_items`) instead of 1 (update balance directly). Acceptable for current volumes.
- **Legacy fallback chain** — The `stock_balances` fallback in `getOnHand()` adds code complexity. It should be removed once migration is complete.

---

## Related ADRs

- ADR-005 — Offline-first (idempotency key pattern originates here, also applied to stock_movements)
- ADR-008 — V2 migration (stock_movements and stock_items are V2-only; legacy product_stock table still exists for older tenants)
