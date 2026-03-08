#!/bin/sh
# docker/ci-e2e-runner.sh
# CI-only runner used inside the Playwright container.
# Credentials are injected by CI: E2E_USERNAME, E2E_PASSWORD, E2E_SALE_ID, E2E_BASE_URL.
# Seeding and cleanup are handled by separate CI steps (not this script).
set -e

corepack enable

# Start the Vite dev server in the background so the browser can load JS/CSS bundles.
# We run Vite here (inside the Playwright container) so that the Chromium process
# can reach it via localhost:5173.
yarn vite --host 0.0.0.0 --port 5173 >/tmp/vite.log 2>&1 &
VITE_PID=$!

echo "[ci-e2e] Waiting for Vite dev server on localhost:5173..."
i=0
while [ "$i" -lt 90 ]; do
    i=$((i + 1))
    if curl -sf http://localhost:5173/build/@vite/client >/dev/null 2>&1; then
        echo "[ci-e2e] Vite ready after ${i}s"
        break
    fi
    if [ "$i" -eq 90 ]; then
        echo "[ci-e2e] ERROR: Vite did not become ready within 90s. Logs:"
        cat /tmp/vite.log
        kill "$VITE_PID" 2>/dev/null || true
        exit 1
    fi
    sleep 1
done

# Run Playwright tests. Capture exit code so we can stop Vite cleanly.
yarn test:e2e
TEST_EXIT=$?

kill "$VITE_PID" 2>/dev/null || true
exit $TEST_EXIT
