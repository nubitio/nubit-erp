#!/usr/bin/env bash
set -euo pipefail

# ======== CONFIG (via env vars) ========
# Required:
#   PORTAINER_URL
#   PORTAINER_TOKEN (x-api-key)
#   STACK_NAME or STACK_ID
#   ENDPOINT_ID (fallback if stack doesn't provide EndpointId)
#   COMPOSE_FILE
#
# Optional:
#   SWARM_ID
#   IMAGE
#   DRY_RUN=1
# ============================================
PORTAINER_URL="${PORTAINER_URL:-}"
PORTAINER_TOKEN="${PORTAINER_TOKEN:-}"
STACK_ID="${STACK_ID:-}"
STACK_NAME="${STACK_NAME:-}"
ENDPOINT_ID="${ENDPOINT_ID:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
SWARM_ID="${SWARM_ID:-}"
IMAGE="${IMAGE:-}"
DRY_RUN="${DRY_RUN:-0}"

if [[ -z "$PORTAINER_URL" || -z "$PORTAINER_TOKEN" ]]; then
  echo "Missing PORTAINER_URL or PORTAINER_TOKEN" >&2
  exit 1
fi
if [[ -z "$STACK_ID" && -z "$STACK_NAME" ]]; then
  echo "Set STACK_ID or STACK_NAME" >&2
  exit 1
fi
if [[ -z "$ENDPOINT_ID" ]]; then
  echo "Missing ENDPOINT_ID" >&2
  exit 1
fi
if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "Compose file not found: $COMPOSE_FILE" >&2
  exit 1
fi

STACK_FILE_CONTENT=$(cat "$COMPOSE_FILE")
STACK_FILE_TMP="/tmp/portainer_stack.yml"

# Replace image placeholder image: ${IMAGE:-...}
if [[ -n "$IMAGE" ]]; then
  sed_script="s|^\\([[:space:]]*image:[[:space:]]*\\)\\\${IMAGE:[^}]*}|\\1$IMAGE|g"
  STACK_FILE_CONTENT=$(printf "%s" "$STACK_FILE_CONTENT" | sed "$sed_script")
fi

printf "%s" "$STACK_FILE_CONTENT" > "$STACK_FILE_TMP"
STACK_FILE_SIZE=$(wc -c < "$STACK_FILE_TMP" | tr -d ' ')
if [[ "$STACK_FILE_SIZE" == "0" ]]; then
  echo "Stack file is empty after preprocessing." >&2
  exit 1
fi

if [[ "$DRY_RUN" == "1" ]]; then
  printf "%s\n" "$STACK_FILE_CONTENT"
  exit 0
fi

STACKS_JSON=$(curl -sS -H "x-api-key: $PORTAINER_TOKEN" \
  "${PORTAINER_URL%/}/api/stacks")

if ! printf "%s" "$STACKS_JSON" | jq -e . >/dev/null 2>&1; then
  echo "Portainer API did not return JSON. Response:" >&2
  printf "%s\n" "$STACKS_JSON" | head -n 20 >&2
  exit 1
fi

if [[ "$(printf "%s" "$STACKS_JSON" | jq -r 'type')" != "array" ]]; then
  echo "Unexpected Portainer response (expected array). Response:" >&2
  printf "%s\n" "$STACKS_JSON" | head -n 20 >&2
  exit 1
fi

if [[ -n "$STACK_ID" ]]; then
  STACK_JSON=$(printf "%s" "$STACKS_JSON" | jq -c ".[] | select(.Id == ($STACK_ID|tonumber))")
else
  STACK_JSON=$(printf "%s" "$STACKS_JSON" | jq -c ".[] | select(.Name == \"$STACK_NAME\")")
fi

if [[ -z "$STACK_JSON" ]]; then
  echo "Stack not found" >&2
  exit 1
fi

FOUND_ID=$(printf "%s" "$STACK_JSON" | jq -r '.Id')
FOUND_ENDPOINT=$(printf "%s" "$STACK_JSON" | jq -r '.EndpointId // empty')
FOUND_ENV=$(printf "%s" "$STACK_JSON" | jq -c '.Env // []')
FOUND_NAME=$(printf "%s" "$STACK_JSON" | jq -r '.Name')

ENDPOINT_TO_USE=${FOUND_ENDPOINT:-$ENDPOINT_ID}

PAYLOAD_FILE="/tmp/portainer_payload.json"
PAYLOAD_TMP="/tmp/portainer_payload.tmp"
PAYLOAD_ERR="/tmp/portainer_payload.err"
PAYLOAD_PROBE_ERR="/tmp/portainer_payload_probe.err"

# Probe jq raw read capability
if ! jq -Rs '.' "$STACK_FILE_TMP" > /dev/null 2> "$PAYLOAD_PROBE_ERR"; then
  echo "jq failed to read stack file as raw string." >&2
  echo "jq probe stderr:" >&2
  cat "$PAYLOAD_PROBE_ERR" >&2 || true
  exit 1
fi

jq -Rs \
  --argjson env "$FOUND_ENV" \
  --arg name "$FOUND_NAME" \
  --arg swarm "$SWARM_ID" \
  '{
    env: $env,
    stackFileContent: .,
    prune: true,
    pullImage: true,
    name: $name
  } | if ($swarm | length) > 0 then . + {swarmID: $swarm} else . end' \
  "$STACK_FILE_TMP" > "$PAYLOAD_TMP" 2> "$PAYLOAD_ERR"

if [[ ! -s "$PAYLOAD_TMP" ]]; then
  echo "Failed to generate payload with jq." >&2
  echo "jq stderr:" >&2
  cat "$PAYLOAD_ERR" >&2 || true
  echo "Stack file size: $(wc -c < "$STACK_FILE_TMP") bytes" >&2
  echo "Stack file head:" >&2
  sed -n '1,5p' "$STACK_FILE_TMP" >&2
  exit 1
fi

mv "$PAYLOAD_TMP" "$PAYLOAD_FILE"

RESP_HEADERS="/tmp/portainer_headers.txt"
RESP_BODY="/tmp/portainer_body.txt"
RESP_PAYLOAD="$PAYLOAD_FILE"

curl --http1.1 -sS -D "$RESP_HEADERS" -o "$RESP_BODY" -X PUT \
  -H "x-api-key: $PORTAINER_TOKEN" \
  -H "Content-Type: application/json" \
  "${PORTAINER_URL%/}/api/stacks/${FOUND_ID}?endpointId=${ENDPOINT_TO_USE}" \
  --data-binary "@$PAYLOAD_FILE"

STATUS_LINE=$(head -n 1 "$RESP_HEADERS" || true)
printf "%s\n" "=== Headers ==="
printf "%s\n" "$STATUS_LINE"
printf "%s\n" "=== Body Size ==="
wc -c "$RESP_BODY"
if grep -qi "HTTP/1.1 4" "$RESP_HEADERS" || grep -qi "HTTP/1.1 5" "$RESP_HEADERS"; then
  printf "%s\n" "=== Body (error) ==="
  cat "$RESP_BODY"
fi
