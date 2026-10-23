#!/usr/bin/env bash
# Run the CI "End-to-End Workflows" job locally, in Docker, before pushing.
#
# Mirrors .github/workflows/ci-main.yml (job `e2e`): APP_ENV=e2e, the slim CI
# compose overlay, a fresh database, migrations, seed, SPA warm-up and the same
# Playwright runner (docker/ci-e2e-runner.sh). It runs in an isolated compose
# project, so the dev stack on :8080 is left alone.
#
#   make e2e-ci                 # full run, stack removed afterwards
#   KEEP_STACK=1 make e2e-ci    # keep the stack up to inspect a failure
#   bash scripts/ci/e2e-local.sh e2e/pos-offline.spec.ts  # focused specs
#   E2E_SETUP_ONLY=1 KEEP_STACK=1 bash scripts/ci/e2e-local.sh  # prepare for debugging
#   SKIP_BUILD=1 make e2e-ci    # reuse the last frontend build (only valid right
#                               # after a run that built it: the dev Vite server
#                               # overwrites public/build when it restarts)

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

export COMPOSE_PROJECT_NAME="${E2E_COMPOSE_PROJECT:-nubit-e2e}"
export COMPOSE_FILE="docker-compose.yml:docker-compose.ci.yml:docker-compose.e2e-local.yml"

export E2E_BASE_URL="http://web"
export APP_ENV="e2e"
export E2E_SEED_ENABLED="1"
export CI="1"
export DATABASE_URL="mysql://demo:changeme@database:3306/demo?serverVersion=10.7.1-MariaDB&charset=utf8mb4"
export CONTROL_PLANE_DATABASE_URL="mysql://efact:changeme@database:3306/efact?serverVersion=10.7.1-MariaDB&charset=utf8mb4"

QUIET_OTEL=(-e OTEL_TRACES_EXPORTER=none -e OTEL_LOGS_EXPORTER=none -e OTEL_METRICS_EXPORTER=none)
DB_ENV=(-e "DATABASE_URL=${DATABASE_URL}" -e "CONTROL_PLANE_DATABASE_URL=${CONTROL_PLANE_DATABASE_URL}")

step() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

BUILT_FRONTEND=0

# The production build replaces public/build, and the dev Vite server only
# rewrites its entrypoints (pointing at :5174) when it starts. So it must be
# restarted after the tests, never between the build and the run.
restore_dev_vite() {
  [[ "$BUILT_FRONTEND" == "1" ]] || return 0
  (
    unset COMPOSE_PROJECT_NAME COMPOSE_FILE
    docker compose -f docker-compose.yml ps --status running --services 2>/dev/null | grep -qx vite \
      && docker compose -f docker-compose.yml restart vite >/dev/null 2>&1
  ) || true
}

cleanup() {
  local status=$?
  if [[ "${KEEP_STACK:-0}" == "1" ]]; then
    echo "KEEP_STACK=1: stack left running (project ${COMPOSE_PROJECT_NAME})."
    echo "public/build holds the production bundle; restart the dev Vite (make dev) when done."
    echo "Remove it with: COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME} COMPOSE_FILE=${COMPOSE_FILE} docker compose down -v"
  else
    step "Stopping e2e stack"
    docker compose down -v >/dev/null 2>&1 || true
    # yarn build rewrites the tracked service worker with different minified
    # names; it is a build artifact, so don't leave it in the working tree.
    [[ "$BUILT_FRONTEND" == "1" ]] && git checkout -- public/sw.js 2>/dev/null || true
    restore_dev_vite
  fi
  exit "$status"
}
trap cleanup EXIT

step "Resetting any previous e2e stack"
docker compose down -v >/dev/null 2>&1 || true

step "Building web image (CI target)"
docker compose build --pull web

step "Starting database redis mercure web"
docker compose up -d --no-build database redis mercure web

if [[ "${SKIP_BUILD:-0}" != "1" ]]; then
  step "Building frontend (production bundle, in a Linux node container)"
  docker compose run --rm --no-deps --user root vite sh -lc \
    'cd /home/node/app && corepack enable && yarn install --immutable && yarn build'
  BUILT_FRONTEND=1
fi

step "Installing PHP dependencies"
# The post-install cache:clear can race with the container health probe that is
# rebuilding var/cache; the install itself is idempotent, so retry.
for attempt in 1 2 3; do
  docker compose exec -T web composer install --no-interaction --no-progress --prefer-dist && break
  [[ "$attempt" == "3" ]] && { echo "composer install failed 3 times" >&2; exit 1; }
  echo "composer install failed (attempt $attempt/3), retrying..." >&2
  sleep 3
done

step "Provisioning tenant database"
docker compose exec -T database sh -lc \
  "until mariadb-admin ping -h127.0.0.1 -uroot -pchangeme --silent; do sleep 1; done"
docker compose exec -T database mariadb -h127.0.0.1 -uroot -pchangeme <<'SQL'
CREATE DATABASE IF NOT EXISTS demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'demo'@'%' IDENTIFIED BY 'changeme';
GRANT ALL PRIVILEGES ON demo.* TO 'demo'@'%';
FLUSH PRIVILEGES;
SQL

step "Running migrations"
docker compose exec -T "${DB_ENV[@]}" "${QUIET_OTEL[@]}" web \
  php bin/console app:control-plane:migrate --no-interaction
docker compose exec -T "${DB_ENV[@]}" "${QUIET_OTEL[@]}" web \
  php bin/console doctrine:migrations:migrate --no-interaction

step "Registering the demo tenant"
docker compose exec -T database mariadb -uroot -pchangeme efact -e \
  "INSERT INTO tenants (name, connection, status, created_at, updated_at, deleted_at) VALUES ('demo', 'demo', 'active', NOW(), NOW(), NULL) ON DUPLICATE KEY UPDATE connection = 'demo', status = 'active', updated_at = NOW(), deleted_at = NULL;"

step "Seeding e2e data"
SEED_OUTPUT="$(docker compose exec -T "${DB_ENV[@]}" -e E2E_SEED_ENABLED=1 "${QUIET_OTEL[@]}" web \
  php bin/console app:test:seed-e2e)"
echo "$SEED_OUTPUT"
SEED_JSON="$(echo "$SEED_OUTPUT" | grep -Eo '\{.*"saleId".*\}' | tail -n 1)"
test -n "$SEED_JSON"
echo "$SEED_JSON" | jq -e '.username and .password and .saleId' >/dev/null
E2E_USERNAME="$(echo "$SEED_JSON" | jq -r '.username')"
E2E_PASSWORD="$(echo "$SEED_JSON" | jq -r '.password')"
E2E_SALE_ID="$(echo "$SEED_JSON" | jq -r '.saleId')"
export E2E_USERNAME E2E_PASSWORD E2E_SALE_ID

step "Warming the SPA"
# A health probe can look up the `demo` tenant before it is registered; the
# registry caches that miss (TenantService, 300s) and every request then 404s
# with "Tenant not found". Drop it now that the tenant exists.
docker compose exec -T --user www-data web php bin/console cache:pool:clear cache.app
docker compose exec -T --user www-data web php bin/console cache:clear --no-warmup --no-debug
docker compose exec -T --user www-data web php bin/console cache:warmup --no-debug
bash scripts/ci/wait-for-spa-host.sh

if [[ "${E2E_SETUP_ONLY:-0}" == "1" ]]; then
  step "E2E stack is ready (tests not started)"
  exit 0
fi

step "Running Playwright e2e (same runner as CI)"
docker compose --profile e2e run --rm --no-deps \
  -e CI -e E2E_USERNAME -e E2E_PASSWORD -e E2E_SALE_ID \
  --entrypoint "" \
  e2e \
  sh /home/node/app/docker/ci-e2e-runner.sh "$@"

step "E2E passed"
