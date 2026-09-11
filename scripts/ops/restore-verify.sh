#!/bin/sh
set -eu

# Deliberately requires an operator-supplied command: database names, clients,
# and sentinel queries differ between environments. The command MUST target an
# isolated database and print RESTORE_VERIFY_OK only after data assertions pass.
: "${APP_URL:?APP_URL must point to the isolated verification app}"
: "${RESTORE_VERIFY_COMMAND:?RESTORE_VERIFY_COMMAND must run isolated DB assertions}"

output=$(sh -c "$RESTORE_VERIFY_COMMAND")
case "$output" in
  *RESTORE_VERIFY_OK*) ;;
  *) printf '%s\n' 'restore verification failed: sentinel assertions did not pass' >&2; exit 1 ;;
esac

printf '%s\n' "$output"
"$(dirname "$0")/production-smoke.sh"
