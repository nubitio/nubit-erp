#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/dev/common.sh
source "$SCRIPT_DIR/common.sh"

exec_web_php "bin/console app:control-plane:migrate --no-interaction"
exec_web_php "bin/console app:tenants:migrate --no-interaction"
