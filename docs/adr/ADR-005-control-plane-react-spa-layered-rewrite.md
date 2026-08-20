# ADR-005: Control Plane React SPA and Layered Backend Rewrite

Date: 2026-07-04
Status: Accepted — **Implemented** (verified 2026-08-19, see Amendment below)

## Context

The eFact control plane (`/admin`) is the operator surface for multi-tenant SaaS management: tenant registry, plans, certificates, provisioning, backups, subscriptions, and audit.

The current implementation has accumulated structural debt:

- `TenantManager` (~930 lines) mixes registry reads, mutations, caching, YAML fallback, and raw DBAL SQL.
- `TenantController` (~990 lines) and `tenant/edit.html.twig` (~937 lines) are monolithic.
- The operator UI is Twig + Bootstrap CDN, isolated from the React 19 + `@nubitio/admin` stack used by tenant admins.
- `ControlPlane` does not follow the DAIA layering mandated by ADR-002.

Operators need a cohesive, fast UI aligned with the product design system. The backend must be testable, layered, and ready for a future `nubit/control-plane` extraction (v1.2 roadmap).

## Decision

### Backend — aggressive layered rewrite

Restructure `App\ControlPlane\Tenant` (and progressively other sub-modules) into ADR-002 layers:

```
Domain/          Entities, VOs, repository ports
Application/     Use-case services (TenantService, TenantRegistry)
Infrastructure/  DBAL repositories, YAML dev store, cache invalidation
Api/             REST controllers under /api/control-plane/*
```

- **Delete** `TenantManager`; replace with `TenantService` (mutations + reads) and `TenantRegistry` (implements `TenantRegistryInterface`).
- Keep the array-based tenant payload at integration boundaries for now (platform contract stability).
- Preserve YAML fallback for local dev (`TENANTS_SOURCE=yaml`).
- Operator REST API is session-authenticated (shared `control_plane` security context with `/admin`).

### Frontend — React SPA

- New Vite entry: `assets/control-plane.tsx`.
- Feature module: `assets/features/control-plane/`.
- `BrowserRouter` with `basename="/admin"` (tenant app keeps `HashRouter`).
- Login remains server-rendered Twig at `/admin/login` (CSRF + throttling).
- All other `/admin/*` routes serve the SPA shell; data via `/api/control-plane/*`.

### Migration strategy

Strangler fig by screen: React replaces Twig routes as each screen ships. Twig templates are deleted once parity is reached.

## Consequences

**Positive**

- Operator UI shares `@nubitio/ui` and design tokens with the tenant product.
- Backend use cases are unit-testable without HTTP or DBAL in controllers.
- Clear path to extract `nubit/control-plane` package.

**Negative**

- Temporary dual-stack (Twig legacy routes until full parity).
- Session-based API requires a dedicated Symfony firewall for `/api/control-plane`.

## Rejected alternatives

- **Twig redesign only** — faster short-term, does not fix backend debt or stack fragmentation.
- **Incremental facade over TenantManager** — preserves god-class internals; rejected in favour of aggressive rewrite.

## Amendment (2026-08-19) — implementation confirmed

Unlike ADR-004 (its CashRegister counterpart, which mostly did not ship — see that ADR's amendment), this rewrite is done:

- `TenantManager` no longer exists anywhere in `src/`.
- `src/ControlPlane/Tenant/` now has `Domain/` (repository interfaces, `TenantSnapshot`, `TenantStatus`), `Application/` (`TenantService.php`, 264 lines; `TenantRegistry.php`, 32 lines), `Infrastructure/`, and `Controller/` — the DAIA split this ADR called for.
- `TenantController.php` is 42 lines (thin, delegates to `TenantService`) — down from the ~990 lines this ADR cited as the problem.
- No `tenant/edit.html.twig` remains in `templates/`.
- `assets/control-plane.tsx` and `assets/features/control-plane/` exist; `/api/control-plane/*` has its own firewall (`control_plane_api`, `ROLE_CP_VIEWER`) in `config/packages/security.yaml`, separate from the tenant app's session context.

This is the one place in the codebase where a DAIA-layered rewrite (as ADR-002 originally mandated everywhere) actually happened — because it had a dedicated ADR and effort behind it. See ADR-006 for why that's now the pattern for *when* to use DAIA, not ADR-002's blanket mandate.

The only `/admin`-related Twig template remaining is `templates/admin/spa.html.twig` — the SPA shell itself, plus the server-rendered login screen this ADR always intended to keep. No per-screen legacy Twig templates remain: the strangler-fig migration reached full parity. Not re-verified in this pass: whether YAML fallback (`TENANTS_SOURCE=yaml`) still works for local dev.