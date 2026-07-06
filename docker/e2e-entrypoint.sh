#!/bin/sh
# docker/e2e-entrypoint.sh
# Bootstraps Corepack, starts Vite dev server, seeds test data, then runs tests.
set -e

# ── 1. Activate Yarn 4 via Corepack ──────────────────────────────────────────
# Suppress the interactive download confirmation prompt.
export COREPACK_ENABLE_DOWNLOAD_PROMPT=0
corepack enable

# ── 2. Install dependencies if needed ────────────────────────────────────────
if [ ! -d node_modules ] || [ ! -f node_modules/.yarn-state.yml ] || [ package.json -nt node_modules/.yarn-state.yml ] || [ yarn.lock -nt node_modules/.yarn-state.yml ]; then
  echo "[e2e-entrypoint] Installing dependencies..."
  yarn install --immutable
fi

# ── 3. Ensure Rollup native module matches the container platform ────────────
# Host node_modules may have darwin-native modules but the container is Linux.
# Map uname arch to Rollup package arch: aarch64 → arm64, x86_64 → x64
UNAME_M=$(uname -m)
case "$UNAME_M" in
  aarch64)  ROLLUP_ARCH="arm64" ;;
  x86_64)   ROLLUP_ARCH="x64" ;;
  *)        ROLLUP_ARCH="$UNAME_M" ;;
esac
ROLLUP_PKG="@rollup/rollup-linux-${ROLLUP_ARCH}-gnu"
ROLLUP_PKG_DIR="node_modules/${ROLLUP_PKG}"
if [ -d "node_modules/rollup" ] && [ ! -d "$ROLLUP_PKG_DIR" ]; then
  echo "[e2e-entrypoint] Installing Rollup native module for linux-${ROLLUP_ARCH}..."
  npm install --no-save "${ROLLUP_PKG}@4.61.1" 2>&1 || echo "[e2e-entrypoint] WARNING: could not install ${ROLLUP_PKG}"
fi

# ── 4. Resolve web service URL ────────────────────────────────────────────────
# Use the numeric IP of the efact-web container rather than the DNS name.
# Chromium rejects page.goto() with extraHTTPHeaders.Host != URL hostname, so
# we cannot set a custom Host header. Instead we navigate directly to the IP —
# Caddy's SERVER_NAME=":80" matches any hostname, so no Host override is needed.
# We resolve the IP at startup (not hardcode it) to survive container restarts.
if [ -z "$E2E_BASE_URL" ]; then
  # Retry resolution up to 10s to handle race between e2e startup and web DNS propagation
  WEB_IP=""
  for i in $(seq 1 10); do
    WEB_IP=$(getent hosts efact-web | awk '{print $1}' | head -1)
    [ -n "$WEB_IP" ] && break
    sleep 1
  done
  if [ -n "$WEB_IP" ]; then
    export E2E_BASE_URL="http://${WEB_IP}"
    echo "[e2e-entrypoint] Resolved efact-web -> ${WEB_IP} -> E2E_BASE_URL=${E2E_BASE_URL}"
  else
    echo "[e2e-entrypoint] WARNING: could not resolve efact-web, falling back to DNS name"
    export E2E_BASE_URL="http://efact-web"
  fi
fi

# ── 4. Seed E2E test data ─────────────────────────────────────────────────────
# Call the seed endpoint to create the CI test user and return credentials.
# The controller is gated by E2E_SEED_ENABLED=1 on the web service.
SEED_URL="${E2E_BASE_URL}/api/test/e2e/seed"
echo "[e2e-entrypoint] Seeding E2E test data from ${SEED_URL}..."

# Retry up to 30 times (30s) — the web container may still be warming up its DB connection pool
SEED_RESPONSE=""
for i in $(seq 1 30); do
  SEED_RESPONSE=$(curl -sf -X POST "${SEED_URL}" 2>&1) && break
  echo "[e2e-entrypoint] Seed attempt ${i}/30 failed, retrying in 1s..."
  sleep 1
done

if [ -z "$SEED_RESPONSE" ]; then
  echo "[e2e-entrypoint] ERROR: Seed endpoint failed after 30 attempts. Last URL: ${SEED_URL}"
  exit 1
fi

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
# The PHP web container injects <script src="http://localhost:5174/build/..."> tags
# (VITE_DEV_SERVER_ORIGIN=http://localhost:5174 in docker-compose.yml). Inside the
# e2e container, "localhost" refers to *this* container, not the host machine, so
# the browser cannot reach the external efact-vite-1 container on :5174.
#
# Fix: start our own Vite on :5174 inside this container. The web container's HTML
# already points to localhost:5174, so the browser will hit our local Vite instead.
VITE_CHECK_URL="http://localhost:5174/build/@vite/client"
if ! curl -sf "$VITE_CHECK_URL" > /dev/null 2>&1; then
  echo "[e2e-entrypoint] Starting Vite dev server on :5174..."
  # Use port 5174 to match VITE_DEV_SERVER_ORIGIN set in docker-compose for the web container.
  # Also set VITE_DEV_SERVER_ORIGIN so vite.config.js generates correct asset URLs.
  VITE_DEV_SERVER_ORIGIN="http://localhost:5174" npx vite --force --host 0.0.0.0 --port 5174 > /tmp/vite.log 2>&1 &
  VITE_PID=$!
  echo "[e2e-entrypoint] Vite PID=$VITE_PID — waiting for it to be ready..."
  # Wait up to 120s for Vite to serve the client script
  for i in $(seq 1 120); do
    if curl -sf "$VITE_CHECK_URL" > /dev/null 2>&1; then
      echo "[e2e-entrypoint] Vite is ready (${i}s)"
      break
    fi
    if [ $i -eq 120 ]; then
      echo "[e2e-entrypoint] ERROR: Vite did not start in 120s. Logs:"
      cat /tmp/vite.log
      exit 1
    fi
    sleep 1
  done
else
  echo "[e2e-entrypoint] Vite already running on :5174"
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
