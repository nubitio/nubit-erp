#!/usr/bin/env bash

set -euo pipefail

repo_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
validator="${repo_root}/scripts/ci/validate-image-ref.sh"
workflow="${repo_root}/.github/workflows/deploy.yml"
digest=$(printf 'a%.0s' {1..64})

"${validator}" "ghcr.io/nubitio/erp@sha256:${digest}"
! "${validator}" "ghcr.io/nubitio/erp@sha256:${digest}a"
! "${validator}" "ghcr.io/nubitio/erp@sha256:${digest%?}"
! "${validator}" "ghcr.io/nubitio/erp@sha256:${digest%?}g"
! "${validator}" "ghcr.io/nubitio/erp:latest"

ruby -e 'require "yaml"; YAML.load_file(ARGV.fetch(0))' "${workflow}"
grep -Fq 'bash scripts/ci/validate-image-ref.sh "${IMAGE_REF}"' "${workflow}"
