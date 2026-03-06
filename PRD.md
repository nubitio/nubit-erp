# efact — Product Requirements Document

> **Status:** active
> _North-star document for all agents and contributors. Every spec must align with the Vision, Goals, and Architecture Principles below._

## Vision

eFact is a multi-tenant SaaS platform that combines a full-featured **Point-of-Sale (POS)** system with **SUNAT-compliant electronic invoicing** for the Peruvian market. It enables retail businesses — stores, pharmacies, restaurants, and distributors — to ring up sales, manage inventory, and emit legally valid electronic tax documents (facturas, boletas, notas de crédito, bajas, resúmenes) in one unified workflow.

**Problem it solves:** Peruvian SMBs are legally required to emit SUNAT-compliant e-invoices in real time. Most off-the-shelf POS solutions either lack SUNAT integration or require expensive on-premise installations. eFact closes that gap by delivering an affordable, cloud-hosted, offline-capable SaaS POS that handles both the transaction and the tax obligation automatically.

**Who benefits:**
- **Tenant businesses** (stores, pharmacies, restaurants, distributors) gain a single tool for sales, inventory, and SUNAT compliance — replacing fragmented manual workflows.
- **Tenant employees** (cashiers, supervisors) work faster with a streamlined POS that emits invoices without extra steps.
- **Platform operators** (eFact team) grow recurring SaaS revenue by onboarding new tenants self-service with automated provisioning and subscription billing.

## Goals

1. **SUNAT compliance rate ≥ 99.5 %** — At least 99.5 % of emitted tax documents (facturas, boletas) are accepted by SUNAT within 24 hours, measured per tenant per billing cycle.
2. **Offline POS availability** — The POS terminal remains fully operational (sale creation, receipt printing) during internet outages, with outbox items synced and invoiced automatically on reconnection; zero data loss tolerance.
3. **Tenant time-to-first-invoice ≤ 10 minutes** — A new tenant completing self-service onboarding (registration → company setup → first sale with e-invoice) achieves success within 10 minutes from sign-up.

## Non-Goals

- **Non-Peruvian tax regimes** — eFact is purpose-built for SUNAT (Peru). Support for other countries' tax authorities (SAT Mexico, DIAN Colombia, SII Chile, etc.) is explicitly out of scope.
- **Physical hardware manufacturing** — eFact integrates with standard ESC/POS thermal printers and QZ Tray but does not manufacture or certify hardware.
- **Pharmacy lot/expiration-date tracking** — Controlled-substance lot tracking and expiry-date management are not implemented and not planned in the near term.
- **Full ERP accounting module** — eFact covers purchasing, inventory, and sales but does not include double-entry accounting, general ledger, or financial statement generation.
- **Native mobile apps** — The product is a Progressive Web App (PWA); dedicated iOS/Android native apps are out of scope.
- **Multi-country currency arbitrage / FX trading** — Exchange rate sync is provided for display purposes only, not for speculative or multi-currency accounting.

## Users

### Persona 1 — The Cashier (`persona:cashier`)

| Attribute | Detail |
|-----------|--------|
| **Role** | Front-line POS operator at a retail store, pharmacy, or restaurant |
| **Goal** | Complete sales transactions quickly and hand the customer a valid SUNAT receipt without extra steps |
| **Pain point** | Previous tools required separate steps to register the sale and then manually send the invoice to SUNAT, causing queues and errors |
| **Tech comfort** | Basic — uses the POS screen all day; expects a fast, touch-friendly interface |

### Persona 2 — The Store Supervisor / Owner (`persona:supervisor`)

| Attribute | Detail |
|-----------|--------|
| **Role** | Business owner or branch manager overseeing daily operations |
| **Goal** | Monitor sales, control cash register sessions, manage products/prices, and ensure SUNAT compliance without hiring a dedicated accountant |
| **Pain point** | Lacks real-time visibility into sales performance and pending invoice submissions; fears SUNAT penalties |
| **Tech comfort** | Intermediate — comfortable with dashboards and reports; may manage multiple branches |

### Persona 3 — The Platform Operator (`persona:operator`)

| Attribute | Detail |
|-----------|--------|
| **Role** | eFact SaaS team member managing the multi-tenant control plane |
| **Goal** | Provision new tenants, monitor usage/quotas, manage subscription plans, and diagnose tenant issues from the admin panel |
| **Pain point** | Manual tenant provisioning and lack of observability lead to slow onboarding and reactive support |
| **Tech comfort** | High — works with admin UI, CLI commands, and infrastructure tools |

## Tech Stack

### Backend

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.5 |
| Framework | Symfony 7.4 |
| API layer | API Platform 4 (REST + JSON-LD) |
| ORM | Doctrine ORM 3.5 + Doctrine Migrations 4 |
| Server | FrankenPHP (PHP worker mode) + Caddy |
| Async messaging | Symfony Messenger 7.4 (Redis transport) |
| Scheduler | Symfony Scheduler 7.4 |
| Auth | Custom JWT (firebase/php-jwt 6.8) + refresh tokens |
| E-invoicing | greenter/core 5.2 (UBL 2.1 XML), greenter/consulta-cpe 1.1 |
| AI / LLM | symfony/ai-bundle (dev-main), OpenAI gpt-4o-mini |
| MCP | symfony/mcp-bundle (dev-main) |
| File storage | Flysystem + AWS S3 (league/flysystem-aws-s3-v3) |
| Exports | PHPSpreadsheet 5, PHPWord 1.1, WeasyPrint (PDF) |
| Printing | mike42/escpos-php 4.0 (ESC/POS), QZ Tray |
| Observability | OpenTelemetry SDK 1.9 (auto Symfony + Doctrine + Monolog) |
| Math | brick/math 0.12 (exact decimal arithmetic for tax) |

### Frontend

| Layer | Technology |
|-------|-----------|
| Language | TypeScript (strict) |
| Framework | React 18.2 |
| UI components | DevExtreme 25.1 (grids, forms, charts) |
| Styling | TailwindCSS 4, @fontsource/inter, @fontsource/ubuntu |
| Build | Vite 6 + vite-plugin-symfony 8.2 |
| PWA | vite-plugin-pwa 1.1 (Workbox service worker, manifest) |
| Routing | react-router-dom 6 |
| Offline DB | Dexie 4.2 (IndexedDB wrapper) |
| HTTP | Axios 1.8 |
| AI (client) | openai SDK 5.12 |

### Infrastructure

| Layer | Technology |
|-------|-----------|
| Database | MariaDB 11.4 — database-per-tenant + control-plane DB |
| Cache / queue | Redis 6.2 |
| Containers | Docker (FrankenPHP, MariaDB, Redis, OTEL Collector, Playwright) |
| Registry | GitHub Container Registry (GHCR) |
| Deployment | Portainer (3 environments: dev, staging, prod) |
| CI/CD | GitHub Actions (quality gate → build image → deploy) |
| Testing | PHPUnit 12 (backend), Vitest 4 (frontend, 85 % coverage gate), Playwright 1.58 (E2E) |

## Architecture Principles

1. **Database-per-tenant isolation** — Every tenant has its own MariaDB database. No cross-tenant `tenant_id` columns exist in tenant schemas. The `TenantConnection` class switches the DBAL connection dynamically on each request. Specs must never introduce shared tenant tables.

2. **Tenant resolution via HTTP Host** — The `TenantListener` (priority 1024) resolves the tenant from the HTTP `Host` header on every request. JWT tokens carry `tenantName` for secondary validation. Specs that add new entry points must respect this listener or explicitly justify bypass (as done for `/admin`, `/register`, `/webhook/*`).

3. **Offline-first POS** — The POS terminal must remain functional without network connectivity. All sale data is written to IndexedDB first via the outbox pattern; sync to the backend happens asynchronously. Specs touching the POS or sale creation flow must not break offline capability.

4. **SUNAT reliability over throughput** — E-invoice delivery uses a circuit-breaker (rejection streak ≥ 3 pauses sending), retry with exponential back-off, CDR-consult-based recovery, and async Messenger dispatch. Specs modifying the e-invoicing pipeline must preserve these safety mechanisms.

5. **Exact decimal arithmetic** — All monetary and tax calculations must use `brick/math` (PHP) or integer-cent arithmetic (JS) to avoid floating-point rounding errors. Specs must never use native PHP `float` or JS `number` for monetary values.

6. **API-first, SPA shell** — The backend exposes a JSON REST API (API Platform); the frontend is a single-page React app served from a Twig shell (`HomeController`). Specs must define API contracts before UI implementation.

7. **Async by default for heavy work** — Invoice sending, audit logging, tenant provisioning, and exports must be dispatched via Symfony Messenger (Redis) rather than executed synchronously in HTTP requests.

8. **Observability is mandatory** — All significant operations must emit OpenTelemetry spans/logs. Specs adding new background jobs, external calls, or critical paths must include instrumentation requirements.

9. **GrumPHP quality gate is non-negotiable** — Every backend commit must pass php-cs-fixer, phpcs, phpmd, phpstan, and PHPUnit. Frontend commits must pass ESLint, `tsc --noEmit`, and Vitest coverage thresholds. PRs failing these gates must not be merged.

10. **Control-plane separation** — Tenant lifecycle management (provisioning, billing, quotas, backups) lives in a separate control-plane database and admin surface (`/admin`). Specs must not mix tenant business logic with control-plane concerns.

## Acceptance Standards

A spec is **done** when all of the following are true:

- [ ] **AC coverage** — All acceptance criteria listed in the slice spec are verified (manual or automated).
- [ ] **Backend tests pass** — `php bin/phpunit` exits 0; no regressions in existing test suite; new code has unit/integration tests for non-trivial logic.
- [ ] **Frontend tests pass** — `yarn vitest run --coverage` exits 0; coverage thresholds (85 % lines/functions/statements, 80 % branches on covered files) are maintained.
- [ ] **Type safety** — `yarn tsc --noEmit` exits 0 (no TypeScript errors).
- [ ] **GrumPHP gate** — `vendor/bin/grumphp run` passes all checks (cs-fixer, phpcs, phpmd, phpstan, phpunit).
- [ ] **E2E smoke** — The Playwright happy-path spec (`e2e/happy-path.spec.ts`) passes without regression.
- [ ] **No offline regression** — If the spec touches POS or sale creation, the offline outbox flow must still work end-to-end.
- [ ] **SUNAT compliance preserved** — If the spec touches e-invoicing, the circuit-breaker, retry, and CDR-consult recovery mechanisms must be intact and tested.
- [ ] **API contract documented** — Any new or changed endpoint is reflected in the API Platform resource definition (OpenAPI schema auto-generated).
- [ ] **Multi-tenancy safe** — No cross-tenant data leakage is possible; the spec has been reviewed for tenant-isolation correctness.
- [ ] **Artifact registered** — The output artifact is registered in MCP state; task status is `done`.

## Open Questions

1. **PHP coverage target** — `COVERAGE_MIN_PHP` in CI is currently `8.0` (8 %), which functions only as a regression floor. Should this be raised to a meaningful target (e.g., 60 %)? Who owns this decision?

2. **`config/tenant_plans.php` missing file** — `config/services.yaml` references this file but it does not exist. Does any active service consume the `tenant_plans` parameter? Should the file be created (with plan definitions) or the reference removed?

3. **`assets/api/auth.tsx` mock stub** — A hardcoded stub `signIn()` coexists with the real `authService.ts`. Should it be deleted to avoid developer confusion?

4. **`symfony/mcp-bundle` configuration** — `McpBundle` is registered in `bundles.php` but no `config/packages/mcp.yaml` exists. Is the bundle functional without explicit YAML configuration, or does it need to be configured?

5. **Scheduler wiring** — `src/Schedule.php` is registered but empty. Should `SendInvoiceCommand`, `ExchangeRateSyncCommand`, `InvoiceRetryMetricsCommand`, and `SendDailySummaryCommand` be wired as scheduled tasks? What are the desired cron frequencies?

6. **PayPal vs MercadoPago gateways** — Both payment gateways are implemented in `src/Onboarding/Payment/`. Are both active in production? What is the primary gateway for Peruvian tenants?

7. **BOM/recipe explosion in production** — `BomExplosionService` exists in the inventory core. Is it used in live production flows (e.g., restaurant manufacturing), or is it experimental?

8. **Multi-branch vs multi-tenant boundary** — The architecture supports both multi-branch (within a tenant) and multi-tenant (separate databases). Are there plans for tenant-to-tenant data sharing (e.g., franchisor reporting), or is isolation permanent?

9. **Frontend coverage scope** — Vitest coverage currently targets only 2 files (`posHelpers.ts`, `offline/api.ts`). Should coverage be expanded to the full `assets/features/` surface area? What is the timeline?

10. **Lot/expiry tracking for pharmacies** — Confirmed not implemented. Is it on the roadmap? If yes, which slice owns it?
