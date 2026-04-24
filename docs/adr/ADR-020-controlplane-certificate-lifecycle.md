# ADR-020 — ControlPlane ownership of SUNAT certificate lifecycle

## Status

Accepted

## Date

2026-04-24

---

## Context

SUNAT electronic invoicing requires each company/RUC to have a valid digital certificate. The lycet service stores the certificate PEM and uses it for signing, but historically it did not keep operational metadata such as expiration date, fingerprint, revocation state, or alert history.

Without metadata in the SaaS control plane, tenants can discover certificate expiration only after invoice sending starts failing.

---

## Decision

ControlPlane owns certificate lifecycle metadata and operations. Lycet remains the storage/signing backend for PEM material.

ControlPlane persists certificate metadata in the control-plane database, including:

- tenant/RUC ownership
- subject and issuer metadata
- serial number
- SHA-256 fingerprint
- validity window
- status
- upload/revocation audit fields
- alert deduplication timestamp

ControlPlane obtains metadata by parsing the uploaded PEM using `openssl_x509_parse()` and sends the PEM to lycet through `ElectronicBillingCompanyPortInterface`.

Status precedence:

1. `revoked` and `superseded` are explicit terminal states.
2. Otherwise, an expired certificate is `expired`.
3. A certificate expiring within 30 days is `expiring_soon`.
4. All other current certificates are `active`.

Alerts are sent to tenant admins through both channels:

- email, using admin usernames when they are valid email addresses
- in-app notifications stored in the tenant database and best-effort Mercure publication

---

## Consequences

### Positive

- ControlPlane can report and alert on certificate health without reading lycet files.
- Certificate renewal and revocation are auditable.
- The design remains compatible with future extraction of ControlPlane because lycet is behind a port.

### Negative / Risks

- Email delivery depends on tenant admin usernames being valid email addresses unless a dedicated email field is introduced later.
- Mercure publication is best-effort; in-app persistence remains the source of truth.
- Lycet update happens after ControlPlane metadata transaction to avoid holding DB locks during network IO, so future work may need reconciliation for rare lycet failures.

### Follow-up

- Add richer admin UI detail/history screens.
- Add optional metadata backfill for certificates already present in lycet.
- Add a dedicated tenant admin email field if usernames are not reliable emails.
