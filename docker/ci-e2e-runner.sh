#!/bin/sh
# docker/ci-e2e-runner.sh
# CI-only runner used inside the Playwright container.
# The frontend is pre-built by CI (yarn build in the vite container) before this runs.
# Credentials are injected by CI: E2E_USERNAME, E2E_PASSWORD, E2E_SALE_ID, E2E_BASE_URL.
# Seeding and cleanup are handled by separate CI steps (not this script).
set -e

corepack enable
yarn test:e2e
