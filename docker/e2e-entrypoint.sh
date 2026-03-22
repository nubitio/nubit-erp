#!/bin/sh
# docker/e2e-entrypoint.sh
# Bootstraps Corepack, starts Vite dev server, seeds test data, then runs tests.
set -e

# ── 1. Activate Yarn 4 via Corepack ──────────────────────────────────────────
# Suppress the interactive download confirmation prompt.
export COREPACK_ENABLE_DOWNLOAD_PROMPT=0
corepack enable

# ── 2. Install dependencies if needed ────────────────────────────────────────
if [ ! -d node_modules ] || [ package.json -nt node_modules/.yarn-integrity 2>/dev/null ]; then
  echo "[e2e-entrypoint] Installing dependencies..."
  yarn install --immutable
fi

# ── 3. Resolve web service URL ────────────────────────────────────────────────
# We navigate to the web container by its numeric IP (resolved from the Docker
# DNS name "efact-web"). We do NOT set a custom Host header — Chromium rejects
# page.goto() when extraHTTPHeaders.Host doesn't match the URL hostname
# (net::ERR_INVALID_ARGUMENT). Caddy's SERVER_NAME=":80" matches any hostname,
# so no Host override is needed.
if [ -z "$E2E_BASE_URL" ]; then
  WEB_IP=$(getent hosts efact-web | awk '{print $1}' | head -1)
  if [ -n "$WEB_IP" ]; then
    export E2E_BASE_URL="http://${WEB_IP}"
    echo "[e2e-entrypoint] Resolved efact-web -> E2E_BASE_URL=${E2E_BASE_URL}"
  fi
fi

# ── 4. Seed E2E test data ─────────────────────────────────────────────────────
# Call the seed endpoint to create the CI test user and return credentials.
# The controller is gated by E2E_SEED_ENABLED=1 on the web service.
SEED_URL="${E2E_BASE_URL}/api/test/e2e/seed"
echo "[e2e-entrypoint] Seeding E2E test data from ${SEED_URL}..."

SEED_RESPONSE=$(curl -sf -X POST "${SEED_URL}" 2>&1) || {
  echo "[e2e-entrypoint] ERROR: Seed endpoint failed. Response: ${SEED_RESPONSE}"
  exit 1
}

echo "[e2e-entrypoint] Seed response: ${SEED_RESPONSE}"

# Parse username and password from JSON response using basic string operations
# (no jq available in the Playwright image by default)
E2E_USERNAME=$(echo "$SEED_RESPONSE" | grep -o '"username":"[^"]*"' | cut -d'"' -f4)
E2E_PASSWORD=$(echo "$SEED_RESPONSE" | grep -o '"password":"[^"]*"' | cut -d'"' -f4)
E2E_SALE_ID=$(echo "$SEED_RESPONSE" | grep -o '"saleId":"[^"]*"' | cut -d'"' -f4)

if [ -z "$E2E_USERNAME" ] || [ -z "$E2E_PASSWORD" ] || [ -z "$E2E_SALE_ID" ]; then
  echo "[e2e-entrypoint] ERROR: Could not parse E2E seed payload from seed response."
  echo "[e2e-entrypoint] Response was: ${SEED_RESPONSE}"
  exit 1
fi

export E2E_USERNAME
export E2E_PASSWORD
export E2E_SALE_ID
echo "[e2e-entrypoint] E2E seed ready: username=${E2E_USERNAME} saleId=${E2E_SALE_ID}"

# ── 5. Start Vite dev server in the background ────────────────────────────────
# The PHP app shell loads assets from http://localhost:5173 (vite-plugin-symfony).
# We start Vite here so the browser can fetch the JS/CSS bundles.
VITE_CHECK_URL="http://localhost:5173/build/@vite/client"
if ! curl -sf "$VITE_CHECK_URL" > /dev/null 2>&1; then
  echo "[e2e-entrypoint] Starting Vite dev server..."
  yarn vite --host 0.0.0.0 --port 5173 > /tmp/vite.log 2>&1 &
  VITE_PID=$!
  echo "[e2e-entrypoint] Vite PID=$VITE_PID — waiting for it to be ready..."
  # Wait up to 90s for Vite to serve the client script
  for i in $(seq 1 90); do
    if curl -sf "$VITE_CHECK_URL" > /dev/null 2>&1; then
      echo "[e2e-entrypoint] Vite is ready (${i}s)"
      break
    fi
    if [ $i -eq 90 ]; then
      echo "[e2e-entrypoint] ERROR: Vite did not start in 90s. Logs:"
      cat /tmp/vite.log
      exit 1
    fi
    sleep 1
  done
else
  echo "[e2e-entrypoint] Vite already running on :5173"
fi

# ── 6. Run the requested command ──────────────────────────────────────────────
# Use a subshell so we can always run cleanup after, regardless of test exit code
TEST_EXIT_CODE=0
"$@" || TEST_EXIT_CODE=$?

# ── 7. Cleanup: reset E2E test data ──────────────────────────────────────────
echo "[e2e-entrypoint] Resetting E2E test data..."
curl -sf -X POST "${E2E_BASE_URL}/api/test/e2e/reset" > /dev/null 2>&1 || \
  echo "[e2e-entrypoint] WARNING: Reset endpoint failed (non-fatal)."

exit $TEST_EXIT_CODE
