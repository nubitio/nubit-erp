# SPEC-004 final Platform boundary review

## Recommendation

`SPEC-004` can be considered **implementation-complete internally** for the current monolith boundary-refinement goal. The Platform boundary is now documented, guarded by static checks, covered by focused regression tests, and wired through transitional Symfony aliases without breaking the existing `App\Core` API.

This is **not yet Composer-extraction complete**. The remaining work is package-shaping and migration follow-up: final package namespace, package-local tests/metadata, Symfony bridge split, and the deprecation/removal timeline for compatibility shims.

## 1. Implemented boundary changes, TASK-013 through TASK-024

- **TASK-013 — boundary inventory**: documented the current `src/Core` surface, classified Platform core candidates, Symfony bridge/adapters, app-owned adapters, shared utility candidates, transitional compatibility, and package exclusions in `docs/architecture/platform-boundary-map.md`.
- **TASK-014 — dependency guard**: added `composer check:platform-boundary` and a self-test mode to prevent Platform candidate paths from importing concrete efact namespaces.
- **TASK-015 — Platform contracts/layout**: introduced `src/Platform` contracts and models for tenant and quota capabilities, while keeping legacy `App\Core\Platform\...` interfaces compatible.
- **TASK-016 — safe reusable promotions**: promoted low-risk capabilities into `App\Platform\...` with legacy `App\Core\...` compatibility for tenant context, messenger stamps/middleware, logging, cache, filesystem, rate limit, HTTP responses, exceptions, validation, and tenant-aware console command support.
- **TASK-017 — quota decoupling**: removed concrete efact entity mapping from `QuotaEnforcementListener`; resource mapping now lives behind `QuotaResourceResolverInterface` in an app-owned ControlPlane adapter.
- **TASK-018 — auth isolation**: documented current auth as efact app-owned and prevented `src/Platform` from importing transitional auth namespaces.
- **TASK-019 — constants isolation**: moved efact domain/config constants to `App\Efact\Config\EfactConstants`; `App\Core\Util\Constants` remains a deprecated compatibility facade.
- **TASK-020 — Doctrine/app policy isolation**: moved efact Doctrine filters and tenant connection policy to `src/Efact/Doctrine`; legacy `App\Core\Doctrine\...` classes are compatibility shims only.
- **TASK-021 — Symfony bindings**: added transitional DI aliases so `App\Platform\...` and legacy `App\Core\...` contracts resolve to the same runtime services.
- **TASK-022 — regression tests**: added focused `tests/Platform` coverage for promoted Platform behavior and compatibility-sensitive infrastructure.
- **TASK-023 — guard/adapters tests**: verified dependency guard negative cases and that app-owned adapters remain outside the package-candidate scan.
- **TASK-024 — Composer readiness**: documented future package candidates, exclusions, bridge options, migration phases, namespace/package alternatives, and extraction blockers in `docs/architecture/platform-composer-readiness-review.md`.

## 2. Files and areas changed by category

### Platform core

- `src/Platform/Tenant/Contract/*`
- `src/Platform/Tenant/Model/TenantDescriptor.php`
- `src/Platform/Tenant/Context/TenantContext.php`
- `src/Platform/Quota/Contract/*`
- `src/Platform/Cache/CacheManager.php`
- `src/Platform/Filesystem/FileManager.php`
- `src/Platform/RateLimit/RateLimitResult.php`
- `src/Platform/Exception/*`

### Symfony bridge / framework adapters

- `src/Platform/Symfony/Console/TenantAwareCommand.php`
- `src/Platform/Messenger/*Middleware.php`
- `src/Platform/Messenger/*Stamp.php` while Messenger placement remains under review
- `src/Platform/Observability/Logging/TenantLogProcessor.php`
- `src/Platform/Validation/Validator.php`
- `src/Platform/Http/ApiResponse.php`
- `src/Core/EventListener/QuotaEnforcementListener.php` as transitional bridge code depending on Platform ports

### App-owned adapters and exclusions

- `src/ControlPlane/Billing/Quota/EfactEntityQuotaResourceResolver.php`
- `src/Efact/Config/EfactConstants.php`
- `src/Efact/Doctrine/DBAL/TenantConnection.php`
- `src/Efact/Doctrine/Filter/{BranchFilter,DevExtremeFilter,SoftDeleteFilter}.php`
- Existing app-owned auth remains excluded in `src/Core/Security/Auth/*`, `src/Core/Symfony/Security/SecurityService.php`, and `src/Core/Application/Session/Security.php`.

### Backward-compatibility shims

- `src/Core/Context/TenantContext.php`
- `src/Core/Platform/Tenant/*`
- `src/Core/Platform/Billing/QuotaEnforcerInterface.php`
- `src/Core/Cache/CacheManager.php`
- `src/Core/Filesystem/FileManager.php`
- `src/Core/Logging/TenantLogProcessor.php`
- `src/Core/Application/Command/TenantAwareCommand.php`
- `src/Core/Util/Constants.php`
- `src/Core/Doctrine/*`

### Docs, tests, and tooling

- `docs/architecture/platform-boundary-map.md`
- `docs/architecture/platform-composer-readiness-review.md`
- `docs/architecture/auth-boundary.md`
- `docs/architecture/spec-004-final-boundary-review.md`
- `scripts/architecture/check-platform-boundary.php`
- `composer.json` scripts: `check:platform-boundary`, `test:platform-boundary-guard`
- `tests/Platform/*`
- `tests/Core/EventListener/QuotaEnforcementListenerTest.php`

## 3. Validation evidence

All final validations were run from `/Users/johan/Projects/efact-project/efact` on 2026-04-25.

| Command | Result | Evidence |
|---|---:|---|
| `docker compose exec -T web composer check:platform-boundary` | PASS | `Platform boundary check passed (56 PHP files scanned).` |
| `docker compose exec -T web composer test:platform-boundary-guard` | PASS | `Platform boundary guard self-test passed.` |
| `docker compose exec -T web ./vendor/bin/phpunit tests/Platform tests/Core/EventListener/QuotaEnforcementListenerTest.php` | PASS | `OK (22 tests, 103 assertions)` |
| `docker compose exec -T web php bin/console lint:container` | PASS | `The container was linted successfully` |
| `docker compose exec -T web php bin/console doctrine:mapping:info` | PASS | Found 46 mapped entities; command exited successfully. |
| `docker compose exec -T web php bin/console list app:tenant` | PASS | Tenant commands are discoverable, including `app:tenant:health`. |
| `docker compose exec -T web php bin/console app:tenant:health --no-interaction` | PARTIAL / ENV DATA FAILURE | Tenant switching path reached the tenant DB health checks for `demo`; control plane and connection passed, but schema failed with `Missing tables: user, branch`. No destructive command was run. |

The optional smoke result is treated as environment/data evidence, not a SPEC-004 boundary regression: it proves the read-only tenant health command could resolve tenant metadata and connect, but the current local `demo` tenant schema is incomplete.

## 4. Acceptance criteria checklist

| AC | Status | Notes |
|---|---|---|
| AC-1 | PASS | `platform-boundary-map.md` inventories and classifies `src/Core` and package candidates. |
| AC-2 | PASS | Static Platform dependency guard exists and passes on the current tree. |
| AC-3 | PASS | `src/Platform` tenant/quota contracts and typed tenant model are in place. |
| AC-4 | PASS | Safe reusable infrastructure capabilities were promoted behind `App\Platform\...` namespaces. |
| AC-5 | PASS | Legacy `App\Core\...` compatibility remains via shims, inheritance, or aliases. |
| AC-6 | PASS | Quota enforcement no longer maps concrete efact entities inside the Platform-facing listener. |
| AC-7 | PASS | Concrete quota resource mapping is isolated in the app-owned ControlPlane adapter. |
| AC-8 | PASS | Efact auth is documented as excluded and blocked from `src/Platform` imports by the guard. |
| AC-9 | PASS | Efact constants moved to app-owned config with deprecated Core facade retained. |
| AC-10 | PASS | Doctrine filters and tenant connection policy are app-owned under `src/Efact/Doctrine`; Doctrine config points to those implementations. |
| AC-11 | PASS | Symfony DI aliases/wiring validate with `lint:container`. |
| AC-12 | PASS for readiness, PARTIAL for extraction | Composer-readiness review exists; actual package metadata, package namespace rename, and package-local CI remain future work. |
| AC-13 | PASS | Focused Platform behavior regression tests pass: 22 tests / 103 assertions. |
| AC-14 | PASS | Guard self-test covers prohibited Platform imports and confirms app-owned adapters are outside scanner scope. |
| AC-15 | PASS with residual risks | Final validation evidence is captured here; optional runtime tenant smoke is only partially covered because local tenant schema is incomplete. |

## 5. Residual risks and follow-up work

- **TASK-014 state inconsistency**: `spex task list` still reports `TASK-014` as `in_progress`, although the guard implementation is present and final guard validations pass. This appears to be the known prior state/gate bug noted in the task request.
- **Runtime tenant switch smoke coverage**: a read-only `app:tenant:health` smoke was safe and discoverable, but the local `demo` tenant schema is incomplete (`Missing tables: user, branch`). A fuller runtime smoke should be repeated in an environment with a migrated tenant database.
- **Package namespace decision**: `App\Platform\...` is acceptable internally, but a published Composer package should likely use `Efact\Platform\...` and package name `efact/platform` or `efact/php-platform`.
- **BC shim removal timeline**: `App\Core` shims for Platform classes, `App\Core\Util\Constants`, and `App\Core\Doctrine\...` need an explicit deprecation/removal schedule before extraction.
- **Symfony bridge split**: Messenger, Console, Monolog, Validator, HttpFoundation, and future DI/bundle code need a deliberate bridge package boundary before publishing a portable core package.
- **Package-local test suite**: current tests run in the monolith. Future extraction should add package-local PHPUnit/static checks that do not boot efact app services.
- **Remaining app-specific Core services**: settings, dashboard, company context, logo, exporters, receipt payload, and auth/session code still require migration or explicit exclusion as the package boundary tightens.

## 6. Final conclusion

The SPEC-004 implementation is ready for internal completion/review. The core boundary goals are satisfied with passing primary validation evidence, and remaining items are documented follow-up work for package extraction rather than blockers for the current internal Platform boundary milestone.
