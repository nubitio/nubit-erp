# ADR-007: Database-per-Tenant Isolation with a Split Control-Plane Connection

Date recorded: 2026-08-19 (retroactive — documents a decision already live in code; see Context)
Status: Accepted — Implemented

## Context

This ADR was reconstructed from `docs/PRD.md`'s "Decisiones vigentes" list, which cites "ADR-001: database-per-tenant" and "ADR-002: base tenant + base control plane" — numbers that collide with the unrelated `docs/adr/ADR-001` (bounded contexts) and `ADR-002` (superseded DAIA layering, see ADR-006). Those two PRD citations were never written up as standalone documents anywhere in the repo. This ADR merges them, since they're two facets of the same infrastructure decision: how tenant isolation is achieved, and how the control-plane database is wired alongside it.

eFact is multi-tenant SaaS: every tenant's data (sales, inventory, fiscal documents) must be strictly isolated from every other tenant, while a separate control-plane database tracks the tenant registry, plans, and subscriptions across all of them.

## Decision

### Isolation: database-per-tenant, not row-level or schema-level

Each tenant gets its own physical database (not a shared database with a `tenant_id` column, and not a Postgres/MariaDB schema-per-tenant split). No tenant-scoped table carries a `tenant_id` column — isolation is enforced by which physical database the connection is pointed at, not by a `WHERE` clause that could be forgotten in a query.

`App\Efact\Doctrine\DBAL\TenantConnection` (a DBAL wrapper class) resolves the current tenant from the request (via `TenantListener`, host/subdomain-based — see `CONTEXT.md`) and swaps the connection's `dbname` per request before any query executes.

### Two named DBAL connections, one ORM mapping

`config/packages/doctrine.yaml` defines two DBAL connections:

- **`tenant`** (the default connection) — uses `DATABASE_URL`, wrapped by `TenantConnection` so its `dbname` changes per request.
- **`control_plane`** — a fixed connection to `CONTROL_PLANE_DATABASE_URL`, never swapped.

There is **one** ORM entity mapping (`App`) shared by both — not two separate EntityManagers with duplicated mapping config. Code that needs the control-plane database asks for it explicitly via `ConnectionRegistry::getConnection('control_plane')` (confirmed in `src/ControlPlane/Tenant/Command/*.php`, `src/Efact/Audit/AuditLogRepository.php`, `src/Efact/Migrations/ControlPlaneMigrationFactory.php`, and others). Everything else — ordinary Doctrine entity persistence — implicitly uses whichever `tenant` connection is active for the current request.

### Separate migration trees

`migrations/` (tenant schema) and `migrations_control_plane/` (control-plane schema) are independent Doctrine Migrations trees, each with its own factory/command (`ControlPlaneMigrationFactory`, `ControlPlaneMigrateCommand`). Running tenant migrations never touches the control-plane schema and vice versa — `make migrate` runs both explicitly (see `README.md`).

## Consequences

**Positive**
- Strongest practical tenant isolation short of separate physical servers — a bug that forgets a `WHERE tenant_id = ?` clause simply cannot leak data, because there is no such column to forget.
- Backups, restores, and per-tenant maintenance operate at the database level, which maps directly onto standard `mariadb-dump`/restore tooling (see `BACKUP_DATABASE_URL` in `README.md`).
- Control-plane data (tenant registry, plans, subscriptions) can never be accidentally queried through a tenant-scoped repository, since it lives on a connection ordinary entity code never touches.

**Negative**
- Cross-tenant reporting/analytics (if ever needed) requires iterating connections rather than a single aggregate query — there is no way to `JOIN` across tenants.
- Every new tenant is a new physical database — provisioning, migrating, and backing up N tenants is N times the operational surface, not a single shared-schema operation.
- Code that needs both connections in the same request (rare, but real — e.g. control-plane commands that also touch a specific tenant) must be deliberate about which connection it's using; a wrong implicit assumption fails loudly (wrong tenant resolved or connection not swapped) rather than silently.

## Alternatives Considered

**Shared database with `tenant_id` column (row-level isolation)** — rejected. Requires disciplined `WHERE tenant_id = ?` on every query and every index; one missed filter is a cross-tenant data leak. Database-per-tenant makes that class of bug structurally impossible.

**Schema-per-tenant** (one database, N schemas) — rejected in practice, though `CONTEXT.md` used to describe the system this way before a 2026-08-19 correction. Database-per-tenant was chosen instead — full connection-level isolation rather than a `search_path`/schema switch within one database.

**Duplicated ORM mapping per connection** — rejected. A single `App` mapping shared by both named connections avoids maintaining two parallel sets of entity metadata for what is, for every entity except the small ControlPlane set, mutually exclusive usage.
