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

---

## Product Suite

The eFact platform consists of three interconnected products:

| Product | Repo | Purpose |
|---------|------|---------|
| **eFact Backend + Web POS** | `efact` | PHP/Symfony multi-tenant SaaS backend with React web frontend |
| **eFact Android POS** | `efact-app` | Native Kotlin/Compose Android app for tablet-based POS terminals |
| **eFact Marketing Site** | `efact-site` | PHP/Symfony public marketing site with CMS and subscription plan catalog |

---

## Goals

1. **SUNAT compliance rate ≥ 99.5 %** — At least 99.5 % of emitted tax documents (facturas, boletas) are accepted by SUNAT within 24 hours, measured per tenant per billing cycle.
2. **Offline POS availability** — The POS terminal remains fully operational (sale creation, receipt printing) during internet outages, with outbox items synced and invoiced automatically on reconnection; zero data loss tolerance.
3. **Tenant time-to-first-invoice ≤ 10 minutes** — A new tenant completing self-service onboarding (registration → company setup → first sale with e-invoice) achieves success within 10 minutes from sign-up.

---

## Non-Goals

- **Non-Peruvian tax regimes** — eFact is purpose-built for SUNAT (Peru). Support for other countries' tax authorities (SAT Mexico, DIAN Colombia, SII Chile, etc.) is explicitly out of scope.
- **Physical hardware manufacturing** — eFact integrates with standard ESC/POS thermal printers and QZ Tray but does not manufacture or certify hardware.
- **Full ERP accounting module** — eFact covers purchasing, inventory, and sales but does not include double-entry accounting, general ledger, or financial statement generation.
- **Multi-country currency arbitrage / FX trading** — Exchange rate sync is provided for display purposes only, not for speculative or multi-currency accounting.

---

## Users

### Persona 1 — The Cashier (`persona:cashier`)

| Attribute | Detail |
|-----------|--------|
| **Role** | Front-line POS operator at a retail store, pharmacy, or restaurant |
| **Goal** | Complete sales transactions quickly and hand the customer a valid SUNAT receipt without extra steps |
| **Pain point** | Previous tools required separate steps to register the sale and then manually send the invoice to SUNAT, causing queues and errors |
| **Tech comfort** | Basic — uses the POS screen all day; expects a fast, touch-friendly interface |
| **Primary surface** | Android POS app or Web POS |

### Persona 2 — The Store Supervisor / Owner (`persona:supervisor`)

| Attribute | Detail |
|-----------|--------|
| **Role** | Business owner or branch manager overseeing daily operations |
| **Goal** | Monitor sales, control cash register sessions, manage products/prices, and ensure SUNAT compliance without hiring a dedicated accountant |
| **Pain point** | Lacks real-time visibility into sales performance and pending invoice submissions; fears SUNAT penalties |
| **Tech comfort** | Intermediate — comfortable with dashboards and reports; may manage multiple branches |
| **Primary surface** | Web POS (desktop/tablet browser) |

### Persona 3 — The Platform Operator (`persona:operator`)

| Attribute | Detail |
|-----------|--------|
| **Role** | eFact SaaS team member managing the multi-tenant control plane |
| **Goal** | Provision new tenants, monitor usage/quotas, manage subscription plans, and diagnose tenant issues from the admin panel |
| **Pain point** | Manual tenant provisioning and lack of observability lead to slow onboarding and reactive support |
| **Tech comfort** | High — works with admin UI, CLI commands, and infrastructure tools |
| **Primary surface** | `/admin` control plane panel |

### Persona 4 — The Prospective Customer (`persona:prospect`)

| Attribute | Detail |
|-----------|--------|
| **Role** | SMB owner evaluating POS/invoicing solutions |
| **Goal** | Understand eFact's features and pricing; sign up for a trial |
| **Primary surface** | `efact-site` marketing website |

---

## Tech Stack

### Backend (`efact`)

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.5 |
| Framework | Symfony 7.4 |
| API layer | API Platform 4.1 (REST + JSON-LD/Hydra) |
| ORM | Doctrine ORM + Doctrine Migrations |
| Server | FrankenPHP (PHP worker mode, `php-runtime/frankenphp-symfony`) |
| Async messaging | Symfony Messenger 7.4 (Redis transport) |
| Auth | Custom JWT (`firebase/php-jwt`) + HttpOnly cookie + refresh tokens |
| E-invoicing | `greenter/core` 5.x (UBL 2.1 XML, SUNAT web services), `greenter/consulta-cpe` |
| AI / LLM | `symfony/ai-bundle` + OpenAI GPT-4o-mini |
| MCP | `symfony/mcp-bundle` — MCP endpoint at `/mcp` |
| File storage | Flysystem + AWS S3 (`league/flysystem-aws-s3-v3`, `oneup/flysystem-bundle`) |
| Exports | PHPSpreadsheet, PHPWord, WeasyPrint (PDF) |
| Printing | `mike42/escpos-php` (ESC/POS thermal), QZ Tray (browser bridge) |
| Observability | OpenTelemetry SDK (OTLP exporter, auto-instrumentation for Symfony + Doctrine + Monolog) |
| Math | `brick/math` (exact decimal arithmetic for tax calculations) |
| Payments (onboarding) | PayPal + MercadoPago gateways, webhook handlers |

### Frontend (`efact` web)

| Layer | Technology |
|-------|-----------|
| Language | TypeScript (strict) |
| Framework | React 19 |
| UI components | DevExtreme 25.1 (grids, forms, charts) |
| Styling | TailwindCSS 4 |
| Build | Vite 6 + `vite-plugin-symfony` |
| PWA | `vite-plugin-pwa` (Workbox service worker) |
| Routing | React Router 6 |
| Offline DB | Dexie 4 (IndexedDB wrapper) |
| HTTP | Axios |
| State / server cache | React Query (TanStack Query) |
| AI (client) | `openai` SDK |
| Real-time | Mercure (SSE) |
| i18n | i18next |

### Android POS (`efact-app`)

| Layer | Technology |
|-------|-----------|
| Language | Kotlin (JVM 17) |
| UI | Jetpack Compose + Material 3 |
| Architecture | MVVM + Clean Architecture (Domain / Data / Presentation) |
| DI | Koin |
| Network | Retrofit |
| Local DB | Room |
| Async | Coroutines + Flow + StateFlow + Channel |
| Firebase | Crashlytics, Analytics, FCM, Remote Config |
| Observability | OpenTelemetry OTLP exporter |
| Min SDK | 25 (Android 7.1) |

### Marketing Site (`efact-site`)

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2 |
| Framework | Symfony 7.4 |
| API | API Platform 4.2 |
| Admin | EasyAdmin 4 |
| Templates | Twig |
| Auth | LexikJWT + form login |
| File uploads | VichUploader |
| Database | SQLite (default) / PostgreSQL / MySQL |

### Infrastructure

| Layer | Technology |
|-------|-----------|
| Database | MariaDB 11.4 — database-per-tenant + control-plane DB |
| Cache / queue | Redis |
| Real-time push | Mercure hub |
| Containers | Docker (FrankenPHP, MariaDB, Redis, OTEL Collector) |
| CI/CD | GitHub Actions |
| Deployment | Portainer |

---

## Architecture Principles

1. **Database-per-tenant isolation** — Every tenant has its own MariaDB database. No cross-tenant `tenant_id` columns exist in tenant schemas. The `TenantConnection` class switches the DBAL connection dynamically on each request, resolved from the HTTP `Host` header. Specs must never introduce shared tenant tables.

2. **Tenant resolution via HTTP Host** — The `TenantListener` (priority 1024) resolves the tenant from the HTTP `Host` header on every request. JWT tokens carry `tenantName` for secondary validation. Specs that add new entry points must respect this listener or explicitly justify bypass (as done for `/admin`, `/register`, `/webhook/*`).

3. **Offline-first POS** — The POS terminal must remain functional without network connectivity. All sale data is written to IndexedDB first via the outbox pattern; sync to the backend happens asynchronously. Specs touching the POS or sale creation flow must not break offline capability.

4. **SUNAT reliability over throughput** — E-invoice delivery uses a circuit-breaker (rejection streak ≥ 3 pauses sending), retry with exponential back-off, CDR-consult-based recovery, and async Messenger dispatch. Specs modifying the e-invoicing pipeline must preserve these safety mechanisms.

5. **Exact decimal arithmetic** — All monetary and tax calculations must use `brick/math` (PHP) or integer-cent arithmetic (JS) to avoid floating-point rounding errors. Specs must never use native PHP `float` or JS `number` for monetary values.

6. **API-first, SPA shell** — The backend exposes a JSON REST API (API Platform); the frontend is a single-page React app served from a Twig shell. Specs must define API contracts before UI implementation.

7. **Async by default for heavy work** — Invoice sending, audit logging, tenant provisioning, and exports must be dispatched via Symfony Messenger (Redis) rather than executed synchronously in HTTP requests.

8. **Observability is mandatory** — All significant operations must emit OpenTelemetry spans/logs. Specs adding new background jobs, external calls, or critical paths must include instrumentation requirements.

9. **GrumPHP quality gate is non-negotiable** — Every backend commit must pass php-cs-fixer, phpcs, phpmd, phpstan, and PHPUnit. Frontend commits must pass ESLint, `tsc --noEmit`, and Vitest coverage thresholds. PRs failing these gates must not be merged.

10. **Control-plane separation** — Tenant lifecycle management (provisioning, billing, quotas, backups) lives in a separate control-plane database and admin surface (`/admin`). Specs must not mix tenant business logic with control-plane concerns.

---

## Features

### 1. Authentication & Authorization

- **JWT authentication** — Custom JWT stored in HttpOnly cookies; access token TTL configurable; refresh token flow at `POST /api/auth/refresh`.
- **Roles** — `ROLE_USER`, `ROLE_CASHIER`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`, `ROLE_CP_VIEWER`.
- **Branch context** — After login, users select a branch; the selected branch is encoded in the JWT and determines data scope for subsequent requests (`POST /api/auth/select-branch`).
- **Control plane auth** — Separate `http_basic` firewall at `/admin` for platform operators.
- **Android auth** — Same JWT API; tenant base URL configured via tenant setup screen at app startup.

### 2. Point of Sale (POS)

**Web POS:**
- Product search by name, code, SKU, or barcode scan
- Cart management: add/remove items, adjust quantities, apply item-level and total discounts
- Customer selection or creation inline (DNI/RUC lookup via `GET /api/consults/dni/{id}` and `GET /api/consults/ruc/{id}`)
- Multiple payment methods: cash, card, mixed; change calculation
- Sale submission with automatic stock deduction
- Invoice generation on sale (`POST /api/sales/{id}/invoice`): auto-selects BOLETA (8-digit RUC) or FACTURA (11-digit RUC)
- Sale void (`POST /api/sales/{id}/void`)
- Receipt printing: ESC/POS thermal (`GET /api/sales/{id}/print-invoice`) or PDF
- Public receipt URL: `GET /receipt/{id}` (QR code on receipt)
- Offline-first: IndexedDB outbox, automatic sync on reconnect

**Android POS:**
- Same core flow: product picker, cart, customer picker, sale submission
- ESC/POS Bluetooth printer integration
- Room local DB for offline persistence
- Print preview screen before printing
- Cash register session management (open/close)
- Branch picker at login

### 3. Inventory Management

- **Products** — UUID PK, name, code, SKU, barcode, `productKind` (GOOD/SERVICE), `inventoryBehavior` (TRACKED/NOT_TRACKED/COMPOSITE), `lotTrackingStrategy` (NONE/FIFO/FEFO), category, unit of measure, sale price, purchase price, BOM items for composite products.
- **Warehouses** — Per-branch, multiple warehouses supported.
- **Stock movements** — Unified ledger (`stock_movements` table) with types: PURCHASE, SALE, ADJUSTMENT, TRANSFER_IN, TRANSFER_OUT, RECIPE_CONSUMPTION.
- **Read model** — `stock_items` table (qty_on_hand + qty_reserved per warehouse+product) updated atomically on each movement; fallback chain to `stock_balances` (legacy).
- **Idempotency** — All movements are idempotent via SHA-256 idempotency key (source_type + source_id + movement_type + warehouse + product); duplicate movement silently skipped.
- **BOM explosion** — COMPOSITE products explode into component movements via `BomExplosionService`.
- **Lot tracking** — `StockLot` entity tracks lot number, expiration date, manufacture date, qty on hand, cost. FIFO/FEFO deduction via `LotDeductionService`.
- **Unit conversion** — `UnitConversionService` converts between units before recording base-unit movements.
- **Purchases** — `goods_receipts` table; UUID PK; idempotency key + sync_status + local_uuid for offline-first Android purchase sync.

### 4. Electronic Invoicing (SUNAT)

**Supported document types:**

| Code | Type |
|------|------|
| 01 | Factura electrónica |
| 03 | Boleta de venta electrónica |
| 07 | Nota de crédito electrónica |
| 08 | Nota de débito electrónica |
| RA | Resumen diario (boletas) |
| RC | Comunicación de baja |

**Flow:**
1. `POST /api/sales/{id}/invoice` → `InvoiceCreationService` creates `TaxDocument` + `TaxDocumentLine` records; auto-selects series from `VoucherSeries` by branch + document type; generates sequential number with `FOR UPDATE` lock.
2. `POST /api/tax-documents/{id}/validate` → `VoucherHydrator` builds UBL 2.1 XML via `greenter/core`, signs with tenant digital certificate.
3. `POST /api/tax-documents/{id}/send` → async dispatch via Symfony Messenger; sends signed XML to SUNAT web service; stores CDR in S3.
4. `GET /api/tax-documents/{id}/consult` → CDR consult via `greenter/consulta-cpe`; updates `sunatStatus` and `sunatMessage`.
5. `POST /api/tax-documents/{id}/email` → sends receipt PDF + XML to customer.

**Safety mechanisms:**
- Circuit breaker: rejection streak ≥ 3 pauses auto-sending for the series.
- Retry with exponential back-off on transport/service errors.
- Event log: `TaxDocumentEvent` records every state transition (issued, validated, sent, accepted, rejected, consulted).
- CDR path stored in `cdr_path` column for audit trail.

**Detraction support:** `TaxDocument` carries full detraction fields (code, percent, amount, payment method, value reference) for high-value transactions requiring SUNAT detractions.

**Credit notes:** `CreditNoteService` creates `TaxDocument` with `document_type=07`, links to `related_tax_document`, carries `affected_document`, `reason_code`, `reason_description`.

### 5. Cash Register & Session Management

- **Cash registers** — Per-branch, multiple registers supported.
- **Cash sessions** — `CashSession` entity; states: OPEN / RECONCILING / CLOSED; tracks `openingAmount`, `expectedAmount` (computed from sales), `countedAmount` (physical count), `differenceAmount`.
- **Cash movements** — `CashMovement` entity; categorized (CashMovementCategory); income/expense entries within a session.
- **Cash shifts** — Legacy `CashShift` entity maintained for backward compatibility.

### 6. Customers

- RUC (11 digits, business) and DNI (8 digits, individual) supported.
- Real-time SUNAT RUC lookup and RENIEC DNI lookup via `ConsultResource` (`GET /api/consults/ruc/{id}`, `GET /api/consults/dni/{id}`).
- Price list assignment per customer.

### 7. Reporting & BI

- **Dashboard** — Real-time KPIs via `GET /api/bi/{type}`: daily sales total, top products, hourly sales distribution, pending invoices count, stock alerts. Data pushed via Mercure SSE.
- **Excel exports** — `GET /api/sales/report?report={type}&date-from=&date-to=`: 7 report types exported via PHPSpreadsheet.
- **AI assistant** — `POST /api/assistant`: GPT-4o-mini agent with 8 tools:
  - `SaleTools` — query sales data
  - `ProductTools` — query product catalog
  - `CashRegisterTools` — query cash session data
  - `DocumentationTools` — answer eFact usage questions via TF-IDF RAG
  - `ApiReadTool` — generic API read
  - `StockAlertTool` — query low-stock alerts
  - `SunatStatusTool` — query SUNAT document statuses
  - `CustomerIntelligenceTool` — customer analytics

### 8. Multi-Tenant Onboarding

- **Self-service registration flow:**
  1. `GET /register` → registration form (email, business name, subdomain, plan selection)
  2. `GET /register/verify` → email verification
  3. `GET /register/new-tenant` → company setup wizard (RUC, fiscal address, SUNAT credentials)
- **Tenant provisioning** — `TenantProvisioningService` creates isolated MariaDB database, runs tenant migrations, seeds default data (admin user, default branch, warehouse, voucher series, payment methods, SUNAT catalogs).
- **Subscription billing** — PayPal and MercadoPago gateways; webhook handlers at `/webhook/paypal` and `/webhook/mercadopago`; `SubscriptionManager` tracks billing cycle and plan limits.
- **Control plane** — `/admin` EasyAdmin panel for operators: tenant list, domain management, provisioning status, subscription management, backup management, audit log.

### 9. Marketing Site (`efact-site`)

- CMS-managed landing page content: hero, CTA, plans, benefits, FAQs, how-it-works steps, stats, modules.
- Plan catalog with monthly/annual pricing, feature lists, and CTA URLs.
- Public API for plan/feature data consumed by the registration flow.
- EasyAdmin at `/admin` for content editors.

### 10. Real-Time Push

- Mercure SSE hub subscribed to by the web frontend.
- Resources with `mercure: true`: Products, Sales, TaxDocuments, CashSessions, UserNotifications, VoucherSeries, Categories, PaymentMethods, and more.
- User notifications: `UserNotification` entity; types (stock alert, invoice status, etc.), severity levels; `GET /api/user-notifications`, `PATCH /api/user-notifications/{id}/read`, `POST /api/user-notifications/read-all`.

### 11. MCP (Model Context Protocol)

- MCP endpoint at `/mcp` via `symfony/mcp-bundle`.
- Exposes the same AI tools as the assistant endpoint to external MCP clients (Claude Desktop, etc.).

---

## Data Model (Key Entities)

### Tenant Database

| Entity | Table | Key fields |
|--------|-------|-----------|
| `Company` | `empresa` | RUC, businessName, tradeName, fiscalAddress, logo, ubigeoCode, bank info |
| `Branch` | `local` | UUID, name, address, company |
| `Warehouse` | `almacen` | UUID, name, branch |
| `User` | `usuario` | int PK, username, role (1/2/3), deletedAt |
| `CashRegister` | `caja` | UUID, name, branch |
| `CashSession` | `cash_sessions` | cashRegister, status, openingAmount, countedAmount |
| `Product` | `producto` | UUID, code, SKU, barcode, productKind, inventoryBehavior, lotTrackingStrategy |
| `Category` | `categoria` | id, name |
| `Customer` | `cliente` | int PK, ruc, businessName, priceList |
| `SaleRecord` | `sales` | UUID, branch, warehouse, customer, status, grandTotal, payments[] |
| `SaleItem` | `detalle_venta` / `sale_items` | saleRecord, product, quantity, unitPrice |
| `TaxDocument` | `tax_document` | UUID, documentType, series, number, sunatStatus, cdrPath |
| `TaxDocumentLine` | `tax_document_line` | taxDocument, lineNumber, productName, quantity, unitPrice, taxAmount |
| `TaxDocumentEvent` | `tax_document_event` | taxDocument, eventType, status, message |
| `VoucherSeries` | `local_comprobante` | branch, voucherType, series |
| `Purchase` | `goods_receipts` | UUID, supplier, status, idempotencyKey, syncStatus |
| `StockItem` | `stock_items` | warehouse, product, qtyOnHand, qtyReserved (read model) |
| `StockLot` | `stock_lots` | product, warehouse, lotNumber, expirationDate, qtyOnHand |
| `UserNotification` | `user_notification` | userId, type, severity, title, body, readAt |
| `Supplier` | `proveedor` | id, ruc, businessName |
| `PaymentMethod` | `metodo_pago` | id, name |
| `PriceList` | `lista_precio` | id, name, multiplier |
| `UnitOfMeasure` | `unidad_medida` | id, code, name |

### Control Plane Database

| Table | Purpose |
|-------|---------|
| `tenants` | Tenant registry (subdomain, DB name, status) |
| `tenant_domains` | Custom domain mappings |
| Plan/subscription tables | Billing plan definitions, tenant subscriptions |
| Audit log | Control plane action audit trail |
| Backup management | Tenant DB backup scheduling/history |

---

## API Surface

### Authentication
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/login` | JWT login; sets HttpOnly cookie |
| POST | `/api/auth/refresh` | Refresh access token |
| POST | `/api/auth/logout` | Invalidate tokens |
| POST | `/api/auth/select-branch` | Set active branch in JWT |

### Core Resources (API Platform CRUD)
`/api/products`, `/api/sales`, `/api/tax-documents`, `/api/purchases`, `/api/customers`, `/api/users`, `/api/branches`, `/api/warehouses`, `/api/cash-registers`, `/api/cash-sessions`, `/api/categories`, `/api/payment-methods`, `/api/price-lists`, `/api/suppliers`, `/api/voucher-series`, `/api/user-notifications`, `/api/stock-lots`

### Business Operations
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/sales/{id}/invoice` | Generate tax document from sale |
| POST | `/api/sales/{id}/void` | Void a sale |
| GET | `/api/sales/{id}/print-invoice` | ESC/POS print data |
| GET | `/api/tax-documents/{id}/consult` | Consult SUNAT CDR status |
| POST | `/api/tax-documents/{id}/validate` | Build + sign UBL XML |
| POST | `/api/tax-documents/{id}/send` | Send to SUNAT |
| POST | `/api/tax-documents/{id}/email` | Email receipt to customer |
| GET | `/api/consults/ruc/{id}` | SUNAT RUC lookup |
| GET | `/api/consults/dni/{id}` | RENIEC DNI lookup |
| GET | `/api/bi/{type}` | Dashboard BI data |
| GET | `/api/sales/report` | Excel report export |
| POST | `/api/assistant` | AI assistant query |
| GET | `/api/setup` | Tenant setup wizard state |
| POST | `/api/setup` | Tenant setup wizard submission |

### Public Routes
| Method | Path | Description |
|--------|------|-------------|
| GET | `/receipt/{id}` | Public receipt page |
| GET | `/receipt/{id}/ticket` | Ticket receipt |
| GET | `/receipt/{id}/pdf` | PDF receipt |
| GET | `/receipt/{id}/xml` | UBL XML download |
| GET | `/receipt/{id}/cdr` | CDR download |
| GET | `/sale/consult` | Public sale consultation |
| POST | `/webhook/paypal` | PayPal subscription webhook |
| POST | `/webhook/mercadopago` | MercadoPago subscription webhook |

---

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

---

## Open Questions

1. **PHP coverage target** — `COVERAGE_MIN_PHP` in CI is currently `8.0` (8 %), which functions only as a regression floor. Should this be raised to a meaningful target (e.g., 60 %)? Who owns this decision?

2. **`config/tenant_plans.php` missing file** — `config/services.yaml` references this file but it does not exist. Does any active service consume the `tenant_plans` parameter? Should the file be created (with plan definitions) or the reference removed?

3. **`assets/api/auth.tsx` mock stub** — A hardcoded stub `signIn()` coexists with the real `authService.ts`. Should it be deleted to avoid developer confusion?

4. **Scheduler wiring** — `src/Schedule.php` is registered but empty. Should `SendInvoiceCommand`, `ExchangeRateSyncCommand`, `InvoiceRetryMetricsCommand`, and `SendDailySummaryCommand` be wired as scheduled tasks? What are the desired cron frequencies?

5. **PayPal vs MercadoPago gateways** — Both payment gateways are implemented in `src/Onboarding/Payment/`. Are both active in production? What is the primary gateway for Peruvian tenants?

6. **BOM/recipe explosion in production** — `BomExplosionService` exists in the inventory core. Is it used in live production flows (e.g., restaurant manufacturing), or is it experimental?

7. **Multi-branch vs multi-tenant boundary** — The architecture supports both multi-branch (within a tenant) and multi-tenant (separate databases). Are there plans for tenant-to-tenant data sharing (e.g., franchisor reporting), or is isolation permanent?

8. **Lot/expiry tracking for pharmacies** — `StockLot` and `LotDeductionService` exist. Is FEFO/FIFO lot tracking used in production pharmacies today, or still experimental?

9. **Frontend coverage scope** — Vitest coverage currently targets only 2 files (`posHelpers.ts`, `offline/api.ts`). Should coverage be expanded to the full `assets/features/` surface area? What is the timeline?

10. **`symfony/mcp-bundle` configuration** — `McpBundle` is registered in `bundles.php`. Is the bundle functional in production, or experimental? Does it need explicit YAML configuration?
