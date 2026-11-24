#!/usr/bin/env bash
# Keep assets/api/types.generated.ts in sync with the API the backend actually serves.
#
#   scripts/api/app-types.sh           regenerate the file from the running stack
#   scripts/api/app-types.sh --check   fail (with a diff) when the committed file is stale
#
# The OpenAPI document comes from `bin/console api:openapi:export`, which needs no
# running HTTP server and produces the same document as /api/docs.json. Set
# OPENAPI_JSON=/path/to/docs.json to use a document you already have.
#
# --check normalises away `@description` JSDoc lines (copied from ApiProperty keys,
# they differ by environment) so only real path/schema drift fails. Same rule as the
# e2e runner (docker/ci-e2e-runner.sh).
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

MODE="${1:-write}"
TARGET="assets/api/types.generated.ts"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

if [ -n "${OPENAPI_JSON:-}" ]; then
  cp "$OPENAPI_JSON" "$WORK/openapi.json"
else
  docker compose exec -T web php bin/console api:openapi:export >"$WORK/openapi.json"
fi
[ -s "$WORK/openapi.json" ] || { echo "error: empty OpenAPI document (is the stack up? try 'make up')" >&2; exit 1; }

if [ -x node_modules/.bin/openapi-typescript ]; then
  GEN=(node_modules/.bin/openapi-typescript)
else
  GEN=(npx --yes openapi-typescript@7.13.0)
fi
"${GEN[@]}" "$WORK/openapi.json" -o "$WORK/types.ts" >/dev/null

case "$MODE" in
  --check)
    norm() { sed -E '/\/\*\* @description /d' "$1"; }
    if diff -u <(norm "$TARGET") <(norm "$WORK/types.ts") >"$WORK/drift.diff"; then
      echo "OpenAPI types are up to date."
    else
      head -60 "$WORK/drift.diff"
      echo
      echo "error: $TARGET is out of date with the API. Run 'make api-types' and commit the result." >&2
      exit 1
    fi
    ;;
  write | --write)
    cp "$WORK/types.ts" "$TARGET"
    echo "Regenerated $TARGET"
    ;;
  *)
    echo "usage: $0 [--check]" >&2
    exit 2
    ;;
esac
