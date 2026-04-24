# ADR-021: ControlPlane operational hardening

**Status**: Accepted
**Date**: 2026-04-24
**Deciders**: adr-writer, sdd-builder
**Related Specs**: SPEC-003
**Supersedes**: None

## Context

SPEC-003 hardens the ControlPlane flows around tenant onboarding, payment webhooks, certificate synchronization, secret handling, and tenant backups. The preceding implementation tasks closed several operational gaps that could otherwise create unrecoverable provisioning states, duplicate payment side effects, inconsistent certificate state between ControlPlane and lycet, accidental secret disclosure, or unauthorized backup reads.

These decisions are lasting architecture choices because they define persistence semantics, retry boundaries, security policy, and integration recovery behavior for ControlPlane rather than one-off implementation details.

## Decision Drivers

- Tenant provisioning starts before a tenant database row exists, so onboarding state must represent pre-tenant subscriptions safely.
- Payment webhooks are delivered at-least-once and must be safe to retry after partial failure.
- Certificate persistence and lycet synchronization cross a local database boundary and a remote signing/storage backend.
- Passwords and process credentials must not be exposed through persisted plaintext, message payloads, logs, or process command arguments.
- Tenant backup downloads must enforce platform-level authorization and tenant ownership before any storage read occurs.
- The chosen patterns should remain compatible with ADR-019's extractable ControlPlane boundary and ADR-020's ControlPlane ownership of certificate lifecycle metadata.

## Considered Options

1. **Targeted operational state and retry semantics** — Model pending and external-sync states explicitly, encrypt sensitive envelopes, pass process secrets through scoped environment variables, and enforce authorization/ownership before backup reads.
2. **Full outbox and saga orchestration** — Introduce a generalized outbox/saga framework for onboarding, payment, certificate, secret, and backup workflows.
3. **Best-effort procedural hardening** — Keep existing schemas and command patterns, add guards in services/controllers, and rely on manual remediation for partial failures.

## Decision Outcome

**Chosen option**: **Targeted operational state and retry semantics**

**Rationale**: This option addresses the concrete failure modes from SPEC-003 with durable state where retries are needed and security controls where data exposure is possible, while avoiding the cost and migration risk of a broad workflow platform. A full outbox/saga may still be useful later, but certificate sync currently needs bounded lycet retry state rather than generalized orchestration. Best-effort guards are insufficient because they do not make partial failures observable, retryable, or auditable.

## Pros and Cons of the Options

### Option A: Targeted operational state and retry semantics

**Pros**:
- Represents pending subscriptions with `tenant_id` nullable until tenant creation, matching the actual onboarding lifecycle.
- Makes webhook idempotency explicit through reservation, in-progress, completion, failure, and retry semantics.
- Adds observable certificate `lycet_sync_status`/error/update state and a retry command without introducing a broad outbox dependency.
- Reduces secret exposure by encrypting onboarding admin-password envelopes and using scoped environment variables for process secrets.
- Enforces backup access with `ROLE_SUPER_ADMIN` plus ownership verification before file/storage reads.

**Cons**:
- Multiple targeted state machines must be documented and kept consistent across services, migrations, tests, and operators.
- Nullable subscription tenant ownership requires careful query assumptions until provisioning completes.
- Certificate retry state is narrower than a full outbox and may need expansion if more cross-system workflows appear.

---

### Option B: Full outbox and saga orchestration

**Pros**:
- Provides a general pattern for reliable cross-boundary operations and eventual consistency.
- Centralizes retry, dead-letter, and operator visibility concerns.
- Could reduce bespoke retry implementations if many ControlPlane workflows need orchestration.

**Cons**:
- Adds significant schema, infrastructure, and operational complexity for the current scope.
- Increases implementation risk late in SPEC-003 by changing several flows at once.
- Does not by itself solve secret transport or backup authorization policy.

---

### Option C: Best-effort procedural hardening

**Pros**:
- Lowest immediate implementation cost.
- Minimal schema changes and fewer new operational concepts.
- Easy to understand at the individual controller/service level.

**Cons**:
- Partial failures remain difficult to detect and retry safely.
- Duplicate webhooks can still risk duplicate business effects if completion is not persisted explicitly.
- Secret handling and backup reads remain vulnerable to accidental disclosure or authorization bypass if checks are not systematic.

## Consequences

### Positive

- Pending subscriptions are first-class onboarding records and are linked to tenants only after successful provisioning.
- Payment webhook retries are safe: duplicates are skipped only after completion, while failed or unknown events retain retryable failure state.
- Certificate consistency issues with lycet are visible and recoverable through status/error timestamps and a focused retry command.
- Onboarding admin passwords are persisted only as encrypted envelopes and decrypted only in the provisioning handler.
- Database and provisioning process secrets are passed through scoped environment variables instead of argv and are redacted from command output logs.
- Backup downloads require `ROLE_SUPER_ADMIN` and verified tenant/backup ownership before any file or storage access.

### Negative

- Operators must understand several ControlPlane-specific statuses rather than one universal workflow mechanism.
- Some pending records intentionally lack `tenant_id`, so reports and foreign-key assumptions must account for null tenant ownership during onboarding.
- Lycet retry remains a focused remediation path, not a general dead-letter/outbox system.

### Risks

- Pending subscriptions may be mishandled by future queries that assume `tenant_id` is always present — Mitigation: keep tests around pending subscription creation/linking and document nullable semantics in model/migration review.
- Webhook events could be stuck in failed/in-progress states after unexpected crashes — Mitigation: retain timestamps/errors and operate retries based on processing status.
- Certificate database state may diverge from lycet after repeated sync failures — Mitigation: use `lycet_sync_status`, error fields, and the certificate retry command for reconciliation.
- Encrypted password envelopes depend on stable application secret configuration until provisioning consumes them — Mitigation: avoid long-lived envelopes and clear secrets after webhook queueing/success/failure paths.
- Backup ownership checks can drift if alternate download paths are introduced — Mitigation: keep ownership-before-read in `TenantBackupService` as the service-level invariant, not only a controller guard.

## Implementation Notes

- Keep `subscriptions.tenant_id` nullable only for pending, pre-provision tenant subscriptions and link by provision identifier once provisioning succeeds.
- Persist payment event reservation before business processing, mark completion only after successful side effects, and treat already-completed events as the only duplicate skip condition.
- Continue using ControlPlane certificate metadata from ADR-020; use lycet sync status and retry for recoverable backend failures rather than adding a full outbox now.
- Do not pass passwords on command lines. Use encrypted onboarding envelopes for persisted/admin password transport and scoped `TENANT_ADMIN_PASSWORD`, `TENANT_DB_PASS`, `MYSQL_PWD`, or `MARIADB_PWD` environment variables for child processes.
- Backup download endpoints must require `ROLE_SUPER_ADMIN` and verify backup ownership from metadata before invoking file downloads or storage reads.

## References

- ADR-019 — ControlPlane bounded context and Core platform library boundary.
- ADR-020 — ControlPlane ownership of SUNAT certificate lifecycle.
- TASK-049 — Nullable pending subscriptions before tenant creation.
- TASK-052 — Webhook reservation/completion semantics.
- TASK-054 — Certificate lycet sync status and retry.
- TASK-056 — Encrypted onboarding passwords and process secret handling.
- TASK-058 — Backup download authorization and ownership checks.
