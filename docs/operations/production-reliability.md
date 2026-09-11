# Production reliability contract

This document is the operating contract for production. It complements the
health endpoints in `src/Efact/Controller/HealthController.php`, the
OpenTelemetry collector in `docker/otel/`, and the production stack in
`docker-compose.prod.yml`. Alert rules live in `ops/monitoring/alerts.yml`.

## Objectives and measurement

The 30-day service window is UTC. Health probes and deploy smoke checks are
excluded from customer latency and availability calculations. Every event,
log, trace, queue message, and backup record MUST carry (or be joinable by)
`tenant_id`, `request_id`/`correlation_id`, `trace_id`, `release`, and
`environment`. Missing identifiers are an observability defect, not a reason
to remove the event from the denominator.

| Service indicator | Objective | Page/ticket threshold | Evidence |
| --- | --- | --- | --- |
| API availability | ≥99.9% | page when 5xx-derived availability <99.9% for 10m | HTTP server metrics |
| API latency | p95 ≤500ms | ticket when p95 >500ms for 10m | HTTP duration histogram |
| API 4xx / 5xx | <5% / <0.5% | ticket/page after 15m/5m | HTTP status metrics |
| Fiscal recovery | failed submissions reconciled ≤15m | page on retry/failure activity | billing events + traces |
| Async queue | oldest message <5m | page after 10m above 300s | queue age metric/log |
| Webhooks | pending delivery <15m | ticket after 10m above 900s | delivery table/events |
| Backup freshness | active tenants ≤24h old | page after 30m over 86400s | `tenant_backups.completed_at` |
| Tenant health | all active tenants healthy | ticket after 10m degraded | `TenantHealthCheckCommand` |

Alert expressions are intentionally versioned as a backend import contract;
operators must validate metric/log field names in the live SigNoz workspace
before paging. The application currently exposes queue and backup state partly
through logs and database records, so a green rule set is not evidence until
the corresponding telemetry pipeline is enabled.

## Ownership and escalation

| Primary | Scope | Escalation |
| --- | --- | --- |
| Platform on-call | API, deploys, workers, database, Redis, SLO burn | secondary platform engineer → engineering lead |
| Billing on-call | SUNAT submission and fiscal recovery | platform on-call → fiscal operations owner |
| Integrations on-call | webhook delivery and partner callbacks | platform on-call → partner owner |
| Data on-call | backups, restore, RPO/RTO | platform on-call → engineering lead |
| Support on-call | tenant-specific health and customer comms | platform on-call → account owner |

Page acknowledgement target is 5 minutes; incident commander is assigned at
10 minutes. Record the alert ID, release, tenant(s), correlation IDs, timeline,
customer impact, and remediation in the incident ticket. Never expose tenant
credentials or backup contents in the ticket.

## Release gate

Production promotion uses an immutable image digest. `.github/workflows/deploy.yml`
runs `scripts/ops/production-smoke.sh` after Portainer reports deployment.
Any failed smoke check halts promotion. To roll back, run the manual
`Rollback production` workflow with the last known-good image digest; it uses
the same health gate. Do not roll back by rebuilding an image or by changing a
tag in place.

## Recovery verification

At least monthly, perform the restore drill in
`docs/operations/restore-runbook.md` against an isolated database and record
the result in `docs/operations/drills/` using the template there. The drill is
successful only when the measured RPO/RTO meets the limits, migrations and
application smoke checks pass, and tenant data validation finds the expected
sentinel sale, fiscal document, stock movement, and cash movement. A drill
must not overwrite a live tenant database.
