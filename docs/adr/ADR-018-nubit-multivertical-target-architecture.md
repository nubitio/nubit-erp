# ADR-018: Nubit Multivertical Target Architecture

Date: 2026-09-05
Status: Accepted

## Context

eFact is today a POS + electronic-invoicing product for Peru, built as a modular
monolith with ~20 bounded contexts (ADR-001). The `nubitio/*` Composer packages
already carry the portable Platform Core (contracts, tenancy, feature gates,
API-Platform bridge) out of the app.

The agreed product direction is broader: **Nubit**, a modular multivertical ERP
platform serving retail, pharmacy, restaurant, hospitality and distribution from
one reusable ERP core. The full target — layers, the
`Vertical → ERP → Platform` dependency direction, and rules AR-001…AR-010 — is
written up in `docs/architecture/nubit-erp-target-architecture.md`, with a
gap analysis (G1–G10) and a convergence backlog (`nubitio/efact#326`,
sub-issues #327–#347).

That document raised four decisions it could not settle on its own. This ADR
settles them so the backlog can proceed.

## Decision

### D1 — eFact is the `apps/erp` of a single Nubit monorepo; verticals live in `src/`

eFact's repository **is** the Nubit monorepo. There is no separate "Nubit
platform" repo to build. Conceptually the repo holds one application (`apps/erp`
in the target doc's vocabulary); the physical `apps/` / `modules/` / `verticals/`
split in the target doc is **aspirational structure, not a required move** —
logical separation by namespace and the AR rules matter, directory shape does not.

New verticals are born as **bounded contexts under `src/`** (`src/Pharmacy`,
`src/Restaurant`, `src/Hospitality`), exactly like the existing ~20 contexts —
not as separate repos or packages. They follow the pragmatic per-context layout
of ADR-006 (`Entity/`, `Repository/`, `Service/`, `State/`, `Controller/`,
`Enum/`), plus a context manifest (NB-2) declaring `dependsOn` and
`publicContracts`.

The `nubitio/*` packages stay as they are: published Platform Core + API-Platform
bridge + admin bundle, consumed via Composer. Nothing moves back into the repo.

Extraction of a vertical (or the ERP core) into its own package/service is
**out of scope** until the trigger in ADR-001 / extraction-plan §11 is met:
another application needs a different version of that code, or independent
deploy/scaling/failure-isolation is genuinely required.

### D2 — ERP Core is not extracted to a package in this horizon

`Catalog`, `Inventory`, `Sales`, `Billing`, `Purchasing`, `Payments`,
`Reporting` etc. stay in `src/`. We do **not** create `nubitio/erp-core`.

Reason: there is exactly one consumer (this app). Publishing the ERP core buys
nothing today and costs a stable public contract, semver discipline, and a
cross-repo change workflow for every refactor. Revisit only when a second
first-party application needs to consume the ERP core at a different version.

The `nubitio/*` boundary (Platform Core) is where "portable" stops for now.

### D3 — Capabilities are the first-class unit of behavior; presets only package

Two concepts stay separate (this restates and commits ADR-013):

- **Entitlement** — what the commercial plan allows (`PlanFeature`,
  `TenantFeatureOverride`). Stays as-is.
- **Capability** — an operational behavior the code branches on
  (`inventory.lot_tracking`, `cash.sessions`, `billing.sunat`,
  `catalog.weighted_items`). Becomes a **versioned catalog** (`CapabilityKey`,
  `CapabilityDefinition` with owner context / dependencies / required entitlement
  / config schema / safe defaults) resolved through one fail-closed
  `TenantCapabilityResolver` (NB-4…NB-7).

A **`VerticalPreset`** is a declarative bundle only — capabilities + defaults +
menus + seeds + UI defaults. It carries **no business logic** and no runtime
branching. Applying a preset writes tenant capability state; it is not consulted
at request time.

Hard rule (AR-007, and guardrail from `multivertical-capability-plan.md`):
**no `if vertical === …` in ERP Core or Commerce services.** Behavior is decided
by resolved capabilities. `vertical_key` is metadata for onboarding, support and
packaging — never a control-flow branch in a core service. A vertical that needs
real branching logic gets its own bounded context (D1) and reacts via events or
registered extension points (NB-11, NB-16).

### D4 — Freeze legacy Spanish table names; prefix everything new by context

Current tables are a mix: legacy Spanish (`producto`, `cliente`, `almacen`,
`comprobante`, `movimiento`, `nota`, `operacion`, …) and context-prefixed
(`inventory_movements`, `payroll_*`, `bank_*`, `integration_*`).

Decision: **do not mass-rename the legacy tables.** A rename touches Doctrine
mappings, 109+ migrations' assumptions, raw SQL in reports/PLE exports, and every
tenant database, for cosmetic gain and real regression risk in fiscal code.

Instead:

- Every **new** table is named `<context>_<name>` (snake, English), where
  `<context>` matches the owning bounded context (NB-13).
- Legacy tables are recorded, as-is, in the `ownsTables` manifest (NB-13) so the
  cross-context access guard (NB-15) still covers them.
- Opportunistic rename is allowed only when a table is already being reshaped by
  a migration for other reasons, and only with a compatibility view for the
  duration of the deprecation.

## Consequences

**Positive**

- The backlog (#327–#347) has an unambiguous target: contexts in `src/`,
  capabilities as the behavior switch, `nubitio/*` boundary unchanged.
- No new repos, no new published packages, no cross-repo change workflow.
- Fiscal-critical SQL and migrations are left alone (D4).
- ADR-013's entitlement/capability split gets a concrete implementation mandate.

**Negative**

- Table naming stays permanently inconsistent; readers must consult the
  `ownsTables` manifest rather than infer ownership from a prefix (D4).
- Keeping verticals in-repo means the monolith keeps growing; PHPArkitect,
  `architecture/contexts.yaml`, and the architecture tests now provide initial
  enforcement (NB-1/NB-2/NB-13/NB-15/NB-16). This is still partial: the
  PHPArkitect baseline contains pre-existing violations, and individual tests
  cover specific rules rather than proving all AR-001…AR-010 end-to-end.
- Deferring ERP-core extraction (D2) means if a second first-party consumer does
  appear, there is a real extraction project to do then, not now.

**Neutral**

- The target doc's `apps/` / `modules/` / `verticals/` tree is downgraded to
  illustration. If a physical reorg is ever wanted, it is its own ADR.

## Alternatives Considered

**D1 — separate Nubit platform repo, eFact consumes it.** Rejected: doubles the
change-management overhead (already paid once for `nubitio/*`) with no second
consumer to justify it. The monorepo keeps refactoring atomic.

**D2 — extract `nubitio/erp-core` now, ahead of need.** Rejected: premature.
Freezes contracts before we know which seams matter, for a single consumer.

**D3 — presets as the primary abstraction, capabilities derived from them.**
Rejected: leads back to per-vertical branching and a behavior matrix that is hard
to test and operate — the exact failure mode `multivertical-capability-plan.md`
was written to avoid.

**D4 — mass-rename legacy tables to English context prefixes.** Rejected: high
regression risk across fiscal code, migrations and every tenant DB, for a
cosmetic gain. Compatibility views would linger indefinitely.
