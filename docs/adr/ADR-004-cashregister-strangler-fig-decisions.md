# ADR-004: CashRegister Strangler-Fig Pilot Implementation Decisions

Date: 2026-06-27
Status: Accepted — **Functional cutover done, legacy tables dropped (2026-08-19); architectural goals (DDD aggregate, capability guard, append-only enforcement) not built. See Amendment below before trusting any claim in "Decision"**

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

**Database-level triggers for append-only enforcement** — a trigger migration was attempted, but DDL privileges with binary logging prevented reliable installation/removal in tenant databases. In the repository now, `Version20260904140000_cash_movements_immutability_guard` and `Version20260906030000_retire_cash_movements_triggers` are both no-ops; the latter explicitly leaves any pre-existing triggers in place for manual DBA removal. Therefore the codebase does not guarantee database-level protection. The live production write path appends through POST/DBAL and has no update/delete operation; test seed/cleanup code may delete rows. No current migration test named `CashMovementsImmutabilityTriggerTest` exists.

## Amendment (2026-08-19) — implementation status vs. this record

An audit of `src/CashRegister/` against this ADR's "Decision" section initially concluded the migration mostly didn't happen. **That first pass was too harsh** — it checked entity/class shape against the ADR's DDD-specific prescriptions (aggregate under `Domain/Model/`, guard, listener) without checking which model the *live request path* actually reads and writes. A follow-up pass, prompted by finding a separate `SLICE-008`/`SPEC-007` commit trail (`git log`: "SLICE-008 ... remove legacy dual-write, migrate processors and dashboard to v2 tables", "SPEC-007: add UUID columns to cash_sessions/cash_movements, create cash_reconciliations") not referenced anywhere in this ADR, found the functional cutover is further along than the first pass credited. Corrected, point by point:

| # | Decision | Status |
|---|---|---|
| 1 | `CashShift` replaced by `CashSession` aggregate under `Domain/Model/` | **Split verdict.** The *entity* was not rebuilt as a `Domain/Model/` DDD aggregate — `CashSession` (`src/CashRegister/Entity/CashSession.php`) is a flat, mutable entity (15 public setters), no `open()`/`registerMovement()`/`close()` factory/guard methods. But the *application flow* genuinely runs on it: `CashRegisterOpenProcessor`, `CashRegisterCloseProcessor`, `CashRegisterShiftsProvider`, and `CashMovementItemProvider` (the entire open/close/movements/dashboard path) all depend on `CashSession`/`CashMovement`, not `CashShift`. |
| 2 | Legacy cash-shift table dropped after migration | **`cash_shift` (singular) and legacy `cash_movement` were dropped** by `Version20260819210000_drop_legacy_cash_shift_tables`; the migration is guarded for partial DDL application and irreversible with respect to historical row data. `CashRegisterTools` now reads `CashSessionRepository::findLatestByCashRegister()`; the regression test covers that reader. Do not call the removed table `cash_shifts`—the live table is `cash_sessions`. |
| 3 | `MovementType` enum (5 cases) replaces `CashMovementCategory` entity | **Not a replacement — became a two-tier classification, seemingly by design.** The enum exists as `CashMovementType` (`src/CashRegister/Enum/CashMovementType.php`), not `MovementType`, and `CashMovementCategory` was not removed: `CashMovement::$category` is still a live ORM relation to it, seeded with 9 named codes (`CASH_SALE`, `CREDIT_PAYMENT`, `CUSTOMER_REFUND`, ...) by `InitialSetupService`/`InitialDataSeeder`, and referenced by `Billing\Sale\Void\CashRefundRegistrar`. Critically, `CashMovement::setCategory()` *derives* `$type` from `$category->getType()` (`src/CashRegister/Entity/CashMovement.php:123-126`) — `type` is a denormalized field for fast filtering, not an independent classification competing with category. Read generously, this is category (granular, 9 codes) + type (coarse, 5 cases, derived) rather than "two ways of classifying the same thing" — but it is still not what this ADR describes (category entity removed, enum as sole classification). |
| 4 | Reconciliation as an inline sub-step of `close()` | **Not verifiable as described.** No `CashSession::close()` method with the described signature exists (see #1) — `CashRegisterCloseProcessor` (a `State/` processor) appears to own this logic instead, flat-layout style. Not re-audited in depth; flagged for whoever picks this ADR back up. |
| 5 | `CashSessionCapabilityGuard` gates session ops on `cash.sessions` capability | **Not found.** No file, class, or capability string named `cash.sessions` or `CashSessionCapabilityGuard` exists anywhere in `src/`. |
| 6 | `ImmutableLedgerListener` enforces append-only on `preUpdate`/`preRemove` for `CashMovement` | **Listener was never built.** The v2 table has no ORM entity mapping; the PHP class is a DTO. The trigger migrations in the current tree are no-ops: the first retired the earlier trigger DDL, and the follow-up also does not drop any existing triggers because that needs elevated privileges with binary logging enabled. Existing databases may therefore differ depending on migration history; deployments must be inspected to determine whether legacy triggers remain. Current app writes append via DBAL and has no production update/delete operation. The test file previously cited for triggers is absent from the current tree. |
| 7 | `CashSessionStatus` enum: `OPEN`/`CLOSED`/`RECONCILING` | Not re-verified in this pass; lower risk than 1/2/3/5/6 above. |

**What this means going forward:**

- The **functional migration succeeded**: the live POS open/close/movement/dashboard flow runs on `CashSession`/`CashMovement`, not `CashShift`/legacy tables. Don't design new work assuming `CashShift` is the live model — it isn't, for anything reachable from the tenant app.
- The **architectural goals of this ADR (DDD aggregate, capability guard, immutable-ledger listener) were not built as originally specified** — `CashSession` is a plain mutable entity and sessions aren't capability-gated. `cash_movements` (the plural v2 ledger) has an append-only production application path, but the repository does not guarantee database enforcement: the trigger migrations are no-ops and existing database triggers, if any, depend on prior migration history.
- **Bug found and fixed (2026-08-19, first pass)**: `App\AI\Tool\CashRegisterTools` read `CashShiftRepository`, a table nothing writes to anymore. Now reads `CashSessionRepository`.
- **Three more bugs of the same species found and fixed (2026-08-19, second pass)**, all rooted in `CashMovement` (the ORM entity class) still being mapped to the frozen legacy `cash_movement` (singular) table while every write goes to v2 `cash_movements` (plural):
  1. `GetCollection()` on `/api/cash-movements` had no explicit provider, so API Platform's default ORM provider read the dead table. Fixed with `CashMovementCollectionProvider` (v2, DBAL).
  2. `BankReconciliationMatchingService::unreconciledCashMovements()` and `cashMovementCandidates()` queried `CashMovement` via ORM — meaning bank reconciliation had been unable to surface **any** cash movement for matching since before `BankReconciliation` even existed as a module (it shipped 2026-08-14, seven weeks after the v2 cutover on 2026-06-27; the legacy table was already frozen when the module was built). Fixed to read `cash_movements` directly.
  3. `BankStatementLineMatchProcessor` confirmed a cash-movement match via `EntityManager::find(CashMovement::class, $id)`, which would have looked in the dead table and 404'd for every id the two fixes above now correctly surface. Fixed to verify existence against v2 and write the FK via `EntityManager::getReference()` instead.
  A companion migration (`Version20260819200000_bank_reconciliation_cash_movement_fk`) drops `bank_reconciliation_match`'s `FK_BRM_CASH_MOVEMENT` constraint (only the constraint — column, data, and index untouched), since it pointed at `cash_movement(id)` and would reject any FK write using a v2 `cash_movements(id)` value. A shared `App\CashRegister\Service\CashMovementHydrator` now backs all four v2-aware read paths so this mapping exists in one place instead of four.
- **`cash_movement` and `cash_shift` are dropped (2026-08-19, third pass, `Version20260819210000_drop_legacy_cash_shift_tables`).** The historical-data-retention question the previous pass of this note raised was put to the person who owns that call, who confirmed the loss was acceptable. Before dropping: `CashMovement`'s `#[ORM\Entity]` mapping and dead `cashShift` relation were removed (it's a plain DTO now, not a Doctrine entity — see its class docblock), and `CashShift`'s base CRUD `#[ApiResource]` operations (`GetCollection`/`Post`/`Get`/`Patch`/`Delete`, all using the default ORM provider/processor) were removed too — **a fourth live consumer of the dead table found only while preparing the drop**: `/api/cash-shifts` was a fully wired, reachable CRUD endpoint with zero frontend usage and zero test coverage, which would have started 500ing the moment the table disappeared. `CashShift`'s three `/cash-shifts/{id}/...` sub-resource routes (movements/summary/x-report) are kept — they only ever resolved through `cash_sessions.legacy_cash_shift_id`, a plain lookup column with no live FK once `cash_sessions`' own FK to `cash_shift` was dropped in the same migration. `CashShiftRepository` and `CashMovementRepository` (both already dead — see decision #2/#6 above) are deleted. MySQL/MariaDB DDL isn't transactional: the first `up()` attempt partially applied before failing on the `cash_sessions` FK this note hadn't caught yet, so the migration's `up()` guards every statement with an information_schema check rather than assuming a clean starting state — worth knowing if this pattern is needed again.
- `CashMovementCategory`/its table is a separate, third loose end: still live and required by the v2 write path (`CashMovementWriteProcessor` stores `category_id` sourced from it) — not a candidate for removal, just never reconciled with this ADR's prediction that an enum would fully replace it.
- This ADR's planning trail (`SLICE-008`, `SPEC-007`, `T008-1..5`) exists in `git log` and code comments but not as a document in this repo — see ADR-016 for what that means for `SLICE-*`/`SPEC-*` as a documentation source generally.
