# ADR-015: Mixed Scheduler Model — Central Provider (Messenger) + Per-Command Attributes

Date recorded: 2026-08-19 (retroactive — documents the current state, including an unresolved gap; see Consequences)
Status: Accepted — **Organic, not a deliberate design choice** (see Context)

## Context

Reconstructed from `docs/PRD.md`'s "ADR-024: Scheduler operativo como modelo mixto provider + atributos" citation, never written up as a standalone document (see ADR-006 for the numbering-collision context shared by all reconstructed ADRs). Earlier audit work already flagged "ownership de scheduler/cron poco claro" as a gap; this ADR records exactly what the mix is and confirms that gap rather than resolving it.

## Decision (as observed, not as deliberately chosen)

Two coexisting mechanisms register scheduled/cron work, and there is no record anywhere in the repo of this split being a deliberate architectural decision — it reads as organic drift between an older pattern and a newer one that never fully replaced it.

### Central provider: `src/Schedule.php`

`#[AsSchedule]`, implementing `ScheduleProviderInterface`, dispatches 5 tasks via Symfony Messenger `RunCommandMessage`, all with `throwOnFailure: false`:

- AI briefing generation — daily 07:30
- Low-stock notifications — every 6 hours
- Recurring subscription sales — daily 06:00
- Overdue-sale notifications — daily 08:00
- Fixed-asset depreciation — monthly, day 1, 06:00

These are newer product features (AI, notifications, recurring billing, depreciation — the latter shipped in the same "Fase 6" work that added `FixedAssets`).

### Per-command attribute: `#[AsCronTask]`

11 commands self-register via the attribute directly on the command class, no central provider involved: `TokenCleanupCommand` (Identity), `TenantBackupScheduleCommand`, `BinlogBackupCommand`, `TenantBackupPruneCommand`, `TenantUsageCollectCommand`, `TenantUsagePruneCommand` (all ControlPlane/Tenant), `CleanExpiredFeatureOverridesCommand` (ControlPlane/Billing), `BuildKnowledgeIndexCommand` (AI), `SendInvoiceCommand` (Billing), and daily summary rebuilds for Sales/Purchasing.

These are older, more operational/infra tasks (backups, token cleanup, usage collection) — the pattern that predates `Schedule.php`.

**Reading of the split**: `Schedule.php` is the newer pattern; `#[AsCronTask]` is what existed before and was never migrated. No ADR, comment, or commit message found anywhere explains this as intentional — it's the default outcome of adding a central scheduler without a follow-up pass to consolidate what was already there.

## Consequences

**Positive**
- Both mechanisms work and are visible in code — a developer grepping for `#[AsCronTask]` or reading `Schedule.php` can find every scheduled task; nothing is scheduled invisibly (e.g., via crontab entries outside version control).

**Negative**
- **No monitoring or alerting exists for either mechanism.** Every task in `Schedule.php` explicitly swallows failures (`throwOnFailure: false`), and no structured failure logging, Sentry integration, or healthcheck command was found for either mechanism. A silently-failing depreciation run or backup job would not page anyone — it would just... not run, with no signal.
- **Split ownership with no rule for which to use.** A developer adding a new scheduled task today has no documented guidance on which mechanism to use — the de facto answer ("newer product features go in `Schedule.php`, infra tasks use the attribute") is inferred from this ADR's own reconstruction, not written down anywhere else.
- Two places to check when asking "what's scheduled to run and when" is strictly worse than one, for an otherwise small piece of surface area.

## Alternatives Considered

**Migrate all `#[AsCronTask]` commands into `Schedule.php`** — not done; this ADR does not do it either (it's a code change, not a documentation fix), but records it as the obvious next step if scheduler ownership is ever picked up as a deliberate cleanup: one place to add monitoring, one place to read to answer "what runs and when."

**Add monitoring to the existing split instead of unifying it** — also viable and cheaper than a migration; either would close the actual gap (silent failures), which matters more than which mechanism wins.
