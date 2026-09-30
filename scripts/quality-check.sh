#!/bin/sh
#
# Runs the same static-analysis, lint and test checks as the CI "backend"
# and "frontend" jobs (.github/workflows/ci-main.yml), against the running
# local docker compose stack. Run manually with `sh scripts/quality-check.sh`.
#
# The CI "e2e" job is intentionally not mirrored here: it needs a fresh
# migrate + seed cycle and takes ~20 minutes, which is impractical to run
# on every push. It still runs in CI.
#
# Coverage is enforced in CI via PCOV; it is not installed in the local dev
# image, so PHPUnit runs here without coverage collection.

set -e

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "$ROOT_DIR"

WEB_CONTAINER=nubit-erp-web

if ! docker ps --format '{{.Names}}' | grep -qx "$WEB_CONTAINER"; then
  echo "error: $WEB_CONTAINER is not running. Start the stack with 'docker compose up -d' before pushing." >&2
  exit 1
fi

echo "==> [backend] mago lint"
docker exec "$WEB_CONTAINER" mago lint

echo "==> [backend] mago analyze"
# --fail-on-out-of-sync-baseline: fail as soon as the baseline drifts (new
# unbaselined issues OR stale entries for issues that no longer exist),
# instead of only failing on new issues and letting stale entries pile up
# silently for months.
docker exec "$WEB_CONTAINER" mago analyze --fail-on-out-of-sync-baseline

echo "==> [backend] mago fmt --check"
docker exec "$WEB_CONTAINER" mago fmt --check

echo "==> [backend] platform boundary guard"
docker exec "$WEB_CONTAINER" php scripts/architecture/check-platform-boundary.php

echo "==> [backend] generated Symfony reference artifacts"
docker exec "$WEB_CONTAINER" sh scripts/ci/check-generated-artifacts.sh

echo "==> [backend] architecture rules (phparkitect AR-001…AR-010)"
docker exec "$WEB_CONTAINER" composer check:architecture

echo "==> [backend] phpstan"
docker exec -e OTEL_SDK_DISABLED=true "$WEB_CONTAINER" \
  php -d memory_limit=1G vendor/bin/phpstan analyse --configuration=phpstan.dist.neon --no-progress

echo "==> [backend] pest"
TEST_DATABASE_URL="mysql://root:${MYSQL_ROOT_PASSWORD:-changeme}@database:3306/demo_test?serverVersion=10.7.1-MariaDB&charset=utf8mb4"
# Recreate demo_test from scratch every run. A non-pristine demo_test trips a
# Doctrine DBAL / MariaDB schema-introspection assertion during migrate
# (AbstractSchemaManager::introspectTableByUnquotedName), and every failed run
# corrupts it further. A clean database migrates all revisions reliably.
docker compose exec -T database mariadb -uroot -p"${MYSQL_ROOT_PASSWORD:-changeme}" \
  -e "DROP DATABASE IF EXISTS demo_test; CREATE DATABASE demo_test CHARACTER SET utf8mb4;"
docker compose exec -T -e APP_ENV=test -e DATABASE_URL="$TEST_DATABASE_URL" web \
  php bin/console doctrine:migrations:migrate --no-interaction
docker exec -e OTEL_SDK_DISABLED=true -e APP_ENV=test -e APP_SECRET=test-secret-for-kernel-tests-32b -e DATABASE_URL="$TEST_DATABASE_URL" "$WEB_CONTAINER" \
  php vendor/bin/phpunit --testsuite core --do-not-fail-on-deprecation --do-not-fail-on-phpunit-deprecation --do-not-fail-on-notice
docker exec -e OTEL_SDK_DISABLED=true -e APP_ENV=test -e APP_SECRET=test-secret-for-kernel-tests-32b -e DATABASE_URL="$TEST_DATABASE_URL" "$WEB_CONTAINER" \
  php vendor/bin/pest -c phpunit.coverage.xml --no-coverage \
  --do-not-fail-on-deprecation --do-not-fail-on-phpunit-deprecation \
  --do-not-fail-on-skipped

echo "==> [frontend] eslint"
yarn lint

echo "==> [frontend] tsc --noEmit"
yarn tsc --noEmit

echo "==> [frontend] vitest"
yarn test

echo "==> all quality checks passed"
