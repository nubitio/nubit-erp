# Tenant database restore and verification runbook

Restores are destructive. This runbook requires an incident commander and a
Data owner. The default action is an isolated drill; never test against a live
tenant database.

## Isolated drill

1. Select a representative active tenant and a completed backup ID. Record
   backup `created_at`, `completed_at`, size, and the drill start time.
2. Provision a disposable MariaDB instance with the same server version and
   drill-only credentials. Keep it off the production network. Use an isolated
   control plane containing the backup and tenant metadata needed for the drill.
3. Validate the artifact before import: object exists, gzip test succeeds, SQL
   header is readable, and recorded size matches the downloaded file.
4. Before running `bin/console app:tenant:restore <backup-id>`, verify that
   `CONTROL_PLANE_DATABASE_URL` points to the isolated control plane and
   `BACKUP_DATABASE_URL` points to the disposable server. The command looks up
   the backup's tenant and uses its `connection` value (or its `name` if empty)
   as the database name; the database path in `BACKUP_DATABASE_URL` is ignored.
   If `BACKUP_DATABASE_URL` is unset, it falls back to the tenant connection
   parameters. Confirm the interactive prompt only after independently
   verifying the server and database name.
5. Run migrations, then execute the production smoke script against the isolated
   app. Validate tenant identity plus counts/checksums for the sentinel sale,
   issued fiscal document, stock movement, and cash movement.
6. Record measured RPO (last accepted event minus backup completion) and RTO
   (drill start through verified smoke) in `docs/operations/drills/YYYY-MM-DD.md`.
   The target is RPO ≤24h and RTO ≤60m. Mark failed if either target or any data
   validation fails, page Data, and create remediation work before the next drill.
7. Destroy the disposable database and temporary files; retain only metadata,
   command output, and remediation notes.

The assertion and isolated-app portion can be gated in CI or a scheduled job by
setting `RESTORE_VERIFY_COMMAND` and running
`scripts/ops/restore-verify.sh`. The command must print `RESTORE_VERIFY_OK` only
after checking the sentinel sale, fiscal document, stock movement, and cash
movement; the script then runs the same `/health/live` and `/health` smoke gate.

## Live recovery

Stop writes for the affected tenant, identify the approved backup, communicate
the expected loss window, and obtain explicit incident-commander approval. Take
a fresh snapshot if possible, restore only after the isolated validation or an
emergency exception is documented, then run tenant health and customer smoke
checks. Reconcile fiscal and webhook outboxes before resuming writes.
