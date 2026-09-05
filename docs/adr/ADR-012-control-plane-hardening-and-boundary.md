# ADR-012: ControlPlane Hardening and Platform Boundary

Date recorded: 2026-08-19 (retroactive — documents decisions already live in code, with real gaps noted; see Context)
Status: Accepted — Implemented with known gaps (see Consequences)

## Context

Reconstructed from `docs/PRD.md`'s "ADR-019 a ADR-021: hardening y frontera de ControlPlane" citation (three numbers cited together, treated here as one decision covering role hierarchy/impersonation, the platform-extraction boundary guard, and general hardening — see ADR-006 for the numbering-collision context shared by all reconstructed ADRs).

The ControlPlane (`/admin`, `/api/control-plane/*`) is the operator surface that manages every tenant — it needs both a role/permission model distinct from tenant-side roles, and protection against becoming (or being mistaken for) the seam that the `nubitio/*` package-extraction effort (`docs/architecture/platform-boundary-map.md`) actually cares about.

## Decision

### Role hierarchy and impersonation

`config/packages/security.yaml` defines a role hierarchy `ROLE_CP_VIEWER → ROLE_ADMIN → ROLE_SUPER_ADMIN`, with `ROLE_SUPER_ADMIN` also inheriting `ROLE_ALLOWED_TO_SWITCH`. `/admin/users` requires `ROLE_SUPER_ADMIN`; the rest of `/admin` and all of `/api/control-plane` require at least `ROLE_CP_VIEWER`.

Tenant impersonation does **not** use Symfony's `switch_user` (incompatible with the stateless JWT-per-request, per-tenant-connection firewall — see ADR-008). Instead, `App\ControlPlane\Operator\Api\ImpersonationApiController` is a dedicated endpoint that mints a scoped JWT for the target tenant user directly — an explicit, auditable action rather than a session mutation.

### The platform-boundary guard is not a ControlPlane↔tenant boundary

`scripts/architecture/check-platform-boundary.php` (referenced from `docs/architecture/platform-boundary-map.md`) does **not** protect ControlPlane from the tenant-domain contexts, despite what "frontera de ControlPlane" suggests. It guards code under `src/Platform` and specific `src/Core/*` subfolders (Context, Messenger, Logging, RateLimit, Cache, Filesystem, Http, Exception, Validation, EventListener) — code that's a candidate for extraction into the portable `nubitio/*` packages — from importing `App\ControlPlane\`, `App\Inventory\`, `App\Entity\`, or other app-specific/domain code. It is a boundary for the *extraction effort*, not for ControlPlane's relationship to the rest of the app. **It does not run in CI** — no `.github/workflows/*.yml` references it.

### Hardening measures in place

- **Rate limiting**: `login_throttling` (5 attempts/minute) on the `admin` firewall. No additional dedicated rate limiter for `/api/control-plane/*` beyond that.
- **Security headers**: `docker/frankenphp/Caddyfile` sets `Strict-Transport-Security`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and `Permissions-Policy` (all referencing `SLICE-005 SEC-3` in a comment). **CSP is enforced, not omitted** — this ADR's original text (below, struck through) was wrong about this from the moment it was written: `App\Efact\Web\CspNonceListener` (`src/Efact/Web/CspNonceListener.php`, existing since 2026-04-29, i.e. *before* this ADR's 2026-08-19 recording date) injects a per-request `Content-Security-Policy` header with a nonce for HTML responses (skipped only in `dev`, to keep Vite HMR working), covering `default-src`/`script-src`/`style-src`/`img-src`/`font-src`/`connect-src`/`frame-src`/`worker-src` with app-specific allowances (S3/CDN asset host, Mercure hub, QZ Tray print agent). The Caddyfile's own comment already says this correctly ("Content-Security-Policy is intentionally omitted **here** [at the Caddy/static layer] — PHP (CspNonceListener) emits a per-request CSP") — this ADR's prose just never matched it. ~~CSP is deliberately omitted~~ — the Caddyfile carries an explicit team comment to that effect referencing `SLICE-005 SEC-3`. See [ADR-016](ADR-016-slice-spec-traceability-ids-not-in-repo.md): `SLICE-*` is a traceability ID into a planning process that never got committed to this repo, so the *why* behind the split between Caddy-level and PHP-level headers specifically is not recoverable from the codebase — only that `SLICE-005` was a real, tested security-hardening slice (confirmed via `tests/Identity/Security/AccessControlTest.php`), not an unexplained gap someone forgot to justify.
- **Audit log**: `App\ControlPlane\Operator\Api\AuditLogApiController` exposes an operator action audit log (visible in `docs/ui-audit/screenshots/05-audit-log.png`).

## Consequences

**Positive**
- Impersonation is a first-class, auditable operation rather than a session hack — matches the auditability a SaaS operator surface needs when acting on behalf of a customer.
- The platform-boundary guard, though not what its ADR citation implied, does real work protecting the `nubitio/*` extraction effort from silent regressions — worth keeping, just needs correct framing.

**Negative**
- **The platform-boundary guard not running in CI means it can silently rot** — a PR can introduce exactly the import it's meant to catch and nothing will flag it until someone runs the script manually.
- ~~**No CSP** is a real, currently-accepted gap for an operator panel that manages every tenant~~ **Corrected 2026-09-04**: this was never true — see the corrected "Security headers" bullet in Decision above. CSP has been enforced application-side since 2026-04-29, months before this ADR was even recorded.
- No dedicated rate limiting beyond login throttling for `/api/control-plane/*` — an authenticated-but-compromised low-privilege (`ROLE_CP_VIEWER`) session has no additional throttle on API calls beyond whatever the login itself was rate-limited to reach.

## Alternatives Considered

**`switch_user` for impersonation** — rejected; incompatible with the firewall model (see ADR-008's Alternatives).

**Running the platform-boundary check as a pure local/manual tool by design** — plausible but undocumented; if that was the intent, it should say so explicitly rather than leaving CI-absence looking like an oversight. This ADR does not resolve which it is.
