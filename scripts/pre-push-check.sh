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
    if command -v corepack >/dev/null 2>&1; then
        printf '%b' "$changed_frontend_files" | xargs -r corepack yarn prettier --check
    else
        printf '%b' "$changed_frontend_files" | xargs -r yarn prettier --check
    fi
else
    echo "==> [frontend] no Prettier-supported files in this push"
fi

echo "==> [frontend] eslint"
if command -v corepack >/dev/null 2>&1; then
    corepack yarn lint
    corepack yarn tsc --noEmit
else
    yarn lint
    yarn tsc --noEmit
fi

if command -v mago >/dev/null 2>&1; then
    echo "==> [backend] Mago lint"
    mago lint

    echo "==> [backend] Mago format check"
    mago fmt --check
else
    echo "Mago is not installed locally; CI will run PHP lint, analysis, and formatting checks."
fi

echo "Pre-push checks passed. Full tests and backend integration checks run in CI."
