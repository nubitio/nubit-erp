# ADR-004 — FrankenPHP as the PHP Application Server

## Status

Accepted

## Date

2026-04-02

---

## Context

Traditional PHP-FPM deployments spawn a new process per request, which means:
- Application bootstrap (autoloader, DI container compilation, Doctrine metadata loading) repeats on every request.
- Symfony's kernel boot (event listeners registration, config parsing) happens fresh each time.
- For a POS system handling bursty traffic (lunch rush, end-of-day cash close), cold-start overhead adds latency under load.

Additionally, eFact uses Mercure for real-time SSE push notifications. Running a separate Mercure hub process requires coordinating two services, two TLS certificates, and two proxy configs.

**Alternatives considered:**

| Runtime | Notes |
|---------|-------|
| PHP-FPM + Nginx/Caddy | Traditional, well-understood, no persistent state between requests |
| RoadRunner | PHP worker mode (persistent state), requires a Go binary, additional config surface |
| Swoole / OpenSwoole | PHP worker mode, excellent performance, but requires Swoole extension and Swoole-compatible Symfony integration; coroutine model can conflict with Doctrine's unit-of-work assumptions |
| **FrankenPHP** | Built on Caddy + Go + PHP; supports worker mode (persistent PHP process pool); ships built-in Mercure hub; official `php-runtime/frankenphp-symfony` runtime integration |

---

## Decision

Use **FrankenPHP** as the PHP application server, configured via `php-runtime/frankenphp-symfony` runtime integration.

Key configuration choices:
- **Worker mode** — PHP processes are kept alive across requests; Symfony kernel boots once per worker. Doctrine EntityManager is reset between requests by the Symfony Messenger kernel reset subscriber.
- **Built-in Mercure hub** — FrankenPHP's integrated Mercure hub is used for real-time SSE push. This eliminates the need for a separate Mercure service and simplifies TLS configuration (one Caddy certificate covers both API and Mercure).
- **Caddy as the web server** — TLS, HTTPS redirect, and HTTP/2 are managed by FrankenPHP's embedded Caddy.
- **Docker image** — The application's `Dockerfile` extends the official `dunglas/frankenphp` image.

### `Dockerfile` (excerpt)

```dockerfile
FROM dunglas/frankenphp:latest-php8.5-alpine AS base
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# ... install extensions, copy app ...
ENTRYPOINT ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
```

### `composer.json` runtime

```json
{
  "require": {
    "php-runtime/frankenphp-symfony": "^0.3"
  },
  "extra": {
    "runtime": {
      "class": "Runtime\\FrankenPhpSymfony\\Runtime"
    }
  }
}
```

### Symfony Kernel Reset

Between requests in worker mode, the Symfony kernel must be reset to avoid state leakage (Doctrine identity map, container scoped services). The `php-runtime/frankenphp-symfony` package handles this via `RequestHandlerRunner` which calls `KernelInterface::reboot(null)` between requests when configured to do so.

The `TenantConnection::switchTo()` method explicitly resets the DBAL connection DSN at the start of each request (via `TenantListener`), ensuring that worker mode does not leak one tenant's DB connection to the next request.

---

## Consequences

### Positive

- **Lower per-request latency** — Symfony DI container, Doctrine class metadata, and autoloader state are compiled once per worker boot, not per request.
- **Built-in Mercure** — No separate Mercure service to deploy, monitor, or TLS-configure.
- **Single binary deployment** — FrankenPHP + Caddy + Mercure are one process/image. Simpler Docker Compose and production deployment.
- **Official Symfony integration** — `php-runtime/frankenphp-symfony` is maintained by the Symfony team, reducing integration risk.
- **HTTP/2 by default** — Caddy enables HTTP/2 out of the box, improving multiplexed API request performance.

### Negative

- **Worker mode state leak risk** — Any global PHP state (static properties, non-reset singletons) persists across requests. Developers must be aware of this. `TenantConnection` and the Symfony kernel reset mitigate the most critical leaks.
- **FrankenPHP maturity** — FrankenPHP is newer than PHP-FPM or RoadRunner. Edge cases in worker mode may surface in production. Community knowledge base is smaller.
- **Debugging worker mode** — Traditional Xdebug step-debugging in worker mode is more complex than in PHP-FPM. Xdebug must be configured for the FrankenPHP worker context.
- **Doctrine EntityManager reset** — In worker mode, the EM must be explicitly reset between requests to avoid stale identity map. Failure to reset causes `EntityNotFoundException` and similar issues.

---

## Related ADRs

- ADR-001 — `TenantConnection::switchTo()` called on each request, necessary because worker mode keeps the connection object alive between requests.
- ADR-006 — Mercure real-time push: FrankenPHP's built-in Mercure hub is the implementation.
