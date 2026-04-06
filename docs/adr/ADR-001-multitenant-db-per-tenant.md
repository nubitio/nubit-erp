# ADR-001 — Multi-Tenancy Strategy: Database-per-Tenant with Subdomain Resolution

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact is a multi-tenant SaaS POS platform serving Peruvian SMBs. Each tenant is an independent business (a store, pharmacy, restaurant, or distributor) that must be fully isolated from all other tenants — both in data and in behaviour.

**Requirements driving the decision:**

1. **Data isolation** — A tenant's sales, customers, inventory, and SUNAT credentials must never be readable by another tenant. A bug, query mistake, or missing `WHERE tenant_id =` clause must not leak data across tenants.
2. **Schema evolution** — Each tenant's database schema may evolve over time via Doctrine Migrations. Migrations must be runnable per-tenant independently (e.g. during incremental rollout).
3. **Performance** — High-volume tenants (a busy restaurant with 500 sales/day) must not degrade the experience of low-volume tenants sharing the same tables.
4. **SUNAT compliance** — Tax documents, voucher series, and company fiscal data are stored per tenant and must not mingle.
5. **Simplicity of queries** — Application queries must not carry a mandatory `tenant_id` filter on every table. Missing a filter must be a compile-time or structural impossibility, not a runtime oversight.

**Considered alternatives:**

| Strategy | Description |
|----------|-------------|
| **Shared database, shared schema (discriminator column)** | All tenants in one DB; every table has a `tenant_id` FK; application filters on every query |
| **Shared database, separate schemas** | One DB server; each tenant has its own MariaDB schema (database) but shares the server connection pool |
| **Database-per-tenant** | Each tenant has a fully isolated MariaDB database; connection switched at runtime |

**Context of the existing codebase at decision time:**

- The application was already using Doctrine ORM with a `tenant` DBAL connection.
- A `TenantConnection` service wrapping the DBAL connection existed and was being used to switch the active database at request time.
- The control plane (tenant registry, billing) already lived in a separate `control_plane` connection.
- 40+ tenant Doctrine migrations and 20+ control plane migrations had been written independently, confirming the two-database model was already established.

---

## Decision

**Use a dedicated MariaDB database per tenant.** The active tenant is resolved from the HTTP `Host` header (subdomain) on every request by `TenantListener` (Symfony event listener, priority 1024). The `TenantConnection` service switches the DBAL connection to the tenant's database before any business logic runs.

The **control plane** (tenant registry, subscription billing, provisioning) lives in a completely separate `control_plane` database, never accessible from tenant-scoped code paths.

---

## Implementation Details

### Tenant Resolution

```
HTTP Request → Host: acme.efact.pe
  → TenantListener::onKernelRequest()
  → TenantRepository::findBySubdomain('acme')
  → TenantConnection::switchTo($tenantDbName)
  → All subsequent Doctrine queries use the tenant DB
```

`TenantListener` runs at priority 1024 (before firewall, before controllers) and throws a `TenantNotFoundException` (→ 404) if the subdomain is not in the `tenants` table.

### Bypassed Routes

The following routes bypass tenant resolution and connect directly to the control plane:
- `/admin/**` — control plane admin panel
- `/register/**` — self-service tenant onboarding
- `/webhook/paypal`, `/webhook/mercadopago` — subscription webhooks

### JWT Secondary Validation

JWT tokens encode `tenantName`. On every authenticated request, the `JWTAuthenticator` validates that the token's `tenantName` matches the host-resolved tenant, preventing token reuse across tenant boundaries.

### Database Connection Config (`doctrine.yaml`)

```yaml
doctrine:
  dbal:
    connections:
      tenant:
        driver: pdo_mysql
        # DSN parameters populated at runtime by TenantConnection
      control_plane:
        url: '%env(DATABASE_CONTROL_PLANE_URL)%'
    default_connection: tenant
  orm:
    entity_managers:
      default:
        connection: tenant
      control_plane:
        connection: control_plane
```

### Migration Paths

- `migrations/` — 40+ tenant schema migrations (run once per tenant database)
- `migrations_control_plane/` — 20+ control plane migrations (run once on the shared control plane DB)

### Provisioning

`TenantProvisioningService` creates a new MariaDB database, runs all tenant migrations, and seeds default data (admin user, branch, warehouse, voucher series, payment methods, SUNAT catalogs) as part of the self-service registration flow.

---

## Consequences

### Positive

- **Zero cross-tenant leakage by construction** — No `tenant_id` column exists; there is nothing to forget to filter.
- **Independent schema evolution** — Tenant migrations can be rolled out incrementally; a breaking migration on one tenant does not affect others.
- **Performance isolation** — A slow query on one tenant's large `sales` table does not lock other tenants.
- **Simple queries** — All ORM queries are written without tenant discriminators; readability is higher.
- **Easy tenant backup/restore** — A tenant's entire data is a single database dump; point-in-time restore is per-tenant.
- **SUNAT data isolation** — Tax credentials, certificates, and voucher series are per-database; no risk of cross-tenant invoice emission.

### Negative

- **Connection overhead** — Each request requires a connection switch. MariaDB connection pools must be sized accordingly.
- **Schema drift risk** — If a tenant migration fails, that tenant's schema may diverge from others. The provisioning service and migration runner must handle failures gracefully.
- **Operational complexity** — Adding a new tenant requires creating a database, running migrations, and seeding data — automated by `TenantProvisioningService` but more complex than inserting a row.
- **Cross-tenant analytics** — Platform-level aggregate reporting (e.g. total sales across all tenants) requires either a data warehouse ETL or querying each tenant DB individually.

---

## Related ADRs

- ADR-002 — Dual-database architecture (tenant + control plane)
- ADR-003 — Custom JWT auth and branch context encoding
PRDEOF
echo "ADR-001 written"