#!/bin/sh

set -eu

ROOT_DIR="$(git rev-parse --show-toplevel)"
cd "$ROOT_DIR"

if ! command -v corepack >/dev/null 2>&1 && ! command -v yarn >/dev/null 2>&1; then
    echo "error: Yarn/Corepack is required for pre-push checks." >&2
    exit 1
fi

changed_frontend_files=""
while read -r local_ref local_sha remote_ref remote_sha; do
    [ -n "$local_sha" ] || continue

    case "$remote_sha" in
        *[!0]*) base_sha="$remote_sha" ;;
        *) base_sha="$(git hash-object -t tree /dev/null)" ;;
    esac

    files="$(git diff --name-only --diff-filter=d "$base_sha" "$local_sha" -- \
        '*.js' '*.jsx' '*.ts' '*.tsx' '*.json' '*.css' '*.scss' '*.md')"
    if [ -n "$files" ]; then
        changed_frontend_files="${changed_frontend_files}${files}\n"
    fi
done

if [ -n "$changed_frontend_files" ]; then
    echo "==> [frontend] format check (files in this push)"
    if [ ! -x node_modules/.bin/prettier ]; then
        echo "error: Prettier is not installed. Run corepack yarn install --immutable." >&2
        exit 1
    fi
    printf '%b' "$changed_frontend_files" | xargs -r node_modules/.bin/prettier --check
else
    echo "==> [frontend] no Prettier-supported files in this push"
fi

echo "==> [frontend] eslint"
ESLINT_USE_FLAT_CONFIG=false node_modules/.bin/eslint . --ext .js,.jsx,.ts,.tsx --max-warnings 0
node_modules/.bin/tsc --noEmit

if command -v mago >/dev/null 2>&1; then
    echo "==> [backend] Mago lint"
    mago lint

    echo "==> [backend] Mago format check"
    mago fmt --check
else
    echo "Mago is not installed locally; CI will run PHP lint, analysis, and formatting checks."
fi

echo "Pre-push checks passed. Full tests and backend integration checks run in CI."
