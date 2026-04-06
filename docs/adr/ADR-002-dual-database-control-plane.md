# ADR-002 — Dual-Database Architecture: Tenant DB + Control Plane DB

## Status

Accepted

## Date

2026-04-02

---

## Context

The eFact platform must manage two fundamentally different concerns:

1. **Tenant business data** — Sales, inventory, customers, invoices, cash sessions. Belongs to the tenant; must be isolated per tenant; evolves with tenant schema migrations.
2. **Platform operations** — Tenant registry, domain management, subscription plans, billing, provisioning status, audit log, backup management. Belongs to eFact as the platform operator; shared across all tenants; must be accessible without resolving a specific tenant.

If both concerns live in the same database:
- Tenant resolution (finding which DB to connect to) creates a chicken-and-egg problem: you need the tenant registry to resolve the tenant, but if the registry is in the tenant DB you can't find the tenant DB.
- Platform operators (admin panel users) need to query the tenant registry without impersonating any tenant.
- Billing, quota enforcement, and provisioning logic must run even when a tenant's database is unreachable.
- Failed message queue entries must persist somewhere even if a tenant DB is down — using the `doctrine://control_plane` transport for the Messenger failed queue achieves this.

---

## Decision

Maintain **two completely separate Doctrine entity managers and DBAL connections**:

| Connection | Name | Entity Manager | Tables |
|------------|------|---------------|--------|
| Tenant business data | `tenant` | `default` (ORM) | All tenant entities (products, sales, tax_documents, etc.) |
| Platform operations | `control_plane` | `control_plane` (ORM) | `tenants`, `tenant_domains`, plans, subscriptions, provisioning, audit log, backups |

The `control_plane` connection uses a fixed DSN (`DATABASE_CONTROL_PLANE_URL` env var). The `tenant` connection DSN is computed at runtime from the resolved tenant record in the control plane.

The Symfony Messenger **failed message transport** is wired to `doctrine://control_plane`, ensuring that failed async jobs (invoice send failures, etc.) are persisted in the control plane DB even if the originating tenant's DB is inaccessible.

---

## Implementation Details

### Entity Manager Selection

PHP code selects the correct entity manager explicitly when it needs control plane data:

```php
// Tenant data — default EM
$this->entityManager->getRepository(SaleRecord::class)->find($id);

// Control plane data — explicit EM
$this->registry->getManager('control_plane')
    ->getRepository(Tenant::class)
    ->findBySubdomain($subdomain);
```

### Messenger Transport

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      async:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'  # Redis
      failed:
        dsn: 'doctrine://control_plane'         # Control plane DB
```

This means a failed invoice-send job is stored in the control plane DB alongside the tenant record, making it recoverable even if the tenant DB has a schema issue.

### Admin Panel

The `/admin` EasyAdmin panel uses `http_basic` authentication against the `control_plane` user table and only accesses control plane entity managers. It never touches tenant databases directly.

### Migration Commands

```bash
# Tenant schema migration (all tenants or one)
php bin/console doctrine:migrations:migrate --em=default

# Control plane migration
php bin/console doctrine:migrations:migrate --em=control_plane \
  --configuration=config/migrations_control_plane.yaml
```

### Control Plane Schema (Key Tables)

From `migrations_control_plane/Version20260210120000.php`:

```sql
tenants (
  id, name, subdomain, db_name, db_host, db_port,
  db_user, db_password_encrypted, status, created_at
)

tenant_domains (
  id, tenant_id, domain, is_primary, verified_at
)

-- Plus: plans, subscriptions, provisioning_jobs, audit_log, backup_jobs
```

---

## Consequences

### Positive

- **Bootstrapping is clean** — `TenantListener` can query the control plane DB to resolve the tenant before any tenant DB connection is established. No circular dependency.
- **Admin panel is always available** — Even if individual tenant databases are down, the control plane admin panel works.
- **Failed queue durability** — Failed async jobs survive tenant DB failures because they are stored in the control plane DB.
- **Billing/quota enforcement is tenant-agnostic** — Subscription checks run against the control plane without needing to connect to any tenant DB.
- **Clear ownership boundary** — Code that touches `control_plane` entity manager is immediately identifiable as platform-level code; accidental cross-contamination is visible in code review.

### Negative

- **Two sets of migrations** — Developers must know which migration set to run for which purpose.
- **Two entity managers in DI** — Services that span both concerns (e.g. `TenantProvisioningService`) must inject both entity managers explicitly.
- **No cross-DB joins** — Business queries cannot JOIN tenant tables with control plane tables. Enrichment (e.g. displaying tenant plan info alongside tenant business data) requires two queries.

---

## Related ADRs

- ADR-001 — Database-per-tenant multi-tenancy strategy
