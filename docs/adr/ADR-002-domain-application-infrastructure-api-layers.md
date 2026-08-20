# ADR-002: Domain/Application/Infrastructure/Api Layer Structure per Module

Date: 2026-06-27
Status: **Superseded by [ADR-006](ADR-006-pragmatic-per-context-layout.md) (2026-08-19)**

> **Why superseded, not just amended:** as of 2026-08-19, zero of the ~20 contexts under `src/` follow the DAIA layering mandated below — not even `CashRegister`, the context this ADR and ADR-004 named as the pilot (see ADR-004's implementation-status amendment). The one place DAIA-style layering was actually built is `ControlPlane/Tenant` under ADR-005. Every other context — including all seven added since this ADR was accepted (`Payroll`, `AccountsPayable`, `BankReconciliation`, `FixedAssets`, `ExpenseReports`, `Purchasing`, and `CashRegister` itself) — uses a flat `Entity/Repository/Service/State/Dto/Controller` layout instead. This ADR is kept for its historical reasoning (still worth reading), but ADR-006 records what the team actually does and should be followed for new contexts. Treat this document as **not currently in force**.

## Context

Each bounded context established in ADR-001 must have an internal structure that enforces the dependency rule (outer layers depend on inner layers, never the reverse) and keeps domain logic free of framework and persistence concerns.

Three internal layering approaches were considered:

- **Flat namespace** — all classes in one directory; fast to start, degrades quickly.
- **Domain/Application/Infrastructure/Api (DAIA)** — four explicit layers with enforced dependency direction.
- **Hexagonal ports-and-adapters** — explicit port interfaces with named adapter groups; more ceremony.

## Decision

Each module under `src/<Context>/` is structured as four layers:

```
src/<Context>/
  Domain/          # Entities, Value Objects, Domain Events, Repository Interfaces, Domain Services
  Application/     # Use-case Services, Command/Query objects, DTOs, Application Event Handlers
  Infrastructure/  # Doctrine repositories, Messenger transports, external HTTP clients, persistence
  Api/             # API Platform resources, controllers, request/response transformers
```

### Dependency rule

```
Api → Application → Domain
Infrastructure → Domain (implements Domain interfaces)
```

`Domain` has **zero** framework dependencies (no Symfony, no Doctrine annotations if they can be avoided — use XML or attribute-less mappings).  
`Application` may depend on Symfony contracts (e.g., `LoggerInterface`, `EventDispatcherInterface`) but not on HTTP or persistence.  
`Api` (controllers, API Platform state providers/processors) may only call **Application services** — never Domain services or repositories directly.  
`Infrastructure` implements Domain interfaces and may use any framework or ORM primitive.

### Controllers and API resources

Controllers and API Platform state providers/processors are the only classes allowed to handle HTTP concerns (status codes, request parsing, response shaping). They delegate all business logic to Application services. No business logic in controllers.

### Cross-context communication

Application services in one context must not instantiate or inject services from another context's Application or Domain layer. Cross-context calls go through Symfony Messenger domain events (see ADR-001).

## Consequences

**Positive**
- Domain logic is portable and independently testable (no framework bootstrap required).
- Clear seam for mocking: tests swap Infrastructure with in-memory fakes at the Application boundary.
- API Platform customisation (state providers, processors) is localised to `Api/`.
- Onboarding: a developer can read any context by following the four-folder structure.

**Negative**
- More files and directories than a flat layout — acceptable trade-off for a growing codebase.
- Discipline required: PHPStan or Deptrac rules must enforce the dependency direction; a loose team convention alone will not hold.
- Mapper/transformer boilerplate between layers (Domain → DTO → API response).

## Alternatives Considered

**Flat namespace** — rejected. Proven to degrade into a big ball of mud as contexts grow.

**Hexagonal ports-and-adapters (strict)** — considered; DAIA achieves the same dependency inversion with less naming ceremony. Port/adapter language can be adopted incrementally inside `Infrastructure/` without changing the top-level structure.
