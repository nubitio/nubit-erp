# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Integration platform (`src/Integration`, ADR-017)** — a reusable machine-to-machine surface, validated by SUNAT emission for `nubit-control`'s own hosting sales:
  - **Tenant-scoped API keys** (`nb_<env>_<tenant>_<secret>`, SHA-256, revocable) behind a new `integration_api` firewall on `^/api/v1`; `app:integration:api-key:create`.
  - **`POST /api/v1/documents`** builds a CPE without a `Sale` (`SyntheticVoucherFactory` + an optional-`Voucher` seam on `BillCreateUseCase`/`BillSendUseCase`), idempotent on `external_id`, dispatched through the existing SUNAT pipeline; `GET /api/v1/documents/{reference}` for polling.
  - **Outbound webhooks** — transactional outbox (`IntegrationEvent`), HMAC signatures (`Nb-Signature`, Stripe-style), per-endpoint retries with dead-letter and auto-disable, `app:integration:webhooks:tick` (scheduled every minute) and `:replay`. Event catalog: `document.accepted` / `rejected` / `voided`.
  - **Settings UI** (Configuración → Integraciones) to manage API keys and webhook endpoints, with one-time secret reveal, a delivery log and manual retry, served by `src/Integration/Controller/Settings/*` under `perm:settings.edit`.
  - Migrations `Version20260830140000`–`160000` (per-tenant: `integration_api_client`/`_key`, `integration_document`, `integration_event`, `integration_webhook_endpoint`/`_delivery`).
- **POS settings** — New `auto_print` tenant setting and a "Punto de venta" section in Configuración → General with typed toggles for **Emitir boleta/factura por defecto** (`autoInvoice`) and **Impresión automática** (`autoPrint`). The POS now honours both as per-tenant defaults; the General tab is reachable by every tenant again (previously hidden for single-store tenants). Migration `Version20260828120000_tenant_settings_auto_print` (default on, no behaviour change for existing tenants).
- **SLICE-CP-02** — Granular role system for the control-plane admin panel: three roles (`ROLE_CP_VIEWER` ⊂ `ROLE_ADMIN` ⊂ `ROLE_SUPER_ADMIN`) with security hierarchy, `#[IsGranted]` guards on all controllers, and Twig `is_granted()` template guards. Destructive tenant actions require `ROLE_SUPER_ADMIN`.
- **SLICE-CP-01** — Login UI for the admin panel: `form_login` replaces `http_basic`, standalone Bootstrap 5.3 login page, CSRF protection, remember-me (7 days), brute-force throttling (5 req/min), and functional logout with session invalidation.
- **SLICE-CP-03** — Admin user management UI (ROLE_SUPER_ADMIN only): full CRUD via `AdminUserController` + `AdminUserRepository` (DBAL), password strength/confirm validation, AJAX active-toggle, and audit log for all operations.

### Fixed
- **Fresh-tenant setup** — `InitialSetupService::configure()` no longer wraps the whole sequence in a manual outer transaction. That forced every nested `$em->flush()` (including `AuditTrailListener`'s second flush in `postFlush`) plus the seeders' own transactions into deep DBAL savepoints, which broke with `SQLSTATE[42000] 1305 SAVEPOINT DOCTRINE_2 does not exist` on a clean tenant DB — blocking `app:tenant:setup`, `app:demo:seed` and `POST /api/test/e2e/seed`. Each unit now manages its own transaction. CI never caught this because it provisions from a baseline dump with `configured = true`.
- **Dashboard — last-7-days chart** — `pickLast7SalesDays` matched sales rows by day-of-month across months, so early in a month it pulled the *current* month's future days (all zero) and emitted duplicate weekday labels, which collapsed the chart's category axis (tooltips read `S/ 0` on the real bar). It now only matches rows in the current month and derives labels from the actual date.

### Changed
- **Dashboard rebuilt on Recharts** — the main dashboard (`/#/dashboard`) is now a fully-custom React + Recharts view (KPI row, 7-day sales bars, 12-month area trend, payment-method horizontal bars, breakdown tables) instead of the declarative `@nubitio/dashboard` config engine. Payment methods are shown as sorted horizontal bars rather than a donut. The "Hoy" block is folded into the dashboard as a slim attention strip (only items needing action), and the onboarding shortcuts only appear while tenant setup is incomplete. The data layer (`GET /api/bi/overview` → `mapEfactDashboardPayload`) is unchanged.
- **Modern SaaS restyle** — neutral near-black dark / off-white light theme tokens (killed the Tailwind-slate blue tint), softer low-contrast borders, flatter shadows (dropped the white inset ring in dark). Retouched the app shell (rounded inset sidebar items instead of full-bleed rows + 3px accent bar, softer header/panel dividers) and the user menu (neutral-grey hover, rounded inset items, roomier dropdown). Grid filter-row operator selector is neutral instead of an accent-tinted pill. Radii widened a step. Theme `--accent-color` fallback aligned to `@nubitio/ui`'s `DEFAULT_ACCENT` (`#1a6fd4`) — the running accent is still the per-tenant setting.

### Removed
- Orphaned legacy dashboard code superseded by the Recharts rebuild: `DashboardHero/Charts/Rankings/StatusBar/Operations`, `useDashboardMetrics`, and the `efactDashboardConfig` declarative config. `@nubitio/react-admin` now has no JS importers in the frontend (only its shared `style.css`).
