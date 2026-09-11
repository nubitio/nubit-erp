# Capacity budgets and structural ceilings

Issue #407 makes the limits below explicit. They are safety budgets, not a
promise that every deployment has the same throughput. Measure a deployment
with `scripts/capacity/run.sh` before changing them.

| Workload | Budget / ceiling | Pass threshold |
| --- | ---: | --- |
| Opening receivables import | 5,000 rows/request; flush and clear every 250 rows | p95 under 1,500 ms at 250 rows |
| Webhook tick | 200 fan-outs and 200 delivery attempts/tenant/pass | 0 unbounded scans; p95 under 2,000 ms/tenant |
| SUNAT retry worker | 100 documents/chunk; 256 MiB worker memory | RSS remains below 230 MiB during a 30-minute run |
| Inventory Kardex export | 10,000 movement rows | HTTP 413/422 (not a process OOM) above the ceiling |
| HTTP web process | 1 GiB / 2 CPUs in production compose | p95 under 750 ms for the read workload |
| Async worker | 512 MiB / 1 CPU in production compose | RSS below 384 MiB; no queue starvation |

## Isolation and operating rules

* Web, asynchronous work, and scheduler work are separate compose services.
  Do not run exports or bulk imports in the scheduler container.
* Webhook fan-out and delivery are both bounded per tenant so one tenant cannot
  consume the whole minute-wide scheduler pass. The existing `--limit` option
  is the control; keep it at or below 200 in production.
* Imports flush and clear in bounded units and reject oversized requests before
  creating entities. The whole request is one database transaction: an
  unexpected failure rolls back all rows, while row validation errors are
  returned as data. The Doctrine unit of work does not grow with the full file.
* Kardex already has a composite `(warehouse_id, product_id, occurred_at)`
  index and a hard 10,000-row export ceiling. Exports above the ceiling return
  HTTP 413 before loading the report. Do not add a second overlapping index
  without an `EXPLAIN` and production cardinality evidence.
* Retry and webhook workloads must use the async worker. Keep the scheduler
  worker for short command dispatch only.

## Reproducible load check

Prerequisites: a running test deployment, `k6`, and an authenticated test
tenant. Set `BASE_URL`, `AUTH_TOKEN`, and optionally `TENANT_HEADER`, then run:

```sh
BASE_URL=http://localhost:8080 AUTH_TOKEN=... \
  scripts/capacity/run.sh
```

The harness tests read traffic plus the bounded import endpoint, emits latency
and error metrics, and fails on the thresholds above. It does not generate
real fiscal documents or call external SUNAT/webhook destinations.
