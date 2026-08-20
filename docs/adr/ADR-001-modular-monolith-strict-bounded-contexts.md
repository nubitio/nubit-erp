# ADR-001: Modular Monolith with Strict Bounded Contexts

Date: 2026-06-27
Status: Accepted

## Context

eFact is a multi-tenant SaaS POS and electronic-invoicing platform for the Peruvian market. The codebase has grown organically and mixes responsibilities across modules, making it difficult to reason about ownership, test in isolation, or extract contexts later.

The team evaluated three architectural styles:

- **Distributed microservices** — each context as a separate deployable unit.
- **Modular monolith with strict bounded contexts** — one deployable, multiple internal modules with enforced boundaries.
- **Unstructured monolith** — status quo; no enforced boundaries.

Operational constraints: a small engineering team, a single production deployment per tenant schema group, and the need to iterate quickly on SUNAT compliance requirements.

## Decision

Adopt a **modular monolith** with **strict bounded contexts**. Each context is a top-level PHP namespace under `src/` with its own Domain, Application, Infrastructure, and Api layers. Cross-context coupling is prohibited at the class level; contexts communicate exclusively through **internal domain events** dispatched via Symfony Messenger.

### Confirmed module map

| Module | Responsibility |
|---|---|
| `ControlPlane` | Tenant registry, commercial plans, SUNAT certificate lifecycle, provisioning |
| `Settings` | TenantRuntimeConfig, VerticalPresets, Capabilities |
| `Contacts` | Unified partner registry (customers, suppliers, employees) with role assignments |
| `Catalog` | Products, variants, price lists, tax rules |
| `Inventory` | Stock ledger (append-only movements), warehouse management |
| `Sales` | Orders, payments — separated from tax document issuance |
| `Billing` | Tax documents (boletas, facturas), SUNAT/Lycet submissions — immutable |
| `CashRegister` | Sessions, cash movements, reconciliations |
| `Reporting` | Read models and projections only — no writes to source-of-truth tables |
| `Purchasing` | Purchase orders, supplier receipts, cost updates into Inventory |
| `AccountsPayable` | Supplier invoices/bills and payment tracking — not double-entry accounting (see Non-Goals in `PRD.md`) |
| `BankReconciliation` | Bank account statement import and matching against cash/sales movements |
| `FixedAssets` | Owned equipment tracking and straight-line depreciation |
| `Payroll` | Employees, monthly/gratificación/CTS/liquidación runs, PLAME export |
| `ExpenseReports` | Employee expense submission and approval |
| `Taxes` | Shared fiscal utilities (rates, rules) consumed by Billing/Payroll — thin, not a full domain context |
| `Identity` | Authentication, users, roles/permissions (tenant-scoped) |
| `Organization` | Company, branch, and organizational structure records |
| `Setup` | Initial tenant setup wizard and seed orchestration |
| `AI` | AI assistant, MCP tools (`SaleTools`, `ProductTools`, `CashRegisterTools`, `DocumentationTools`), RAG knowledge index |
| `Core` / `Efact` | Cross-cutting framework glue (tenant connection, mercure, filesystem, validation) — deliberately outside the bounded-context rule below; see Amendment 2026-08-19 |

*(This table was last reconciled against `src/` on 2026-08-19 — see `PRD.md` for feature-completeness detail on the newer modules, several of which predate their own documentation.)*

### Event bus

Symfony Messenger is the sole cross-context bus. Events are value objects defined in the **emitting** context. Subscribers live in the **consuming** context. No context may directly call a service from another context's Application or Domain layer.

### Implementation strategy

Strangler-fig migration by context. Pilot context: **CashRegister** (greenfield bounded context — establishes the pattern before migrating legacy modules).

## Consequences

**Positive**
- Clear ownership per context; easier onboarding and code review.
- Contexts can be extracted to separate services later with minimal coupling to break.
- Testability: each context's domain and application layers are testable without HTTP or database.
- SUNAT compliance changes are localised to `Billing`.

**Negative**
- Cross-context queries require either events, read-model projections (Reporting), or explicit query buses — no lazy Doctrine associations across context boundaries.
- Initial migration effort: legacy code must be moved or wrapped before the boundary is enforced.
- Messenger adds async complexity for eventual-consistency scenarios.

## Alternatives Considered

**Distributed microservices** — rejected. Operational overhead (service mesh, distributed tracing, cross-service transactions) exceeds the team's capacity. The monolith can be split later if justified.

**Unstructured monolith** — rejected. Current state is unsustainable; cross-module coupling blocks safe refactoring and makes SUNAT compliance work risky.

## Amendment (2026-08-19) — the events-only rule has a declared exception

An audit of `src/` found direct class-level imports across `Billing` ↔ `Sales` ↔ `Inventory` ↔ `CashRegister` in 20+ files — a straightforward reading of this ADR would call that a violation. On inspection, essentially all of it sits on one path: **recording a sale synchronously drives a tax document (Billing) and a stock deduction (Inventory) in the same request**, and the team has never wanted eventual consistency there — an accepted sale with a stock write that fails silently later, or an invoice that "will eventually" reflect the stock movement, is a worse failure mode for a POS+fiscal product than a documented coupling exception.

This ADR is amended to make that exception explicit rather than leave it as an unreviewed gap:

- `Billing`, `Sales`, `Inventory`, and `CashRegister` may call each other's Application-layer services **directly, synchronously, within the same request/transaction**, for the specific path of: sale creation → stock deduction → tax document issuance → cash movement. This is the one place in the system where transactional consistency is judged more important than context isolation.
- Everything **outside** that four-context transactional core still follows the events-only rule from this ADR unchanged — including calls *into* that core from `Payroll`, `Purchasing`, `AccountsPayable`, `BankReconciliation`, `Reporting`, or any other context, which must still go through Messenger domain events.
- This is a decision, not a grandfathered violation: new direct coupling between these four contexts for the sale/stock/fiscal-document/cash path does not need a new ADR. New coupling for anything else does.
- `Core`/`Efact` (tenant connection resolution, Mercure publishing, filesystem, validation) is cross-cutting infrastructure consumed by every context and was never meant to be a bounded context under this rule — it is not an exception to track, just outside the rule's scope.

Enforcement remains a gap: nothing (Deptrac, PHPStan rule, CI check) currently distinguishes "coupling inside the declared exception" from "coupling that should have gone through an event." Until such a check exists, code review is the only enforcement mechanism — a reviewer should ask "is this the sale/stock/fiscal/cash path?" before approving a new cross-context import.
