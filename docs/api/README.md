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
