#!/bin/sh
set -eu

: "${APP_URL:?APP_URL is required}"
BASE_URL=${APP_URL%/}
TIMEOUT=${SMOKE_TIMEOUT_SECONDS:-10}

check() {
  path=$1
  expected=${2:-200}
  body=$(mktemp)
  trap 'rm -f "$body"' EXIT HUP INT TERM
  status=$(curl --fail-with-body --silent --show-error --max-time "$TIMEOUT" \
    --output "$body" --write-out '%{http_code}' "$BASE_URL$path" || true)
  if [ "$status" != "$expected" ]; then
    printf 'smoke failed: %s returned HTTP %s (expected %s)\n' "$path" "$status" "$expected" >&2
    return 1
  fi
  case "$(tr -d '\n' < "$body")" in
    *'"status":"ok"'*) ;;
    *) printf 'smoke failed: %s did not report status=ok\n' "$path" >&2; return 1 ;;
  esac
  printf 'smoke ok: %s\n' "$path"
}

check /health/live
check /health
