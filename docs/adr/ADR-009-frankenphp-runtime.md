# ADR-009: FrankenPHP as the Application Runtime

Date recorded: 2026-08-19 (retroactive — documents a decision already live in code; see Context)
Status: Accepted — Implemented

## Context

Reconstructed from `docs/PRD.md`'s "ADR-004: FrankenPHP runtime" citation, never written up as a standalone document (collides with the unrelated real `ADR-004`, CashRegister strangler-fig — see ADR-006).

eFact needs a PHP runtime for dev, staging, and production that serves both the Symfony app and its assets efficiently under a multi-tenant, multi-stage Docker build.

## Decision

Use [FrankenPHP](https://frankenphp.dev/) (`dunglas/frankenphp`) as the base runtime image across all Docker build stages (`dev`, `stg`, and production), built on Caddy. Confirmed in `Dockerfile`:

- `FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION} AS base` — the base stage every later stage builds from.
- Runtime config lives at `docker/frankenphp/Caddyfile`, copied into the image and used to start the server: `CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]`.
- The same base image is reused for `dev` and `stg` stages (`FROM base AS dev`, `FROM base AS stg`), keeping runtime behavior consistent across environments rather than switching PHP-FPM/nginx for dev and something else for prod.

## Consequences

**Positive**
- One runtime (Caddy + FrankenPHP's PHP worker mode) across all environments — no dev/prod parity gap from swapping PHP-FPM+nginx locally for FrankenPHP in prod or vice versa.
- Caddy gives automatic HTTPS and a modern reverse-proxy feature set (see the security-headers config in `docker/frankenphp/Caddyfile`, e.g. `Strict-Transport-Security`) without a separate nginx layer.
- FrankenPHP's worker mode can keep the Symfony kernel booted between requests, avoiding PHP-FPM's full bootstrap-per-request cost — relevant for a multi-tenant app where kernel boot also resolves tenant connection state.

**Negative**
- Smaller ecosystem and fewer battle-tested production deployment patterns than PHP-FPM + nginx, which most Symfony hosting guides still assume.
- Worker mode (if enabled) requires the app to correctly reset per-request state between requests — a bug here doesn't fail loudly at request start the way a fresh PHP-FPM process would.
- Caddyfile-based config is a different mental model than `nginx.conf`/`php-fpm.conf` for anyone coming from a conventional Symfony deployment.

## Alternatives Considered

**PHP-FPM + nginx** — not adopted. The conventional Symfony deployment stack; rejected (implicitly, by the existing `Dockerfile`) in favor of FrankenPHP's simpler single-binary runtime and built-in HTTPS/proxy handling. No record of the original comparison exists in the repo.

**PHP-FPM + Caddy** — not adopted; the project uses FrankenPHP's own embedded PHP integration rather than pairing Caddy with a separate FPM pool.
