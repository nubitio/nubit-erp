# eFact Domain Context

## Target Architecture

> Decisions are recorded in `docs/adr/`. The section below summarises the agreed direction; see the referenced ADR for rationale and rejected alternatives.

### Multivertical Target — ADR-018

The agreed product direction is **Nubit**, a modular multivertical ERP (retail, pharmacy, restaurant, hospitality, distribution) on one reusable ERP core, with the dependency direction `Vertical → ERP → Platform` and rules AR-001…AR-010. Full target + gap analysis in [`docs/architecture/nubit-erp-target-architecture.md`](docs/architecture/nubit-erp-target-architecture.md); settled decisions in [ADR-018](docs/adr/ADR-018-nubit-multivertical-target-architecture.md) (eFact **is** the Nubit monorepo; verticals are bounded contexts under `src/`; ERP core is not extracted; capabilities — not presets — are the behavior switch, no `if vertical === …` in core; legacy Spanish table names are frozen, new tables are `<context>_*`). Convergence backlog: `nubitio/efact#326` (#327–#347).

### Architecture Style — ADR-001

eFact is a **modular monolith with strict bounded contexts** (see [ADR-001](docs/adr/ADR-001-modular-monolith-strict-bounded-contexts.md)).

- One deployable unit; contexts enforced by namespace boundaries, not network boundaries.
- Cross-context communication via **Symfony Messenger domain events**, with one declared exception: `Billing`/`Sales`/`Inventory`/`CashRegister` may call each other's Application services directly and synchronously for the sale → stock → fiscal-document → cash-movement path only. See [ADR-001](docs/adr/ADR-001-modular-monolith-strict-bounded-contexts.md)'s 2026-08-19 amendment for the exact scope — everything outside that path still goes through events.
- Migration strategy: **strangler-fig by context**. Pilot context: **CashRegister** — but see [ADR-004](docs/adr/ADR-004-cashregister-strangler-fig-decisions.md)'s 2026-08-19 amendment: most of that pilot's decisions were not actually implemented. Don't assume the `CashSession` aggregate model described there is the live one without checking.

### Module Responsibilities

The current module summary lives in [ADR-001](docs/adr/ADR-001-modular-monolith-strict-bounded-contexts.md); the canonical, complete context inventory is [`architecture/contexts.yaml`](architecture/contexts.yaml). Do not rely on a fixed context count.

### Layer Structure per Module — ADR-002 (superseded)

~~Each module follows a four-layer structure (see [ADR-002](docs/adr/ADR-002-domain-application-infrastructure-api-layers.md)):~~ **ADR-002 is superseded by [ADR-006](docs/adr/ADR-006-pragmatic-per-context-layout.md).** No context — not even the CashRegister pilot — actually uses the DAIA layering below, except `ControlPlane/Tenant` under its own dedicated rewrite (ADR-005). Most contexts use the pragmatic `Entity/`, `Repository/`, `Service/`, `State/`, `Dto/`, `Controller/`, `Enum/` layout; check each context rather than assuming a universal tree — see ADR-006. The structure below is kept only as historical context for reading ADR-002 itself:

```
src/<Context>/
  Domain/          # Entities, Value Objects, Domain Events, Repository Interfaces
  Application/     # Use-case Services, Commands/Queries, DTOs, Event Handlers
  Infrastructure/  # Doctrine repos, Messenger transports, external HTTP clients
  Api/             # API Platform resources, controllers, request/response transformers
```

Dependency rule: `Api → Application → Domain`; `Infrastructure → Domain` (implements interfaces).  
**Controllers and API resources only call Application services** — never Domain services or repositories directly.

### Append-only Ledgers and Immutable Fiscal Documents — ADR-003

See [ADR-003](docs/adr/ADR-003-append-only-ledgers-immutable-fiscal-documents.md).

- `stock_movements` and `cash_movements` are intended to be append-only ledgers — production code appends rows and has no update/delete workflow. Corrections should be represented by new rows referencing the original where supported. `stock_movements` is represented by an immutable API resource. `cash_movements` is written/read through DBAL and hydrated into a DTO; this is an application-path invariant, not a guaranteed database invariant. The trigger migration is retired/no-op and deliberately does not create or remove triggers; databases that applied an earlier trigger implementation may still have those triggers. See ADR-003 and ADR-004 for the distinction between the plural v2 ledger and the legacy singular table.
- Tax documents are **immutable after issuance**. Corrections use `credit_note` / `debit_note` documents linked via `references_document_id` (SUNAT CPE model). Voiding is a status transition, never a delete.
- Reporting aggregates/read models are not source-of-truth, but update/rebuild mechanisms vary; verify the specific projection before assuming it is rebuilt from events or can be regenerated on demand.

### Stack (confirmed, no changes)

PHP 8.5 · Symfony 7.4 · API Platform 4.1 · Doctrine ORM 3.5 · MariaDB (database-per-tenant + control-plane DB) · React 19 / Vite / TypeScript

*(Corrected 2026-08-19: this line previously said "PostgreSQL (schema-per-tenant...)". The actual engine is MariaDB — see `DATABASE_URL` in `.env` and `ENGINE = InnoDB` throughout `migrations/`. Tenancy is database-per-tenant, not schema-per-tenant: `TenantConnection` (`src/Efact/Doctrine/DBAL/TenantConnection.php`) switches the DBAL `dbname` per request based on the resolved tenant's subdomain, not a Postgres `search_path`.)*

## Product Model

### Tenant Runtime Config

Resolved tenant configuration consumed by frontend and application modules at runtime. It combines commercial entitlements, operational settings, tenant defaults, capabilities, counts and UI rules behind one interface.

Use this term instead of scattering feature flags or settings checks directly through screens.

### Capability

Operational behavior the product can enable for a tenant, such as `inventory.multi_warehouse`, `inventory.lot_tracking`, `cash.sessions` or `exports.excel`.

Capabilities describe behavior. Entitlements describe what the commercial plan allows.

### VerticalPreset

Versioned onboarding template that applies capabilities, defaults, menus, seed data and UI defaults for a target business type.

Examples (vertical axis only): `retail`, `pharmacy`, hardware store, butcher shop.

Implemented as the `App\Settings\Vertical\VerticalPresetInterface` seam (key, version,
label, settings, desired capabilities, single-stock-location flag). Presets are tagged
`app.vertical_preset` and resolved through `VerticalPresetRegistry`. `VerticalPresetApplier`
applies a preset to the current tenant (TenantSettings defaults + ensures a Default Stock
Location for single-location presets); it is idempotent and never deletes data. The console
command `tenant:apply-preset <key>` runs it per tenant. Concrete presets:
`RetailPreset` (key `retail`), `PharmacyPreset` (key `pharmacy`). Preset keys are
named after the vertical only — never the topology (no `single_store_*`).

The selected vertical is persisted on `TenantSettings.vertical` (written by the applier,
not by tenant PATCH). `TenantRuntimeConfigProvider` reads it as the source of truth for
`profile.vertical` / `profile.preset`, validating it against the registry (unknown →
`custom`). `simpleStoreMode` remains a separate, data-derived topology signal.

Onboarding entry points: the CLI `tenant:apply-preset <key>` and the admin-only
`POST /api/tenant/apply-preset` ({ "preset": "<key>" }), which returns the refreshed
runtime config. Frontend hook: `useApplyTenantPreset` (primes the runtime-config cache).

Post-provisioning onboarding is tracked by `TenantSettings.onboardingCompleted` and
exposed as `TenantRuntimeConfig.profile.onboardingCompleted`. The admin-only
`POST /api/tenant/onboarding/complete` marks the wizard complete and returns refreshed
runtime config. Frontend hook: `useCompleteTenantOnboarding`.

SUNAT certificate setup belongs to tenant onboarding/settings UX, but the lifecycle
remains in the control plane. `POST /api/tenant/certificate` is the tenant-facing
adapter: it accepts SOL credentials + PEM/PFX certificate, converts PFX to PEM when
needed, and calls `CertificateLifecycleServiceInterface` to persist metadata and sync
Lycet. Frontend hook: `useUploadTenantCertificate`.

The React post-provisioning wizard lives in `TenantOnboardingGate` and is rendered
before the normal tenant shell when `profile.onboardingCompleted === false`. It covers
Welcome/vertical, Company (reuses `consultService.consultRuc`), SUNAT certificate,
Branch + F001/B001 series, Warehouse and Done. The lightweight onboarding endpoints
under `/api/tenant/onboarding/*` save these steps without exposing Hydra/IRI details
to the wizard.

A preset encodes ONE axis only: the **vertical** (business type). The orthogonal
**topology/scale** axis (single vs multi store/warehouse) is NOT encoded in presets —
it is derived at runtime by `TenantRuntimeConfig` from the tenant's actual data
(`branchCount`, `warehouseCount`) and entitlements. This avoids a combinatorial
explosion of `single_store_pharmacy` / `multi_store_pharmacy` variants: one `pharmacy`
preset serves pharmacies of any size. `usesSingleStockLocation` only expresses a
preset's default topology intent for fresh tenants; runtime data always wins.

### Tienda Unica

Default product experience for a tenant with one physical store and one implicit stock location. Internally it still uses branch and warehouse records, but the user interface should not force the owner/cashier to think in branches or warehouses.

### Default Stock Location

The implicit branch and warehouse used when a tenant operates in Tienda Unica mode. Advanced tenants can still enable multi-branch or multi-warehouse capabilities.

### Navigation Tier

`TenantRuntimeConfig.profile.navigationTier` is the navigation scale axis used by the frontend shell and feature hubs. It is derived at runtime from topology counts and entitlements; it is **not** the same as `simpleStoreMode`.

| Tier | Meaning | Current derivation |
|---|---|---|
| `basic` | POS/facturación-first tenant with one implicit operating location | single branch/warehouse and no enterprise entitlements |
| `standard` | SMB operations with explicit purchasing/inventory workflows | multi-branch, multi-warehouse, or `multi_warehouse` entitlement |
| `advanced` | Enterprise/reporting surface | `advanced_reports` or `excel_export` entitlement |

`simpleStoreMode` remains a topology/layout signal: hide warehouse columns or default stock locations. Do not use it to decide which product modules are in the navigation. Navigation exposure uses `navigationTier`, capabilities, and role-menu permissions together.

Current hub structure:

- `/billing` (`Facturación`): wraps ventas, consultas, comprobantes and notas de crédito. Basic tenants see ventas/comprobantes; standard and advanced tenants also see consultas/notas.
- `/catalog` (`Catálogo`): wraps products and catalog configuration. Basic tenants see products only; standard and advanced tenants also see categories, units, prices and suppliers. Fiscal series stay in Configuration, not Catalog.
- `/inventory` (`Inventario`): wraps stock, movements and stock operations. The movement ledger tab is standard/advanced only; warehouse UI remains gated by `showWarehouseManagement`.
- `/config` (`Configuración`): basic tenants see company, SUNAT and fiscal series. Standard/advanced tenants also see user, branch and general configuration tabs. Warehouse management belongs to Inventory; legacy `/config/warehouses` redirects to `/inventory/warehouses`.

## Pending Simplification Work

1. Hide warehouse management for Tienda Unica tenants. (DONE)
   Route guard added to `/inventory/warehouses`, legacy redirects from `/warehouse` and `/config/warehouses` now check `showWarehouseManagement`. Warehouse hub tab and menu entries were already gated.

2. Hide stock transfers for Tienda Unica tenants. (DONE)
   Menu entries, hub banner, operation card, route guard on `StockEntryPage`, and `StockEntryPage` transfer form already gated by `showTransfers` / `inventory.multi_warehouse`.

3. Default warehouse in write forms. (DONE)
   Stock entry forms (`StockEntryFields.tsx`), purchase forms (`InboundFields.tsx`), purchase grid layout (`InboundPage.tsx`), and POS form all use `defaults.warehouse` / `showWarehouseSelector` to hide and auto-fill the warehouse field.

4. Make menu resolution capability-aware. (DONE)
   Menu resolution now combines `navigationTier`, capabilities/UI rules and role-menu permissions. The database menu defines the ceiling, `menuService.ts` filters unavailable paths at runtime, and feature hubs hide internal tabs using the same runtime config.

5. Add a `SaleSubmitter` seam in POS. (DONE)
   Online sales submit directly by default; the IndexedDB outbox handles network failure and offline mode. Implemented in `assets/features/pos/saleSubmitter.ts`.

6. Split the Sales/Billing mixed interface. (DONE)
   The broad `SaleInvoicingPortInterface` was replaced by narrower interfaces:
   `InvoiceCreatorInterface`, `SaleInvoicePrinterInterface`, `SaleVoiderInterface`
   and `RejectedDocumentReplacerInterface`. Invoice creation and rejected-document
   replacement now return HTTP-agnostic result DTOs (`InvoiceCreationResult`,
   `RejectedDocumentReplacementResult`); controllers own the HTTP response shaping.
   `SaleInvoicingAdapter` implements all four interfaces.
