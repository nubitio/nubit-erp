# ADR-003: Append-only Ledgers and Immutable Fiscal Documents

Date: 2026-06-27
Status: Accepted

## Context

Two categories of data in eFact require audit-safe, tamper-evident storage:

1. **Stock and cash movements** — inventory adjustments and cash register transactions that auditors, reconciliation reports, and SUNAT may inspect.
2. **Tax documents** — boletas, facturas, notas de crédito/débito submitted to SUNAT via Lycet. Once a document is sent, its content is legally frozen by Peruvian fiscal regulation.

The existing schema allows `UPDATE` on movement rows and in-place mutation of tax document records, which creates audit gaps and complicates the SUNAT correction workflow.

## Decision

### Append-only ledger tables

`stock_movements` and `cash_movements` are **append-only** ledger tables:

- Rows are never `UPDATE`d or `DELETE`d after insertion.
- Current stock levels and cash balances are always derived by summing/aggregating movements (or from maintained projection/read-model tables in the `Reporting` context that consume movement events).
- Corrections are new rows with a negative quantity and a reference to the original movement (e.g., `corrects_movement_id`).
- Doctrine entities for these tables expose no setters that would allow post-persist mutation; the repository interface has only `append(Movement $m): void`.
  **Verified true for `Inventory\ApiResource\StockMovement`** (no setters, immutable after construction).
   **`cash_movements` (plural) — application-path append-only, not guaranteed by current migration code.** There is no ORM entity mapped to this v2 table: `CashMovementWriteProcessor` and `SalesWriteGateway::writeCashSaleMovement()` append via DBAL; reads use DBAL providers and `CashMovementHydrator`. Production application code has no update/delete workflow. Test seed/reset code does delete rows for cleanup. The trigger migration `Version20260904140000_cash_movements_immutability_guard` is now a no-op, as is `Version20260906030000_retire_cash_movements_triggers`; neither guarantees that triggers exist or removes triggers left on databases that applied the earlier trigger version. Thus append-only is an application convention, not a universal database constraint. The legacy singular `cash_movement` table/class is distinct; see ADR-004.
  **Correction, 2026-08-19:** an earlier pass of this note confused `cash_movements` (the ledger above) with `CashRegister\Entity\CashMovement`, a same-ish-named but different class mapped to the legacy **singular** `cash_movement` table — a pre-v2 table that is not `cash_movements` and is not what this ADR describes. That class does have public setters, but it's dead for writes (see ADR-004's amendment) and was never the append-only ledger to begin with. Two tables, `cash_movement` and `cash_movements`, one letter apart — worth double-checking which one is meant before trusting either name at a glance.

### Immutable fiscal documents

Tax documents (`tax_documents` table and related) are **immutable after creation**:

- No `UPDATE` is permitted on a tax document once it has been issued (status `emitted` or later).
- Corrections are represented as new documents of type `credit_note` or `debit_note` linked to the original via `references_document_id` — following the SUNAT CPE specification.
- Voiding an invoice that has not yet been sent to SUNAT is performed by setting status `voided` (a narrow allowed transition) — never by deleting the row.
- The `Billing` context enforces this invariant at the Application layer: `TaxDocumentService` refuses any mutation command on a document past the `draft` status and raises a domain exception.

### Projection / read-model tables

Reporting aggregates and operational projections (including stock on hand and sales summaries) may live separately from source ledgers and are not themselves the source of truth. Do not assume every cash/reporting projection is currently event-subscriber-driven or rebuildable: verify its owning implementation and rebuild path before relying on that property.

### Indexing

Each ledger and fiscal-document table must have explicit indexes documented alongside schema migrations:
- `stock_movements`: `(warehouse_id, product_variant_id, created_at)`, `(corrects_movement_id)`.
- `cash_movements`: `(session_id, created_at)`, `(corrects_movement_id)`.
- `tax_documents`: `(tenant_id, series, number)` unique, `(references_document_id)`, `(status, created_at)`.

## Consequences

**Positive**
- Full audit trail: every stock and cash change is traceable without relying on database logs.
- SUNAT compliance: credit/debit note model maps directly to CPE correction requirements.
- Read models can be rebuilt deterministically from ledger history (resilience against projection bugs).
- Simplifies concurrency: append-only removes update contention on movement rows.

**Negative**
- Stock-on-hand queries require aggregation or a maintained projection — cannot `SELECT current_stock FROM products`. Projection tables must be kept consistent.
- Slightly larger database footprint (no in-place correction of erroneous entries).
- Developers must understand the correction-via-new-row pattern; direct `UPDATE` must be blocked at the application layer (and optionally via DB-level triggers or RLS for defence-in-depth).

## Alternatives Considered

**Mutable rows with audit log (trigger-based)** — rejected. Trigger-based audit logs are fragile, invisible to application code, and not portable across database versions. The append-only model is self-documenting and application-enforced.

*(Correction, 2026-08-19: this ADR and `CONTEXT.md` both stated the database as PostgreSQL. The tenant and control-plane databases are MariaDB — see `DATABASE_URL`/`CONTROL_PLANE_DATABASE_URL` in `.env` and the migration SQL, which uses `ENGINE = InnoDB` throughout. The append-only reasoning above is unaffected by the engine; only the "portable across PostgreSQL versions" phrasing was wrong.)*

**Event sourcing for all entities** — considered but scoped down. Full event sourcing adds significant infrastructure (event store, projections framework). Append-only ledgers give the same auditability for the specific tables that need it without rewriting all entity persistence.
