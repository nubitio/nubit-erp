# Production rollback runbook

## Trigger

Rollback when post-deploy smoke fails, a page-level SLO alert persists after
triage, or a release causes data-integrity or fiscal impact. The incident
commander approves the action and records the current and target image digests.

## Procedure

1. Freeze promotion and acknowledge the incident. Capture release, deploy run,
   tenant, `request_id`/`correlation_id`, and trace IDs.
2. Confirm the target digest is the last known-good production image. Never use
   `latest` or rebuild during an incident.
3. Start **Actions → Rollback production**, provide `release_tag` (for example
   `v1.2.2`), and wait for Portainer deployment plus the smoke gate. The workflow
   validates `deployment.json` against the release's tag commit, deploys its
   recorded digest with its matching Compose definition, and disables forward
   migrations. For older releases without a manifest, provide `image_ref`
   instead and select the source ref containing the appropriate Compose and
   rollback workflow. Provide exactly one target input.
4. If smoke fails, stop traffic at the edge and escalate to Platform + Data;
   do not repeatedly redeploy.
5. Watch `/health`, API 5xx/latency, worker queue age, fiscal retries, and
   webhook age for 15 minutes. Check at least one affected tenant.
6. Reconcile in-flight fiscal documents and webhook deliveries before reopening
   promotion. Preserve the failed release for investigation.

## Rollback gate

Rollback is complete only when smoke checks pass, no new page-level alert fires
for 15 minutes, and the incident commander records customer impact and follow-up
actions. A rollback does not undo database migrations; use the restore runbook
only with an approved recovery decision and a verified backup.

## Migration compatibility

Before choosing a target, verify its code can operate on the current database
schema and queued message formats. Use expand/contract migrations: add nullable
columns/tables first, migrate callers and data, and remove old schema only after
the rollback window closes. The one-shot migration service exits without
running Doctrine commands during rollback. Automatic rollback is not enabled:
a failed deployment can have already applied migrations across tenant databases.

GitHub Releases record built artifacts, not proof of production health. Use the
successful production workflow history and its smoke result to identify the
last known-good version. A failed smoke gate marks the workflow failed but does
not restore the previous stack automatically.
