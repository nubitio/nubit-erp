# ADR-004: CashRegister Strangler-Fig Pilot Implementation Decisions

Date: 2026-06-27
Status: Accepted

## Context

ADR-001 designated `CashRegister` as the pilot bounded context for the strangler-fig migration strategy. The legacy module (`src/CashRegister/`) contained a mix of API Platform resources (`Entity/`), repositories (`Repository/`), DTOs (`Dto/`), state providers/processors (`State/`), and a controller — all without a clear layered structure or domain model.

The migration goal was to produce a greenfield `CashRegister` bounded context that follows the DAIA layering (ADR-002) and enforces append-only semantics (ADR-003), while preserving all existing API contracts.

Seven key decisions crystallised during implementation:

1. How to replace the legacy `CashShift` entity.
2. What to do with the legacy `CashMovementCategory` entity.
3. How to model cash movement types.
4. Where to place cash reconciliation in the session lifecycle.
5. How to gate session features per tenant.
6. How to enforce append-only immutability at the persistence layer.
7. How to migrate data and drop legacy tables.

## Decision

### 1. CashShift migrated 1-to-1 to CashSession aggregate

The legacy `CashShift` entity is replaced by a new `CashSession` aggregate root under `Domain/Model/`. Every foreign-key reference pointing to `cash_shifts` now points to `cash_sessions`. The mapping is direct — one legacy row becomes one `CashSession` row.

`CashSession` encapsulates the session lifecycle through factory methods and guards:

- `CashSession::open()` — factory method that enforces one-open-session-per-register via an optional `$currentlyOpenSession` parameter. It automatically creates the `OPENING_BALANCE` movement.
- `registerMovement()` — adds a `CashMovement` to the session's internal collection, guarded by an open-session check.
- `close()` — computes expected/counted/difference amounts from the movement ledger. Accepts an optional reconciliation payload that transitions the session to `RECONCILING` status.

### 2. cash_shifts table dropped after migration

A data migration script reads all rows from `cash_shifts`, transforms them into `CashSession` rows with corresponding `CashMovement` rows (including an `OPENING_BALANCE` movement for each), and inserts them into the new schema. After verification, the `cash_shifts` table is dropped. No legacy read path remains.

### 3. MovementType enum replaces CashMovementCategory entity

The legacy `CashMovementCategory` entity (a free-text category with a join table) is removed. Movements are now classified by a `MovementType` enum with five cases:

```php
enum MovementType: string
{
    case INCOME          = 'INCOME';          // (+) Cash inflow (sales, receivables)
    case EXPENSE         = 'EXPENSE';         // (-) Cash outflow (supplies, utilities)
    case WITHDRAWAL      = 'WITHDRAWAL';      // (-) Cash taken from register
    case DEPOSIT         = 'DEPOSIT';         // (+) Cash deposited to bank
    case OPENING_BALANCE = 'OPENING_BALANCE'; // (+) Initial float when session opens
}
```

The sign convention is encoded in `CashMovement::signedAmount()`: `EXPENSE` and `WITHDRAWAL` negate the stored (always-positive) amount. Other types return the amount as-is.

No `CashMovementCategory` entity or table exists in the new context. Categories were over-engineered for the actual usage patterns (all tenants use the same five built-in categories).

### 4. ReconcileCash is an inline sub-step of CloseCashSession

Cash reconciliation is not a separate use case. It is an optional sub-step within `CashSession::close()`:

```php
public function close(
    string $closedByUserId,
    string $countedAmount,
    // ...
    ?string $reconciliationId = null,
    ?string $reconciliationNote = null,
    ?string $reconciliationRequestedByUserId = null,
): ?CashReconciliation
```

When reconciliation parameters are provided, `close()` creates a `CashReconciliation` value object and transitions the session status to `RECONCILING`. Otherwise the session goes directly to `CLOSED`.

`CashReconciliation` is a `final readonly` value object with no behaviour — it only records the request. The reconciliation workflow (approval/rejection) is not in scope for the initial pilot.

### 5. Capability gate (cash.sessions) in the Application layer

The `CashSessionCapabilityGuard` in `Application/Guard/` checks the `cash.sessions` capability before any session operation. It is injected into use-case handlers that open, modify, or close sessions. The capability is defined in the `Settings` context and served via `FeatureCheckerInterface`.

This means cash session features are hidden for tenants whose commercial plan does not include `cash.sessions`. The gate is at the Application layer — API controllers, providers, and processors do not check capabilities themselves.

### 6. ImmutableLedgerListener enforces append-only on cash_movements

A Doctrine event listener (`ImmutableLedgerListener`, tagged with `#[AsDoctrineListener]`) intercepts `preUpdate` and `preRemove` events. If the entity is an instance of the Doctrine entity `CashMovement` (in `Infrastructure/Doctrine/CashMovement`), it throws `ImmutableLedgerViolationException`.

This is a defence-in-depth measure: the domain model already exposes no setters on `CashMovement`, and the repository interface `CashMovementRepositoryInterface` exposes only `append(CashMovement): void`. The listener guards against accidental or programmatic misuse at the ORM layer.

### 7. Session statuses: OPEN, CLOSED, RECONCILING

The `CashSessionStatus` enum captures all valid session states:

```php
enum CashSessionStatus: string
{
    case OPEN         = 'OPEN';
    case CLOSED       = 'CLOSED';
    case RECONCILING  = 'RECONCILING';
}
```

Transitions: `OPEN` → `CLOSED` (standard close) or `OPEN` → `RECONCILING` (close with discrepancy requiring reconciliation). There is no transition out of `CLOSED` or `RECONCILING` in the initial scope — reopening a closed session is not supported.

### Data migration

A one-time CLI command (`cash-register:migrate-from-legacy`) performs:

1. Read all `cash_shifts` rows with their related `cash_movement` and `cash_movement_category` rows.
2. For each shift, create a `CashSession` with `OPENING_BALANCE` movement.
3. For each legacy movement, create a `CashMovement` mapped to the new `MovementType` enum (legacy category name → enum case mapping).
4. Write all entities within a transaction.
5. Verify counts match.
6. Drop `cash_shifts`, `cash_movement_category`, and related join tables.

### Repository naming convention

Domain repository interfaces use the pattern `<Aggregate>RepositoryInterface`:

| Interface | Location |
|---|---|
| `CashSessionRepositoryInterface` | `Domain/Repository/` |
| `CashMovementRepositoryInterface` | `Domain/Repository/` |
| `CashReconciliationRepositoryInterface` | `Domain/Repository/` |

Implementations in `Infrastructure/Doctrine/` follow the naming `<Aggregate>Repository` (e.g. `DoctrineCashSessionRepository`).

## Consequences

**Positive**
- The five-case `MovementType` enum eliminates an entire join-table entity and category CRUD.
- `CashSession` aggregate enforces session lifecycle invariants at the domain layer — no invalid state transitions possible.
- Reconciliation as an optional sub-step of `close()` avoids a separate use case while keeping the model honest about discrepancies.
- Defence-in-depth on append-only: domain model (no setters) + repository (only `append`) + Doctrine listener (`preUpdate`/`preRemove`).
- Capability gate protects feature rollout per tenant without controller-level checks.
- Dropping `cash_shifts` eliminates the legacy read path entirely — no dual-write maintenance burden.

**Negative**
- The 1-to-1 migration leaves no rollback path once `cash_shifts` is dropped. The migration script must be thoroughly tested.
- `RECONCILING` status has no follow-up transition in the initial scope — subsequent ADRs must define the resolution flow (approve/reject discrepancy).
- The sign convention in `signedAmount()` places domain logic in a getter; callers must understand that `amount()` returns the raw (unsigned) value.
- `CashReconciliation` as a read-only value object cannot be updated — any reconciliation workflow that requires status updates will need a new aggregate or entity.

## Alternatives Considered

**CashMovementCategory as a value object** — considered but rejected. The legacy entity had a join table and was effectively acting as a hard-coded list of five categories. An enum is simpler, type-safe, and eliminates the join table entirely. If tenant-specific categories are needed later, a value object with database-backed allowed-values can be introduced without changing the `MovementType` enum.

**ReconcileCash as a separate use case** — considered but rejected. Reconciliation is only ever triggered as part of closing a session. A separate handler would require the close handler to emit an event that the reconcile handler listens to, adding async complexity with no benefit. Inlining it keeps the session lifecycle atomic within one database transaction.

**Reopening closed sessions** — considered but rejected for the initial pilot. If a cashier closes with a discrepancy and needs to re-count, the current model requires either: (a) a new session, or (b) a future "reopen adjustment" feature. The team chose to defer this to a follow-up ADR.

**Database-level triggers for append-only enforcement** — considered but rejected in ADR-003. The Doctrine listener approach is portable, testable, and visible in the codebase. Triggers would be a defence-in-depth addition, not a replacement.
