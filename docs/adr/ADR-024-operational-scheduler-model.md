# ADR-024: Operational Scheduler Model — Mixed Attribute and Provider Scheduling

**Status**: Accepted
**Date**: 2026-05-14
**Deciders**: equipo eFact
**Related Specs**: operational hardening, platform readiness
**Supersedes**: —

---

## Context

eFact now has several recurring operational tasks that are part of product correctness, not background convenience:

- SUNAT invoice sending retries and pending send loops.
- Token cleanup.
- ControlPlane usage collection, backup scheduling, binlog backups and pruning.
- Expired feature override cleanup.
- Sales and purchases daily summary rebuilds.
- AI knowledge/briefing jobs.
- Stock-low notification generation.

Symfony supports two scheduling styles in this codebase:

1. Command-local `#[AsCronTask]` attributes.
2. A central schedule provider in `src/Schedule.php` using `RecurringMessage` and `RunCommandMessage`.

The previous PRD open question treated scheduler wiring as unresolved. The current code shows that scheduling is active through both mechanisms.

---

## Decision Drivers

- **Locality:** jobs owned by a command should be discoverable at the command class.
- **Operational grouping:** cross-cutting product workflows should be visible in one provider when they need shared state, timezone, missed-run semantics or comments.
- **Reliability:** missed runs should not fan out uncontrollably after downtime.
- **Asynchronous execution:** recurring work should be dispatched through Messenger instead of running inside HTTP requests.
- **Explicit timezone:** user/business-facing jobs should specify `America/Lima` where the schedule is business-time sensitive.
- **Compatibility:** existing `#[AsCronTask]` jobs should not be rewritten unless there is a concrete operational need.

---

## Considered Options

1. **Mixed model: keep command-local attributes and use `src/Schedule.php` for orchestrated/provider-owned tasks.**
2. **Attribute-only model: every recurring job declares `#[AsCronTask]` directly on the command.**
3. **Provider-only model: remove all attributes and centralize every recurring task in `src/Schedule.php`.**
4. **External cron/Kubernetes scheduler only: remove Symfony Scheduler from application code.**

---

## Decision Outcome

**Chosen option:** Mixed model.

Command-local jobs continue to use `#[AsCronTask]` when the schedule is intrinsic to the command and does not need shared provider semantics. `src/Schedule.php` is reserved for jobs that benefit from stateful scheduling, missed-run policy, explicit business timezone or documentation near other product-level recurring workflows.

Current provider-owned jobs:

- `app:ai:briefing:generate` at `30 7 * * *` in `America/Lima`.
- `app:notification:generate-stock` at `0 */6 * * *` in `America/Lima`.

Current attribute-owned job categories include invoice sending, token cleanup, feature override cleanup, tenant usage/backups/binlogs and reporting summary rebuilds.

---

## Consequences

### Positive

- Developers can find simple recurring command schedules next to the command implementation.
- Product-level jobs with shared operational behavior remain readable in `src/Schedule.php`.
- `src/Schedule.php` uses `stateful($cache)` and `processOnlyLastMissedRun(true)`, avoiding excessive catch-up execution after downtime.
- Business-time jobs can explicitly use `America/Lima`.
- Migration cost is low because existing attributes remain valid.

### Negative

- There are two discovery locations for recurring jobs.
- Operators need a documented inventory of both attribute and provider schedules.
- New contributors may choose the wrong style without guidance.

### Risks

- **Duplicate scheduling:** a command could accidentally be scheduled both by attribute and provider. Mitigation: check both `#[AsCronTask]` and `src/Schedule.php` during review.
- **Unmonitored failures:** `RunCommandMessage(..., throwOnFailure: false)` prevents scheduler crashes but can hide repeated failures. Mitigation: production monitoring must watch command logs, Messenger failures and domain metrics.
- **Timezone drift:** attribute jobs without explicit timezone may run in server/default timezone. Mitigation: use provider scheduling for business-time jobs or document timezone expectations on the command.

---

## Implementation Notes

- Use `#[AsCronTask]` for command-owned maintenance tasks with simple cron cadence.
- Use `src/Schedule.php` for jobs that require stateful missed-run handling, business timezone, grouped operational comments or `RunCommandMessage` dispatch.
- Do not run recurring heavy work synchronously from controllers.
- If a scheduled job mutates tenant data, it must preserve tenant context and respect database-per-tenant isolation.
- If a scheduled job touches SUNAT, billing, backups or inventory, it must emit logs/spans sufficient for operations review.

---

## References

- `src/Schedule.php`
- `src/Billing/Command/SendInvoiceCommand.php`
- `src/Identity/Command/TokenCleanupCommand.php`
- `src/ControlPlane/Billing/Command/CleanExpiredFeatureOverridesCommand.php`
- `src/ControlPlane/Tenant/Command/*Backup*Command.php`
- `src/Sales/Command/SalesV2RebuildDailySummariesCommand.php`
- `src/Purchasing/Command/PurchasesV2RebuildDailySummariesCommand.php`
- ADR-021 — ControlPlane operational hardening
