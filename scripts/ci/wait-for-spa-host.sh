#!/usr/bin/env bash
# Poll the SPA from the runner, starting a fresh docker exec for each attempt.
# If the web container restarts while FrankenPHP compiles its first HTML route,
# the current exec can be killed; keeping the retry loop on the runner lets it
# reconnect after Docker restarts the service.

set -euo pipefail

MAX="${1:-36}"
MARKER='__SPA_HTTP_CODE__'

for ((attempt = 1; attempt <= MAX; attempt++)); do
    response=''
    if response="$(docker compose exec -T web sh -lc \
        'curl -sS -m 8 -H "Host: 127.0.0.1" -w "__SPA_HTTP_CODE__%{http_code}" http://127.0.0.1/ 2>/dev/null' \
        2>/dev/null)"; then
        if [[ "$response" == *"$MARKER"* ]]; then
            code="${response##*"$MARKER"}"
            body="${response%"$MARKER"*}"
        else
            code='000'
            body="$response"
        fi
    else
        # The exec can exit 137 if FrankenPHP/container restarts during the
        # request. Keep the loop alive and start a new exec on the next try.
        code='000'
        body=''
    fi

    printf '[wait-for-spa] attempt %s/%s HTTP %s (Host: 127.0.0.1)\n' "$attempt" "$MAX" "$code"

    if [[ "$code" == '200' && "$body" == *'id="root"'* && "$body" == *'/build/'* ]]; then
        echo '[wait-for-spa] SPA shell is ready.'
        exit 0
    fi

    sleep 2
done

echo '[wait-for-spa] ERROR: SPA shell did not become ready.' >&2
docker compose ps -a web >&2 || true
docker inspect nubit-erp-web --format 'state={{.State.Status}} exit={{.State.ExitCode}} oom={{.State.OOMKilled}} restarts={{.RestartCount}}' >&2 || true
docker compose logs --tail=80 web >&2 || true
exit 1
