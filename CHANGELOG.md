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

### Changed
- **Modern SaaS restyle** — neutral near-black dark / off-white light theme tokens (killed the Tailwind-slate blue tint), softer low-contrast borders, flatter shadows (dropped the white inset ring in dark). Retouched the app shell (rounded inset sidebar items instead of full-bleed rows + 3px accent bar, softer header/panel dividers) and the user menu (neutral-grey hover, rounded inset items, roomier dropdown). Grid filter-row operator selector is neutral instead of an accent-tinted pill. Radii widened a step. Theme `--accent-color` fallback aligned to `@nubitio/ui`'s `DEFAULT_ACCENT` (`#1a6fd4`) — the running accent is still the per-tenant setting.
