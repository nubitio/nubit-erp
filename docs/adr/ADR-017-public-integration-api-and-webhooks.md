# ADR-017: Public Integration API — Tenant-Scoped API Keys, `/api/v1`, and Signed Webhooks

Date recorded: 2026-08-30
Status: Accepted (partially delivered — see Consequences)

## Context

Nubit ERP had no machine-to-machine surface. Its only inbound API is the tenant
JWT firewall (`^/api`, ADR-008), resolved by subdomain (ADR-007); its only
webhooks are the subscription-payment callbacks in `src/ControlPlane`
(PayPal / MercadoPago). There was no way for another Nubit system to drive the
ERP as an API, and no way for the ERP to notify anything when a fiscal document
changed state.

The forcing case: **`nubit-control`** (the hosting control plane) sells hosting
and must issue SUNAT documents for its own sales. It already had a client
(`EfactHttpAdapter`, a two-call REST contract) and a spec
(`nubit-control/docs/efact-integration.md`) — but the ERP endpoint it calls did
not exist, so production ran with `EFACT_ADAPTER=memory` (documents stay local,
`app:doctor` fails the deploy gate). No real SUNAT emission from a hosting sale
was possible.

Rather than bolt one endpoint onto `src/Billing`, we treat this as the first
consumer of a **reusable integration layer** every ERP eventually needs.

## Decision

A new bounded context, **`src/Integration`**, owns everything a third party
touches. It translates between a stable external contract (`/api/v1`, versioned,
additive-only) and the internal domain use cases, which stay free to change.

### 1. Authentication — tenant-scoped API keys

`ApiClient` + `ApiKey` live in the **tenant** database. The key is
`nb_<env>_<tenant>_<secret>`; the tenant slug rides in the key so
`ApiKeyAuthenticator` can `switchConnection()` to the right database *before* it
has a table to look the key up in — there is no shared store, and the slug is
the public subdomain, so this leaks nothing. Only a public `prefix` (indexed
selector) and `sha256(key)` are stored — SHA-256, not a password hash, because
the secret is 256 bits of CSPRNG output: nothing to brute-force and it must be
fast on every request. Scopes (`documents:write`, `documents:read`,
`webhooks:manage`) are exposed as roles (`ROLE_SCOPE_*` + a blanket
`ROLE_INTEGRATION`) so `#[IsGranted]` works unchanged. A new `integration_api`
firewall claims `^/api/v1` **before** `main`; `TenantListener` skips `^/api/v1`.

### 2. Fiscal emission without a `Sale`

`POST /api/v1/documents` accepts nubit-control's minimal payload (totals in
minor units, customer identity, caller-supplied series + number — **the ERP
never allocates a correlative for this channel**). `SyntheticVoucherFactory`
builds the `Voucher` the SUNAT pipeline needs directly (one line, 18 % IGV,
cash, no detraction). `BillCreateUseCase` / `BillSendUseCase` gained an optional
pre-built `Voucher` parameter and skip `getVoucherByTaxDocumentId()` when given
one — the only change to `src/Billing`. Intake persists a `TaxDocument` (no
branch, no sale) + an `IntegrationDocument` (idempotent on `external_id`), then
queues `SendIntegrationDocumentMessage`; the handler rebuilds the voucher from
the stored request, calls `BillSendUseCase`, maps greenter's response
(`SunatOutcomeMapper`: CDR `0` / `4000+` = accepted, `2000-3999` = rejected),
and records the terminal outbox event. `GET /api/v1/documents/{reference}`
serves the resource for a polling fallback.

### 3. Outbound webhooks — transactional outbox, HMAC, bounded retries

`IntegrationEvent` is a transactional outbox: the row is written in the *same
transaction* as the domain transition it describes, so a crash between "document
accepted" and "webhook sent" loses nothing. `WebhookFanout` drains pending
events into one `WebhookDelivery` per enabled `WebhookEndpoint` subscribed to
the event's type (`document.*` patterns). `WebhookDeliveryAttempt` POSTs with a
10 s timeout and a Stripe-style signature
(`Nb-Signature: t=..,v1=hmac_sha256(secret, "<t>.<body>")`, 300 s tolerance);
2xx is delivered, anything else backs off through `RetrySchedule`
(60 s → 24 h over 8 tries) then dead-letters and, after 20 consecutive
dead-letters, auto-disables the endpoint. `app:integration:webhooks:tick` runs
the whole pipeline per tenant, wired into `Schedule.php` every minute;
`app:integration:webhooks:replay` requeues an event.
`GET/POST/DELETE /api/v1/webhook-endpoints` (+ `rotate-secret`) is self-service
management behind `webhooks:manage`.

### Event catalog (the `v1` contract)

`document.accepted`, `document.rejected`, `document.voided`. Envelope:
`{ id, type, api_version: "v1", created_at, data }` where `data` carries
`external_id`, `reference`, `status`, `sunat_status`, `series`, `number`,
`cdr_reference`, `error`.

## Consequences

**Positive**

- One versioned surface (`/api/v1`) for every future integration; `Billing` /
  `Efact` only expose use cases, `Integration` orchestrates them.
- The outbox makes delivery crash-safe; the receiver side (nubit-control) only
  has to be idempotent (by `Nb-Event-Id`) and monotonic (terminal states don't
  regress) — which it is.
- `nubit-control` can move off `EFACT_ADAPTER=memory` the moment the tenant
  below is provisioned.

**Pending / not yet live**

- The **`nubit` tenant** must be provisioned: a `Company` with Nubit's RUC, a
  SUNAT **beta** digital certificate, and the series authorised for this
  channel. Until then the path is wired and unit-tested against greenter's
  models, but no real CDR has been produced.
- Webhook `secret` is stored in cleartext in the tenant DB. Acceptable for MVP
  (same trust boundary as the tenant's own data); encrypt-at-rest is follow-up.
- Delivery runs inside the per-minute `tick` command, not a dedicated Messenger
  transport. Fine at expected volume; revisit if a tenant fans out thousands of
  events per tick.
- No React management screen yet — endpoints are managed via the `/api/v1`
  routes or `app:integration:webhook-endpoint:create`.

**Negative**

- Two more sets of tables per tenant DB (`integration_api_*`,
  `integration_document`, `integration_event`, `integration_webhook_*`) and a
  per-minute scheduled command.
- `src/Billing`'s `BillCreateUseCase` now has two entry shapes (with / without a
  supplied `Voucher`); the synthetic path is only exercised by the integration
  channel.

## Alternatives Considered

- **One endpoint in `src/Billing`, no bounded context.** Rejected: it would mix
  the external contract with the fiscal domain and make `/api/v1` versioning a
  `Billing` concern. The forcing case is narrow but the need is not.
- **Synthesise `sale` / `sale_items` rows so the existing
  `getVoucherByTaxDocumentId()` works.** Rejected: fabricated rows in the sales
  module, visible to reports and every `Sale`-shaped query. The optional-voucher
  seam is smaller and honest.
- **Polling only (no webhooks), keeping nubit-control's `GET /status` loop.**
  Rejected as the primary mechanism — latency and constant load — but kept as
  the fallback, which is the industry norm (Stripe, GitHub).
- **Password-hash the API key.** Rejected: argon2 per request is ~50-100 ms of
  pure overhead for a 256-bit random token with no brute-force surface.
