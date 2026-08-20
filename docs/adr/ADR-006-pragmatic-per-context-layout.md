# ADR-006: Pragmatic Per-Context Layout — DAIA Reserved for Contexts Under Active Rewrite

Date: 2026-08-19
Status: Accepted

## Context

ADR-002 mandated a `Domain/Application/Infrastructure/Api` (DAIA) layer structure for every context under `src/`, enforced by "PHPStan or Deptrac rules" that were never built. Fourteen months (in product time) and roughly twenty contexts later, an audit of `src/` found:

- **Zero** contexts fully follow DAIA, including `CashRegister` — the context ADR-001 and ADR-004 explicitly named as the pilot.
- Every context added since ADR-002 was accepted (`Payroll`, `AccountsPayable`, `BankReconciliation`, `FixedAssets`, `ExpenseReports`, `Purchasing`) shipped with the flat layout DAIA was meant to replace: `Entity/`, `Repository/`, `Service/`, `State/`, `Dto/`, `Controller/` (Symfony/API Platform's own idiomatic split, not this project's).
- The **one** place DAIA-style layering (`Domain/`, `Application/`, `Infrastructure/`, plus a thin `Controller/`) was actually built is `ControlPlane/Tenant`, under the dedicated rewrite in ADR-005 — a targeted, funded effort with its own ADR, not the ambient discipline ADR-002 assumed would just happen.

In other words: DAIA layering only ever got adopted when a context had a named rewrite project behind it. Left as a standing rule with no enforcement tooling and no rewrite budget, it was silently ignored by every context that didn't get one — which is most of the product surface, including newly-written code, not just legacy holdovers.

Continuing to cite ADR-002 as "the" architecture while the codebase demonstrates otherwise misleads anyone — human or agent — using `CONTEXT.md`/`PRD.md` to orient themselves before writing code in a context that was never rewritten.

## Decision

Adopt the layout the team has actually been using as the accepted pattern for new and existing contexts **not** currently under an active layered rewrite:

```
src/<Context>/
  Entity/       # Doctrine entities, frequently doubling as API Platform resources
  ApiResource/  # Used instead of Entity/ where the API resource is kept separate from the ORM entity (e.g. Inventory)
  Repository/   # Doctrine repositories
  Service/      # Application/use-case logic — the de facto "Application" layer, without the folder ceremony
  State/        # API Platform state providers/processors
  Dto/          # Input/output DTOs for API Platform operations
  Controller/   # Non-API-Platform HTTP endpoints (exports, webhooks, custom actions)
  Enum/         # Enums used by the entities/services above
```

This is not a downgrade of rigor — it's naming the rigor the team already applies:

- Business logic still belongs in `Service/`, not in controllers or state processors. Controllers/processors stay thin and delegate.
- Entities stay free of business rules beyond invariants they can enforce locally (e.g. append-only entities still expose no setters — see ADR-003).
- Cross-context communication rules from ADR-001 (and its 2026-08-19 amendment) are unaffected by this ADR — this is purely about the internal folder structure of one context, not how contexts talk to each other.

### When DAIA still applies

A context **may** adopt full DAIA layering when it undergoes a dedicated, named rewrite with its own ADR — as `ControlPlane/Tenant` did under ADR-005. DAIA is a tool for a funded migration project, not a default. A new ADR proposing a context rewrite should state explicitly whether it adopts DAIA and why the effort is justified for that context (extraction candidate, god-class debt, testability blocker) — not cite ADR-002 as a blanket requirement.

### CashRegister and the "pilot" label

ADR-004 no longer accurately describes `CashRegister`'s structure (see its 2026-08-19 implementation-status amendment). `CashRegister` is not a completed DAIA pilot; it is a context with a partial, stalled rewrite and two coexisting data models. This ADR does not resolve that — it only stops citing DAIA as the reason to expect otherwise. Finishing or abandoning the `CashRegister` migration is separate follow-up work (see ADR-004).

## Consequences

**Positive**
- Documentation matches reality: `CONTEXT.md`/`PRD.md` can point to this ADR instead of asserting a pattern the codebase doesn't follow.
- No forced migration of ~20 working contexts to a layout that was never enforced anyway.
- New contexts have a template that matches what reviewers actually expect to see, reducing PR friction.

**Negative**
- Contexts remain harder to extract to a separate service later than a fully DAIA-layered context would be — this was ADR-002's main promised benefit, and it's given up here for contexts outside a named rewrite.
- Domain logic is not guaranteed framework-free; `Service/` classes may depend on Doctrine/Symfony directly. Contexts that need portability (e.g. for eventual extraction) must opt into DAIA via their own rewrite ADR.
- Still no automated enforcement of *any* layering rule (flat or DAIA) — a Deptrac/PHPStan-rule gap this ADR does not close.

## Alternatives Considered

**Keep ADR-002 as the rule and treat every non-conforming context as debt to burn down** — rejected. Twenty contexts is not a backlog, it's the product. Declaring the entire codebase non-compliant with its own architecture doc is not actionable guidance; it just makes the documentation useless for onboarding.

**Retroactively migrate all contexts to DAIA** — rejected as a blanket mandate. Worth doing for a specific context when there's a concrete driver (extraction, testability, god-class debt), same bar as `ControlPlane/Tenant` cleared under ADR-005 — not as a mechanical sweep with no product value attached.

**Add Deptrac now and enforce DAIA going forward** — deferred, not rejected. If enforcement tooling gets built, it should encode *this* ADR's flat layout as the default (with an allowlist for DAIA contexts), not resurrect ADR-002 as the target.
