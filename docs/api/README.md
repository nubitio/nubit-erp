# Public API contract

`public-v1.yaml` is the authoritative contract for the tenant-scoped machine-to-machine API. It is intentionally separate from API Platform's internal CRUD documentation: `/api/v1` is the compatibility surface for external clients.

Run `php scripts/api/check-public-contract.php` (or `yarn check:api-contract`) in an environment with the backend dependencies installed. This boots the real Symfony router, validates the OpenAPI structure and security semantics, and compares the `/api/v1` routes with the documented operations. The backend CI job runs the PHP command in the `web` container.

Run `yarn generate:api-types` to generate the public TypeScript types into `assets/api/public-v1.types.generated.ts`; `assets/api/types.generated.ts` is the separate compatibility artifact for API Platform consumers.

## Compatibility policy

- `v1` changes are additive only. Existing fields, response meanings, status codes, authentication requirements, and pagination/error envelopes must remain compatible.
- The public contract uses a tenant-scoped bearer API key for machine clients and a separate tenant JWT scheme for ERP web routes. API-key scopes are declared with `x-required-scopes` because OpenAPI HTTP bearer schemes do not carry OAuth scope arrays; `x-scopes` lists the supported values.
- A breaking change requires an explicit ADR and a new versioned prefix (for example `/api/v2`) plus a migration and sunset plan for the prior version.
- API keys are tenant-scoped bearer keys. `external_id` is the caller-owned idempotency key for document submission; reusing it with a different payload returns `409`. Webhook receivers must deduplicate `Nb-Event-Id` and verify `Nb-Signature` as described in ADR-017.
- Lycet is an implementation dependency of the fiscal pipeline, not a public contract owner. Its adapter may change without changing this document; any provider-induced contract change requires the versioning policy above.

## Credit sales pilot

`POST /api/v1/sales` requires `sales:write`, an active branch assigned to the API client and an `Idempotency-Key`. Send existing branch, warehouse, customer and product IDs; the warehouse must belong to the authorized branch. Quantities and unit prices are decimal strings; `total_minor` is the expected total in céntimos. The server validates prices and the total before confirming the sale. First confirmation returns `201`; identical replay returns `200` with the same `sale_id`. A changed command under the same key returns `409`.

`GET /api/v1/sales/{id}` requires `sales:read` and returns the status, currency, total and outstanding balance of a sale created by the same client in its assigned active branch. Monetary fields are exact decimal strings. An unknown sale or one outside the client's access returns `404`. Provision read access explicitly with `--scope=sales:read --sales-branch=<id>`.

`POST /api/v1/sales/{id}/payments` requires `payments:write` and applies a confirmed PEN bank receipt to a sale owned by the same API client. Treasury requires a bank account mapped to the method and branch. Keep the same `capture_reference` and receipt fields on retry to recover the original payment ID. The caller verifies the receipt before submission. Configure the API client's active branch with `--sales-branch`.

For a verified gateway capture, send `destination: gateway`. Configure the PEN route with `php bin/console sales:gateway-route:set --tenant=<tenant> <branch-id> <method-id> <clearing-account-id> <asset-ledger-code>`. ERP uses the configured accounts and credits customer receivables in the same transaction. A retry must retain the destination. Gateway settlement and fees are separate operations.

`POST /api/v1/sales/{id}/document` requires `sales:read` and `documents:write`. It creates one native voucher for a fully paid sale and durably links it to that sale before the regular `invoice:send` worker runs. Reposting returns the same document and current state. Read that state with `GET /api/v1/sales/{id}/document` using `sales:read` and `documents:read`. The tenant needs fiscal series, SUNAT sending enabled, and the native retry entitlement.

This endpoint only confirms unpaid credit sales in PEN. It reduces available inventory and creates the receivable without recording a cash or bank movement or issuing a SUNAT document. It does not handle reservations for unconfirmed orders, payments, or WooCommerce synchronization. Do not use it as a substitute for an applicable fiscal issuance workflow.
