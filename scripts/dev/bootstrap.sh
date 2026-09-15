#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/dev/common.sh
source "$SCRIPT_DIR/common.sh"

dc up -d database redis otel-collector web vite object-storage object-storage-init
exec_web "composer install --no-interaction --prefer-dist --no-scripts"
exec_web_php "bin/console cache:clear"
exec_web_php "bin/console assets:install public"
exec_vite "corepack enable && yarn install --immutable"
"$SCRIPT_DIR/migrate.sh"
exec_web_php "bin/console cache:clear"

printf "\nBootstrap complete.\n"
printf "- App: http://demo.localhost:8080\n"
printf "- Vite: http://localhost:5174 (starts automatically with the vite container)\n"
