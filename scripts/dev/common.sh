#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

dc() {
  docker compose -f "$ROOT_DIR/docker-compose.yml" "$@"
}

exec_web() {
  dc exec -T web sh -lc "cd /app && $*"
}

exec_vite() {
  dc exec -T --user root vite sh -lc "cd /home/node/app && $*"
}
