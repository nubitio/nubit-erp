#!/usr/bin/env sh
set -eu

: "${BASE_URL:=http://localhost:8080}"
: "${AUTH_TOKEN:?Set AUTH_TOKEN to a test-tenant token}"
command -v k6 >/dev/null 2>&1 || { printf '%s\n' 'k6 is required: https://k6.io/docs/get-started/installation/'; exit 2; }

export BASE_URL AUTH_TOKEN TENANT_HEADER
exec k6 run scripts/capacity/k6.js
