#!/usr/bin/env bash

set -euo pipefail

image_ref=${1:-}

if [[ ! "${image_ref}" =~ ^.+@sha256:[0-9a-fA-F]{64}$ ]]; then
  echo "image_ref must be an image@sha256:<64 hex digest>" >&2
  exit 1
fi
