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
