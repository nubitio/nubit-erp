#!/bin/sh
# docker/healthcheck.sh
#
# Liveness check plus a one-time warm-up of /api/docs.json.
#
# FrankenPHP's ZTS opcache has an intermittent zend_mm_heap-corruption crash
# the first time a route this expensive (full Hydra/OpenAPI doc generation,
# across every API Platform resource) compiles under concurrent worker
# threads. FrankenPHP restarts itself after the crash, so retrying here —
# inside Docker's own healthcheck retry loop, before the container is ever
# marked healthy — absorbs it before real traffic (or CI, which fetches this
# same route as a single unretried request) can hit it. A marker file skips
# the expensive request once it has succeeded once, so steady-state checks
# stay as cheap as before.

set -eu

status=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/health || true)
[ "$status" = "200" ] || [ "$status" = "503" ] || exit 1

MARKER=/tmp/.docs-json-warm
if [ ! -f "$MARKER" ]; then
    curl -sf -o /dev/null http://localhost/api/docs.json && touch "$MARKER"
fi
