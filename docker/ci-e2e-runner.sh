#!/bin/sh
# docker/ci-e2e-runner.sh
# CI-only runner used inside the Playwright container.
# The frontend is pre-built by CI (yarn build in the vite container) before this runs.
# Credentials are injected by CI: E2E_USERNAME, E2E_PASSWORD, E2E_SALE_ID, E2E_BASE_URL.
# Seeding and cleanup are handled by separate CI steps (not this script).
set -e

# Wait for the web server to be ready before running tests.
# This guards against the web container being recreated by docker compose run.
BASE="${E2E_BASE_URL:-http://web}"
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
echo "Web is ready."

corepack enable
yarn test:e2e
