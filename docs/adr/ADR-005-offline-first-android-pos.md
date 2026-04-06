# ADR-005 — Offline-First Design for Android POS and Backend Sync

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact POS terminals are deployed in retail environments (stores, pharmacies, restaurants) in Peru, where internet connectivity can be intermittent. A cashier must be able to:
- Complete sales transactions even when the backend is unreachable.
- Print receipts immediately without waiting for server confirmation.
- Have all offline transactions automatically synchronized and invoiced when connectivity is restored.

**Zero data loss is a hard requirement.** A sale that was rung up and paid for must never be lost due to a network outage, regardless of when the connection is restored.

The Android POS app (`efact-app`) and the web POS each implement offline-first patterns. This ADR covers both.

---

## Decision

### Android POS (`efact-app`)

**Local-first write path:**
1. All sales created on the Android POS are first written to **Room** (SQLite local database).
2. A Kotlin `WorkManager` worker (background sync) periodically attempts to sync pending sales to the backend API (`POST /api/sales`).
3. Each sale carries a `localUuid` (client-generated UUID v4) and an `idempotencyKey` (SHA-256 of localUuid + timestamp). The backend uses these to make sync idempotent.
4. After successful sync, the local record is updated with the server-assigned `id` and `syncStatus = SYNCED`.
5. If the sync fails (network error, server error), the record retains `syncStatus = PENDING` and the worker retries with exponential back-off.

**Offline data availability:**
- Product catalog, customer list, and configuration are prefetched and cached in Room at login / periodic refresh.
- The sale creation flow reads entirely from Room; no network call is required to complete a sale.
- ESC/POS thermal printing is local (Bluetooth); no network required.

### Web POS (`efact` frontend)

**IndexedDB outbox pattern (Dexie):**
1. Sale data is written to **IndexedDB** (via `Dexie`) before any network call.
2. A service worker (via `vite-plugin-pwa` / Workbox) intercepts `POST /api/sales` requests; if offline, it queues the request in the outbox.
3. When online, the outbox is drained: each queued request is replayed to the backend.
4. The `offline/api.ts` module handles the outbox write/read/drain logic.
5. Same idempotency key strategy as Android.

### Backend Idempotency (Shared)

The `Purchase` entity (`goods_receipts` table) carries these offline-sync fields:
```php
#[ORM\Column(name: 'local_uuid', type: Types::STRING, length: 36, nullable: true)]
private ?string $localUuid = null;

#[ORM\Column(name: 'idempotency_key', type: Types::STRING, length: 64, nullable: true, unique: true)]
private ?string $idempotencyKey = null;

#[ORM\Column(name: 'sync_status', type: Types::STRING, length: 20)]
private string $syncStatus = 'PENDING'; // PENDING | SYNCED | CONFLICT

#[ORM\Column(name: 'version_no', type: Types::INTEGER)]
private int $versionNo = 1;

#[ORM\Column(name: 'last_modified_at', type: Types::DATETIME_IMMUTABLE)]
private DateTimeImmutable $lastModifiedAt;
```

The `InventoryService` also uses idempotency keys on `stock_movements`:
```sql
UNIQUE INDEX uq_stock_movement_idempotency_key (idempotency_key)
```
A duplicate movement (same source_type + source_id + movement_type + warehouse + product) is silently skipped via `UniqueConstraintViolationException` catch — no error, no double-deduction.

### Conflict Resolution

- **Android → Server:** If the server already has a sale with the same `idempotencyKey`, the server returns the existing record (HTTP 200 with `syncStatus=SYNCED`). The app treats this as a successful sync.
- **Stock reconciliation:** `ReconciliationService` can be triggered to recompute `stock_items.qty_on_hand` from the full `stock_movements` ledger if a sync drift is detected.

---

## Consequences

### Positive

- **Zero data loss on connectivity loss** — Sales are persisted locally before any network attempt. Even if the device never reconnects, the sale record exists in Room/IndexedDB.
- **Instant UX** — Cashiers do not wait for server round-trips; the POS responds immediately to every action.
- **Idempotent sync** — Retrying a failed sync (after network restore) is safe; duplicate movements and duplicate sale records cannot be created.
- **Resilience to backend downtime** — Scheduled maintenance or backend upgrades do not interrupt sales operations.

### Negative

- **Sync complexity** — Conflict detection and resolution logic must be maintained in multiple layers (Android Room, web Dexie, backend idempotency).
- **Stale product catalog** — If the product catalog is not refreshed, a cashier may sell a product at an outdated price. The app must balance prefetch freshness with offline availability.
- **SUNAT invoicing is always online** — Electronic invoice generation and SUNAT submission require internet. Offline sales will have invoices generated asynchronously when connectivity is restored; the customer receipt may not include a SUNAT-valid comprobante until then.
- **Version conflicts** — Concurrent edits to the same sale record (rare but possible with multi-device scenarios) require a conflict resolution strategy. `versionNo` field exists for optimistic locking but conflict UI is not yet fully implemented.

---

## Related ADRs

- ADR-009 — Inventory ledger + read model (idempotency on stock movements)
- ADR-004 — FrankenPHP (worker mode state reset, relevant to sync endpoint handling)
