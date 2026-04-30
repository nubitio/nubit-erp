# ADR-019 — ControlPlane bounded context and Core platform library boundary

## Status

Accepted

## Date

2026-04-24

---

## Context

`src/ControlPlane` groups tenant provisioning, onboarding, subscription, quota, admin, backup, and related SaaS control-plane behavior. The project may eventually extract this control plane into a separate repository, package, or service.

At the same time, `App\Core` is expected to evolve into a reusable PHP platform library for multiple projects. Today, several Core classes import concrete ControlPlane services, which couples the platform layer to this application's SaaS control-plane implementation.

---

## Decision

We will treat `ControlPlane` as an extractable bounded context and `Core` as a future platform library.

Rules:

1. `ControlPlane` may depend on generic, reusable Core platform capabilities.
2. `Core` must not depend on concrete `App\ControlPlane\...` classes.
3. Core-to-ControlPlane dependencies must be inverted through platform contracts or moved out of Core.
4. ControlPlane dependencies on app-specific modules such as `Setup` and `Billing` must move behind ControlPlane-owned ports/adapters over time.
5. ControlPlane public API must be explicit; implementation services remain internal by default.

---

## Consequences

### Positive

- `Core` can become reusable without dragging SaaS control-plane behavior into other projects.
- `ControlPlane` becomes easier to extract because its dependencies are visible and adapter-based.
- Future features such as certificate lifecycle management have a clear owner inside ControlPlane.

### Negative / Risks

- Short-term refactor overhead: interfaces/adapters may look redundant until extraction pressure increases.
- Some existing command and listener flows are tightly coupled and require careful incremental migration.
- Boundary checks may initially need temporary exceptions while ports are introduced.

### Rollback

The first phases are additive and dependency-inversion oriented. If needed, aliases can be rewired back to existing concrete services without changing persisted data or user-facing behavior.
