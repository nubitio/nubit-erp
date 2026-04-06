# ADR-010 — OpenTelemetry as the Unified Observability Standard

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact is a multi-layer platform (PHP backend, React frontend, Kotlin Android app) with multiple external integrations (SUNAT web services, OpenAI, AWS S3, Redis, MariaDB). Without structured observability, diagnosing production issues — especially SUNAT invoice failures, sync errors, or slow queries — requires grepping logs with no correlated context.

**Requirements:**

1. **Distributed tracing** — A single SUNAT invoice submission spans multiple layers: HTTP request → Symfony controller → Messenger handler → SUNAT HTTP call. A trace ID must link all spans.
2. **Structured logging** — Log lines must carry trace/span IDs for correlation. Log aggregation (Loki, Elasticsearch) must be able to join logs with traces.
3. **Metrics** — Request rate, error rate, latency, queue depth (Messenger), and SUNAT submission success rate must be measurable without instrumenting each metric manually.
4. **Cross-platform** — The same observability standard must cover PHP (backend), TypeScript (frontend), and Kotlin (Android).
5. **Vendor neutrality** — The observability pipeline must not be locked to a single vendor (Datadog, New Relic, etc.). Traces should be exportable to any OTLP-compatible backend (Jaeger, Tempo, Honeycomb).

**OpenTelemetry** is the CNCF standard for distributed tracing, metrics, and logs. It has:
- PHP SDK with auto-instrumentation for Symfony, Doctrine, PSR-3 logging.
- TypeScript SDK for browser/Node.
- Kotlin/Java SDK for Android.
- OTLP exporter compatible with any vendor.

---

## Decision

Use **OpenTelemetry** as the unified observability standard across all three platforms, exporting to an OpenTelemetry Collector via OTLP.

### PHP Backend (`efact`)

**Dependencies:**

```json
{
  "require": {
    "open-telemetry/sdk": "^1.9",
    "open-telemetry/exporter-otlp": "*",
    "open-telemetry/opentelemetry-auto-symfony": "*",
    "open-telemetry/opentelemetry-auto-doctrine": "*",
    "open-telemetry/opentelemetry-auto-monolog": "*",
    "open-telemetry/opentelemetry-auto-psr18": "*"
  }
}
```

**Auto-instrumentation covers:**
- Symfony HTTP kernel (request/response spans with route, method, status)
- Doctrine DBAL (SQL query spans with query text and parameters)
- Monolog (log records injected with `trace_id`, `span_id`)
- PSR-18 HTTP client (outbound HTTP calls to SUNAT, OpenAI, etc.)

**OTLP exporter config (`.env`):**

```
OTEL_SERVICE_NAME=efact-backend
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_TRACES_EXPORTER=otlp
OTEL_METRICS_EXPORTER=otlp
OTEL_LOGS_EXPORTER=otlp
```

**Key manual spans:**
- SUNAT XML signing in `VoucherHydrator`
- SUNAT web service call in the invoice send handler
- Mercure publish in event handlers
- Tenant provisioning steps in `TenantProvisioningService`

### Kotlin Android App (`efact-app`)

**Dependencies (`app/build.gradle`):**

```groovy
implementation("io.opentelemetry.android:android:0.6.0-alpha")
implementation("io.opentelemetry:opentelemetry-exporter-otlp:1.40.0")
```

**Coverage:**
- HTTP requests (Retrofit OkHttp interceptor)
- Room DB queries
- Firebase FCM token refresh
- Sync worker execution spans

**OTLP endpoint:** Configurable via `BuildConfig.OTEL_ENDPOINT`.

### Infrastructure

An **OpenTelemetry Collector** container runs in Docker Compose:

```yaml
otel-collector:
  image: otel/opentelemetry-collector-contrib:latest
  volumes:
    - ./docker/otel-collector.yaml:/etc/otelcol-contrib/config.yaml
  ports:
    - "4317:4317"  # OTLP gRPC
    - "4318:4318"  # OTLP HTTP
```

The collector fans out to:
- **Jaeger** (local traces visualization in development)
- **Loki** (log aggregation)
- **Prometheus** (metrics scraping)

In production, the collector can be reconfigured to export to Grafana Cloud, Honeycomb, or any OTLP-compatible backend without changing application code.

---

## Consequences

### Positive

- **Correlated traces across layers** — A SUNAT submission failure can be traced from the HTTP request through the Messenger handler to the SUNAT HTTP call, with all Doctrine queries in between.
- **Zero code change for vendor switch** — Changing from Jaeger to Honeycomb requires only a collector config change.
- **Shared standard across PHP/TS/Kotlin** — Developers learn one observability paradigm regardless of which layer they work on.
- **Automatic Doctrine instrumentation** — Slow queries surface automatically in traces without adding manual spans.
- **Log-trace correlation** — Monolog auto-instrumentation injects `trace_id` into every log line, enabling log-to-trace navigation in Grafana.

### Negative

- **SDK maturity (PHP)** — The PHP OpenTelemetry SDK is still maturing; auto-instrumentation packages may have bugs or performance issues with specific Symfony/Doctrine versions.
- **Performance overhead** — Each request generates spans, attributes, and exporter calls. In high-throughput scenarios, the OTLP exporter must be configured with batching and sampling to avoid measurable latency.
- **Collector as a dependency** — The OTEL Collector is an additional service in production. If it is down, spans are lost (unless the exporter is configured with a buffer/retry).
- **Android battery impact** — OTLP export from a battery-powered Android device must use batching and respect battery optimization constraints.

---

## Related ADRs

- ADR-004 — FrankenPHP (worker mode: OTEL spans must be properly ended between requests)
- ADR-007 — AI/MCP integration (OpenAI API calls auto-instrumented via PSR-18 interceptor)
