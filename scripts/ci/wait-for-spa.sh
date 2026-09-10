#!/bin/sh
# Wait until the running web process serves the SPA shell.
#
# /health/live is not enough: it skips tenant resolution and does not load Twig
# or Vite entrypoints. Playwright's first page.goto() was paying that cold start
# and failing to mount #/pos within 25s.
#
# Curl from inside the web container uses Host 127.0.0.1, which activates the
# local demo tenant. Use the same explicit CI hostname as the browser path;
# private IP hosts are intentionally ignored by TenantListener.
set -e

BASE="${1:-http://127.0.0.1}"
MAX="${2:-36}"
HOST_HEADER="${3:-host}"
i=0
BODY=""
CODE="000"

while [ "$i" -lt "$MAX" ]; do
  i=$((i + 1))
  CODE="$(curl -sS -m 8 -H "Host: ${HOST_HEADER}" -o /tmp/wait-for-spa.body -w '%{http_code}' "${BASE}/" 2>/dev/null || echo 000)"
  BODY="$(cat /tmp/wait-for-spa.body 2>/dev/null || true)"
  echo "[wait-for-spa] attempt ${i}/${MAX} HTTP ${CODE} (Host: ${HOST_HEADER})"

  if [ "$CODE" = "200" ] \
    && printf '%s' "$BODY" | grep -q 'id="root"' \
    && printf '%s' "$BODY" | grep -q '/build/'; then
    echo "[wait-for-spa] SPA shell is ready."
    exit 0
  fi

  sleep 2
done

echo "[wait-for-spa] ERROR: SPA shell not ready (last HTTP ${CODE})."
printf '%s\n' "$BODY" | head -n 40
exit 1
