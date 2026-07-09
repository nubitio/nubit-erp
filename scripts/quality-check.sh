#!/bin/sh
#
# Runs the same static-analysis, lint and test checks as the CI "backend"
# and "frontend" jobs (.github/workflows/ci-main.yml), against the running
# local docker compose stack. Invoked by .husky/pre-push.
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

WEB_CONTAINER=efact-web

if ! docker ps --format '{{.Names}}' | grep -qx "$WEB_CONTAINER"; then
  echo "error: $WEB_CONTAINER is not running. Start the stack with 'docker compose up -d' before pushing." >&2
  exit 1
fi

echo "==> [backend] mago lint"
docker exec "$WEB_CONTAINER" mago lint

echo "==> [backend] mago analyze"
docker exec "$WEB_CONTAINER" mago analyze

echo "==> [backend] mago fmt --check"
docker exec "$WEB_CONTAINER" mago fmt --check

echo "==> [backend] phpstan"
docker exec -e OTEL_SDK_DISABLED=true "$WEB_CONTAINER" \
  php -d memory_limit=1G vendor/bin/phpstan analyse --configuration=phpstan.dist.neon --no-progress

echo "==> [backend] phpunit"
docker exec -e OTEL_SDK_DISABLED=true "$WEB_CONTAINER" \
  php vendor/bin/phpunit --no-coverage

echo "==> [frontend] eslint"
yarn lint

echo "==> [frontend] tsc --noEmit"
yarn tsc --noEmit

echo "==> [frontend] vitest"
yarn test

echo "==> all quality checks passed"
