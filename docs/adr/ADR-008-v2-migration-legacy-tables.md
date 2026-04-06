# ADR-008 — V2 Schema Migration: Legacy Spanish Tables to English Tables with Bridge Aliases

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact's original schema was written with Spanish table and column names (reflecting the Peruvian market context and the developer team's language): `movimiento`, `detalle_venta`, `cliente`, `producto`, `almacen`, `comprobante`, `caja_session`. Over time this created several problems:

1. **Naming inconsistency** — New features added English tables (`cash_sessions`, `goods_receipts`, `tax_document`) while old features used Spanish tables. The codebase had no consistent naming convention.
2. **Column name confusion** — Spanish column names (`total_igv`, `serie`, `numero`, `fecha_creacion`) mixed with English conventions in newer code.
3. **SUNAT compliance evolution** — The original `movimiento` table had a monolithic structure that combined sale data and some invoice state. As SUNAT requirements evolved (credit notes, detractions, CDR tracking), the schema needed normalization that the original table couldn't accommodate cleanly.
4. **Android POS sync** — The `efact-app` Android POS needed to sync sales to the backend. The original `movimiento` table had no `idempotency_key`, `sync_status`, or `local_uuid` columns for offline-first sync. Adding these to `movimiento` without breaking existing queries was risky.
5. **Cash session clarity** — `cash_shift` (old) was ambiguous; `cash_sessions` (new) with explicit state machine (OPEN/RECONCILING/CLOSED) was clearer.

**The constraint:** The legacy tables were in active production use. A big-bang migration (rename all tables, update all queries at once) was too risky — a single missed reference would break SUNAT invoice emission for live tenants.

---

## Decision

Execute an **incremental V2 migration** with bridge aliases (backward-compatible ORM getters) rather than a big-bang rename:

### Strategy

1. **Create new V2 tables** alongside the old ones: `sales` (replacing `movimiento`), `tax_document` (replacing `comprobante`), `cash_sessions` (replacing `cash_shift`), `goods_receipts` (new for purchases).
2. **New code writes to V2 tables** exclusively.
3. **Legacy code is bridged** by adding alias getter/setter methods on the `SaleRecord` entity that call the real field names:

```php
// SaleRecord.php — bridge aliases for backward compatibility
public function getAlmacen(): ?Warehouse
{
    return $this->warehouse;  // "almacen" is the Spanish word for warehouse
}

public function getCliente(): ?Customer
{
    return $this->customer;
}

public function getFecha(): ?\DateTimeInterface
{
    return $this->issuedAt;
}

public function getFormaPago(): ?string
{
    return $this->paymentMethod;
}
```

4. **`InvoiceCreationService` bridges both table versions**: It resolves the V2 `sales.id` from the legacy `movimiento_id` field and writes to both tables during the transition period:

```php
// Resolve V2 sale ID from legacy ID (supports both old and new references)
$v2SaleId = $connection->fetchOne(
    'SELECT id FROM sales WHERE id = :id OR legacy_movimiento_id = :id LIMIT 1',
    ['id' => $saleId]
);
```

5. **`syncInvoicedSaleState()`** updates the V2 `sales` table after invoice creation, keeping both tables in sync during the transition.
6. **Migrations are additive** — New migrations add columns and tables; old tables are preserved until all references are updated.
7. **Legacy `movimiento_id` FK** is kept on `tax_document` alongside the new `sale_id` FK:

```sql
tax_document (
    movimiento_id INT NULL,       -- legacy FK to movimiento table
    sale_id VARCHAR(36) NULL,     -- V2 FK to sales table (UUID)
    ...
)
```

### Migration Progression (selected milestones from migrations/)

| Migration | Change |
|-----------|--------|
| `Version20251205023010` | Initial schema (legacy Spanish tables) |
| `Version20260115*` | Add `sales` table alongside `movimiento` |
| `Version20260201*` | Add `tax_document` table; add `sale_id` FK alongside `movimiento_id` |
| `Version20260210*` | Add `goods_receipts` with `idempotency_key`, `sync_status`, `local_uuid` |
| `Version20260220*` | Add `cash_sessions` with explicit state machine |
| `Version20260301*` | Add `sale_items` (V2) alongside `detalle_venta` |
| `Version20260315*` | Add soft-delete `deleted_at` on `usuario`, `goods_receipts`, `media` |
| `Version20260320*` | Add `user_notification` table |
| `Version20260325*` | Add `stock_lots`, `stock_movements`, `stock_items` for V2 inventory |

---

## Consequences

### Positive

- **Zero downtime migration** — Legacy tables and queries continue to work during the transition. No big-bang cutover.
- **Safe rollback** — If a V2 table or query has a bug, falling back to the legacy path is possible without data loss.
- **Incremental risk** — Each migration is small and focused; the risk surface of any single migration is limited.
- **Android sync enabled** — V2 `sales` table has all the fields needed for offline-first sync (`idempotency_key`, `sync_status`, `local_uuid`, `version_no`).

### Negative

- **Dual-write complexity** — During the transition, some writes must go to both old and new tables. `InvoiceCreationService` bridges both; if the bridge logic has a bug, data in the two tables can diverge.
- **Bridge aliases add indirection** — `getAlmacen()` returning `$this->warehouse` is confusing to new developers who expect Spanish property names to map to Spanish DB columns.
- **Lingering legacy tables** — Without a hard deadline and cleanup migration, legacy tables will persist indefinitely, adding confusion and schema clutter.
- **Test complexity** — Integration tests must cover both the V2 path and the legacy bridge path until the transition is complete.

### Cleanup Plan

Once all code paths write only to V2 tables:
1. Remove bridge alias methods from entities.
2. Remove `legacy_movimiento_id` FK from `tax_document`.
3. Drop legacy tables in a final cleanup migration.
4. Update all test fixtures to use V2 table names.

---

## Related ADRs

- ADR-009 — Inventory ledger + read model (stock_movements and stock_items are V2-only tables)
- ADR-005 — Offline-first (goods_receipts offline sync fields are V2-only)
