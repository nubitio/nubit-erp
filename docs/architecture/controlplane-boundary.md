# ControlPlane bounded context boundary

## Status

Draft policy for `SPEC-001`.

## Intent

`src/ControlPlane` owns SaaS control-plane concerns: tenant registration and provisioning, admin users, onboarding, subscription/payment orchestration, plan/quota management, usage collection, backups, and future certificate lifecycle management.

The context should remain inside this repository for now, but be structured so it can later be extracted to a separate package, repository, or service with minimal business-code rewrites.

## Relationship to `Core`

`App\Core` is intended to become a reusable PHP platform library. Therefore:

- `ControlPlane` **may depend on generic Core platform capabilities**: framework integration, tenant context abstractions, generic command bases, DBAL helpers, migrations, messenger primitives, common exceptions, logging/audit primitives, and utility code.
- `Core` **must not depend on concrete ControlPlane classes**. If Core needs tenant, quota, backup, audit, or lifecycle behavior, it must depend on generic Core contracts implemented by the application or by `ControlPlane` adapters.
- Any `Core` code that contains SaaS-specific or ControlPlane-specific business knowledge should be moved out of Core or inverted behind a platform contract.

## Allowed dependencies

### Inside `ControlPlane`

Allowed:

- `App\Core\...` only when the referenced class is platform-level and reusable.
- Symfony, Doctrine, Messenger, HTTP client, logging, payment SDKs, and other infrastructure libraries.
- `App\ControlPlane\...` internal classes within the context.

Preferred for future slices:

- `ControlPlane\...\Port\*` contracts for capabilities implemented by other app modules.
- Adapter classes at the edge when calling tenant-app setup, e-invoicing, lycet, or other bounded contexts.

Adapter-only exceptions:

- `App\Dto\Setup\InitialSetupInput`
- `App\Setup\InitialSetupService`
- `App\Billing\Sunat\Domain\LycetCompanyServiceInterface`

These imports are allowed only under `src/ControlPlane/**/Adapter/` and are guarded by `tests/Architecture/ControlPlaneBoundaryTest.php`.

### Outside `ControlPlane`

Allowed:

- Public ControlPlane contracts/facades/events explicitly marked as public API.

Prohibited long term:

- Imports of concrete services from `App\ControlPlane\Tenant\Service`, `App\ControlPlane\Billing\Service`, `App\ControlPlane\Subscription\Payment`, or other internal implementation namespaces.

## Current inbound coupling from `Core` to `ControlPlane`

These must be inverted first because they block extraction of Core as a platform library:

| File | Current concrete dependency | Target direction |
|---|---|---|
| `src/Core/Audit/AuditLogger.php` | `TenantManager` | Core audit depends on tenant-context/tenant-registry contract |
| `src/Core/EventListener/QuotaEnforcementListener.php` | `TenantQuotaService` | Core listener depends on quota-check contract or moves to ControlPlane |
| `src/Core/MessageHandler/TenantBackupMessageHandler.php` | `TenantBackupService` | Handler moves to ControlPlane or depends on backup contract |
| `src/Core/EventListener/TenantListener.php` | `TenantManager`, `TenantConnectionSwitcher` | Core listener depends on tenant registry + connection switcher contracts |
| `src/Core/Application/Command/TenantAwareCommand.php` | `TenantManager`, `TenantConnectionSwitcher` | Base command depends on platform tenant contracts |
| `src/Core/Messenger/TenantContextMiddleware.php` | `TenantConnectionSwitcher` | Middleware depends on tenant connection switcher contract |

## Current outbound coupling from `ControlPlane` to app modules

These are acceptable as temporary implementation details but should move behind ControlPlane-owned ports:

| Area | Current dependency | Future port |
|---|---|---|
| Tenant setup | `InitialSetupService`, `InitialSetupInput` | `InitialTenantSetupPort` with ControlPlane request DTO |
| Lycet company config | `LycetCompanyServiceInterface` | `ElectronicBillingCompanyPort` |
| Tenant migrations/commands | `TenantAwareCommand`, `TenantMigrationFactory` | Core platform contracts or ControlPlane command adapters |
| Audit/dashboard/binlog helpers | `AuditLogger`, `DashboardService`, `BinlogBackupService` | keep if generic Core; otherwise invert/move |

## Public API rule

Until extraction, public ControlPlane API should be explicit. Classes are public only if located in a future `Contract`, `Port`, `Api`, `Facade`, `Message`, or `Event` namespace and documented as such. Everything else under `ControlPlane` should be treated as internal implementation.

### Current public contracts

ControlPlane-owned ports:

| Contract | Purpose | Current adapter |
|---|---|---|
| `App\ControlPlane\Tenant\Port\InitialTenantSetupPortInterface` | Run tenant initial setup without exposing `App\Setup` DTOs/services to ControlPlane commands | `InitialSetupServiceAdapter` |
| `App\ControlPlane\Tenant\Port\ElectronicBillingCompanyPortInterface` | Register/update/remove an electronic-billing company without exposing lycet/e-invoicing internals to ControlPlane commands | `LycetElectronicBillingCompanyAdapter` |

Core platform contracts implemented by ControlPlane:

| Contract | Implemented by | Consumers |
|---|---|---|
| `App\Core\Platform\Tenant\TenantRegistryInterface` | `TenantManager` | Core tenant resolution/audit/tenant-aware commands |
| `App\Core\Platform\Tenant\TenantConnectionSwitcherInterface` | `TenantConnectionSwitcher` | Core HTTP/messenger/command tenant switching |
| `App\Core\Platform\Tenant\TenantBackupRunnerInterface` | `TenantBackupService` | Async tenant backup handler |
| `App\Core\Platform\Billing\QuotaEnforcerInterface` | `TenantQuotaService` | Quota enforcement listener |

## First implementation phase

1. Add generic Core platform contracts for tenant registry, tenant connection switching, quota checking, and backup handling where needed.
2. Implement those contracts in `ControlPlane` using existing services.
3. Refactor Core classes to depend on the contracts instead of concrete `App\ControlPlane\...` implementations.
4. Add a guard or documented check for prohibited imports.

## Validation

From `efact/`:

```bash
./vendor/bin/phpunit
yarn test
yarn format && yarn lint && yarn build && yarn test
```

Targeted boundary checks:

```bash
rg '^use App\\ControlPlane' src/Core
rg '^use App\\(Setup|Dto\\Setup)' src/ControlPlane/Tenant/Command
docker compose exec -T web ./vendor/bin/phpunit tests/Architecture/ControlPlaneBoundaryTest.php
```
