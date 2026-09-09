#!/bin/sh
# docker/ci-e2e-runner.sh
# CI-only runner used inside the Playwright container.
# The frontend is pre-built by CI (yarn build in the vite container) before this runs.
# Credentials are injected by CI: E2E_USERNAME, E2E_PASSWORD, E2E_SALE_ID, E2E_BASE_URL.
# Seeding and cleanup are handled by separate CI steps (not this script).
set -e

# Wait for the web server to be ready before running tests.
# This guards against the web container being recreated by docker compose run.
if [ -z "$E2E_BASE_URL" ] || [ "$E2E_BASE_URL" = "http://web" ]; then
  WEB_IP=""
  for _ in $(seq 1 10); do
    WEB_IP=$(getent hosts efact-web | awk '{print $1}' | head -1)
    [ -n "$WEB_IP" ] && break
    sleep 1
  done

  if [ -n "$WEB_IP" ]; then
    export E2E_BASE_URL="http://${WEB_IP}"
  else
    export E2E_BASE_URL="http://efact-web"
  fi
fi

BASE="$E2E_BASE_URL"
echo "Waiting for $BASE/health/live ..."
i=0
until wget -qO- "$BASE/health/live" >/dev/null 2>&1; do
  i=$((i + 1))
  if [ "$i" -ge 24 ]; then
    echo "ERROR: web did not become ready after 120s"
    exit 1
  fi
  echo "  attempt $i/24 — not ready yet, retrying in 5s..."
  sleep 5
done
echo "Web is live."

echo "Waiting for SPA shell at $BASE/ ..."
i=0
until wget -qO- "$BASE/" 2>/dev/null | grep -q 'id="root"' \
  && wget -qO- "$BASE/" 2>/dev/null | grep -q '/build/'; do
  i=$((i + 1))
  if [ "$i" -ge 36 ]; then
    echo "ERROR: SPA shell not ready after 72s"
    wget -qO- "$BASE/" | head -n 40 || true
    exit 1
  fi
  echo "  attempt $i/36 — SPA shell not ready yet, retrying in 2s..."
  sleep 2
done
echo "SPA shell is ready."

# Local runs without CI env vars: seed idempotently (same as e2e-entrypoint).
if [ -z "${E2E_USERNAME:-}" ] || [ -z "${E2E_PASSWORD:-}" ] || [ -z "${E2E_SALE_ID:-}" ]; then
  echo "Seeding E2E credentials from ${BASE}/api/test/e2e/seed ..."
  SEED_JSON=$(curl -sf -X POST "${BASE}/api/test/e2e/seed") || {
    echo "ERROR: E2E seed endpoint failed at ${BASE}/api/test/e2e/seed"
    exit 1
  }
  export E2E_USERNAME=$(echo "$SEED_JSON" | grep -o '"username":"[^"]*"' | cut -d'"' -f4)
  export E2E_PASSWORD=$(echo "$SEED_JSON" | grep -o '"password":"[^"]*"' | cut -d'"' -f4)
  export E2E_SALE_ID=$(echo "$SEED_JSON" | grep -o '"saleId":"[^"]*"' | cut -d'"' -f4)
  echo "E2E seed ready: username=${E2E_USERNAME} saleId=${E2E_SALE_ID}"
fi

corepack enable

# Critical business flows only — no visual/debug/smoke specs.
yarn test:e2e \
  e2e/happy-path.spec.ts \
  e2e/golden-path-fiscal.spec.ts \
  e2e/purchases-stock.spec.ts
