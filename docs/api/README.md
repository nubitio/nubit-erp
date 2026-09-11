# Public API contract

`public-v1.yaml` is the authoritative contract for the tenant-scoped machine-to-machine API. It is intentionally separate from API Platform's internal CRUD documentation: `/api/v1` is the compatibility surface for external clients.

Run `composer check:api-contract` to parse the contract and compare every custom `/api/v1` controller route with its documented operation. Run `yarn generate:api-types` to generate TypeScript types from the same file; CI fails if generated output is stale.

## Compatibility policy

- `v1` changes are additive only. Existing fields, response meanings, status codes, authentication requirements, and pagination/error envelopes must remain compatible.
- A breaking change requires an explicit ADR and a new versioned prefix (for example `/api/v2`) plus a migration and sunset plan for the prior version.
- API keys are tenant-scoped bearer keys. `external_id` is the caller-owned idempotency key for document submission; reusing it with a different payload returns `409`. Webhook receivers must deduplicate `Nb-Event-Id` and verify `Nb-Signature` as described in ADR-017.
- Lycet is an implementation dependency of the fiscal pipeline, not a public contract owner. Its adapter may change without changing this document; any provider-induced contract change requires the versioning policy above.
