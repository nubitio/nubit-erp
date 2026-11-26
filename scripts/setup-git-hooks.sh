#!/bin/sh

set -eu

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Skipping Git hook setup outside a worktree."
  exit 0
fi

git config --local core.hooksPath .husky
chmod +x .husky/pre-push
chmod +x .husky/commit-msg

echo "Git pre-push and commit-message hooks installed."
