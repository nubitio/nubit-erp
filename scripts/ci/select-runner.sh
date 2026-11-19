#!/usr/bin/env bash
# Map the selected runner provider(s) to GitHub Actions runner labels.
#
#   hybrid      — CI_RUNNER_PROVIDER_HEAVY / CI_RUNNER_PROVIDER_LIGHT (defaults: Blacksmith / GitHub)
#   blacksmith  — Blacksmith for every job
#   github      — GitHub-hosted ubuntu-latest for every job
#   auto        — optional; prefer Blacksmith until quota is low.
#                 Requires BLACKSMITH_ORG_TOKEN + CI_BILLING_TOKEN.
#                 Without them, auto == blacksmith.
set -euo pipefail

PROVIDER="$(printf '%s' "${CI_RUNNER_PROVIDER:-}" | tr '[:upper:]' '[:lower:]')"
HEAVY_PROVIDER="$(printf '%s' "${CI_RUNNER_PROVIDER_HEAVY:-blacksmith}" | tr '[:upper:]' '[:lower:]')"
LIGHT_PROVIDER="$(printf '%s' "${CI_RUNNER_PROVIDER_LIGHT:-github}" | tr '[:upper:]' '[:lower:]')"
GITHUB_INCLUDED="${CI_GITHUB_INCLUDED_MINUTES:-2000}"
BLACKSMITH_INCLUDED="${CI_BLACKSMITH_INCLUDED_MINUTES:-3000}"
RESERVE="${CI_RUNNER_RESERVE_MINUTES:-200}"
OWNER="${CI_REPO_OWNER:-}"
OWNER_TYPE="$(printf '%s' "${CI_REPO_OWNER_TYPE:-}" | tr '[:upper:]' '[:lower:]')"

emit() {
  local runner="$1"
  local runner_light="$2"
  local use_bs="$3"
  local provider="$4"
  local block
  block="$(cat <<EOF
runner=${runner}
runner_light=${runner_light}
use_blacksmith_docker=${use_bs}
provider=${provider}
EOF
)"
  printf '%s\n' "$block"
  if [ -n "${GITHUB_OUTPUT:-}" ]; then
    printf '%s\n' "$block" >> "$GITHUB_OUTPUT"
  fi
}

emit_github() {
  echo "Selected GitHub-hosted runners (ubuntu-latest)."
  emit "ubuntu-latest" "ubuntu-latest" "false" "github"
}

emit_blacksmith() {
  echo "Selected Blacksmith runners (4 vCPU heavy / 2 vCPU light)."
  emit "blacksmith-4vcpu-ubuntu-2404" "blacksmith-2vcpu-ubuntu-2404" "true" "blacksmith"
}

emit_hybrid() {
  local heavy_runner light_runner use_blacksmith_docker

  case "$HEAVY_PROVIDER" in
    blacksmith)
      heavy_runner="blacksmith-4vcpu-ubuntu-2404"
      use_blacksmith_docker="true"
      ;;
    github)
      heavy_runner="ubuntu-latest"
      use_blacksmith_docker="false"
      ;;
    *)
      echo "Invalid CI_RUNNER_PROVIDER_HEAVY='$HEAVY_PROVIDER'; expected blacksmith or github." >&2
      exit 2
      ;;
  esac

  case "$LIGHT_PROVIDER" in
    blacksmith) light_runner="blacksmith-2vcpu-ubuntu-2404" ;;
    github) light_runner="ubuntu-latest" ;;
    *)
      echo "Invalid CI_RUNNER_PROVIDER_LIGHT='$LIGHT_PROVIDER'; expected blacksmith or github." >&2
      exit 2
      ;;
  esac

  echo "Selected hybrid runners (heavy=${HEAVY_PROVIDER}, light=${LIGHT_PROVIDER})."
  emit "$heavy_runner" "$light_runner" "$use_blacksmith_docker" "hybrid"
}

case "$PROVIDER" in
  '' | hybrid)
    emit_hybrid
    exit 0
    ;;
  github)
    emit_github
    exit 0
    ;;
  auto) ;;
  blacksmith | '')
    emit_blacksmith
    exit 0
    ;;
  *)
    echo "Unknown CI_RUNNER_PROVIDER='${PROVIDER}', defaulting to Blacksmith."
    emit_blacksmith
    exit 0
    ;;
esac

# ── auto (only used when CI_RUNNER_PROVIDER=auto and secrets exist) ──────
gh_remaining=""
bs_remaining=""

github_used() {
  local url="$1"
  local token="${CI_BILLING_TOKEN:-}"
  if [ -z "$token" ]; then
    return 1
  fi
  local body code
  body="$(mktemp)"
  code="$(curl -sS -o "$body" -w '%{http_code}' \
    -H "Authorization: Bearer ${token}" \
    -H "Accept: application/vnd.github+json" \
    -H "X-GitHub-Api-Version: 2022-11-28" \
    "$url" || true)"
  if [ "$code" != "200" ]; then
    echo "GitHub billing API ${url} returned HTTP ${code}." >&2
    rm -f "$body"
    return 1
  fi
  local used included
  used="$(jq -r '.total_minutes_used // empty' "$body")"
  included="$(jq -r '.included_minutes // empty' "$body")"
  rm -f "$body"
  if [ -z "$used" ]; then
    return 1
  fi
  if [ -n "$included" ] && [ "$included" != "null" ]; then
    GITHUB_INCLUDED="$included"
  fi
  echo $((GITHUB_INCLUDED - used))
}

if [ -n "${CI_BILLING_TOKEN:-}" ] && [ -n "$OWNER" ]; then
  if [ "$OWNER_TYPE" = "organization" ] || [ "$OWNER_TYPE" = "org" ]; then
    gh_remaining="$(github_used "https://api.github.com/orgs/${OWNER}/settings/billing/actions" || true)"
  fi
  if [ -z "$gh_remaining" ]; then
    gh_remaining="$(github_used "https://api.github.com/users/${OWNER}/settings/billing/actions" || true)"
  fi
  if [ -n "$gh_remaining" ]; then
    echo "GitHub Actions remaining: ${gh_remaining} min (included ${GITHUB_INCLUDED})."
  else
    echo "Could not read GitHub billing."
  fi
else
  echo "CI_BILLING_TOKEN not set; auto cannot read GitHub remaining minutes."
fi

blacksmith_remaining() {
  if [ -z "${BLACKSMITH_ORG_TOKEN:-}" ]; then
    return 1
  fi
  if [ -z "${BLACKSMITH_ORG:-}" ]; then
    echo "BLACKSMITH_ORG is required to query Blacksmith usage." >&2
    return 1
  fi

  if ! command -v blacksmith >/dev/null 2>&1; then
    echo "Installing Blacksmith CLI..." >&2
    curl -fsSL https://get.blacksmith.sh | sh
    export PATH="${HOME}/.local/bin:${HOME}/.blacksmith/bin:/usr/local/bin:${PATH}"
  fi
  if ! command -v blacksmith >/dev/null 2>&1; then
    echo "Blacksmith CLI is not on PATH after install." >&2
    return 1
  fi

  export BLACKSMITH_DISABLE_AUTO_UPDATE=1
  printf '%s' "${BLACKSMITH_ORG_TOKEN}" | blacksmith auth login --api-token - --non-interactive --organization "${BLACKSMITH_ORG}" >/dev/null

  local json
  json="$(blacksmith usage --since 30d --format json 2>/dev/null || true)"
  if [ -z "$json" ]; then
    return 1
  fi

  local remaining used
  remaining="$(printf '%s' "$json" | jq -r '
    .free_tier_remaining_minutes
    // .remaining_minutes
    // .summary.free_tier_remaining_minutes
    // .totals.free_tier_remaining_minutes
    // empty
  ')"
  if [ -n "$remaining" ] && [ "$remaining" != "null" ]; then
    printf '%s' "$remaining"
    return 0
  fi

  used="$(printf '%s' "$json" | jq -r '
    .billable_minutes
    // .billing_minutes
    // .summary.billable_minutes
    // .totals.billable_minutes
    // empty
  ')"
  if [ -n "$used" ] && [ "$used" != "null" ]; then
    echo $((BLACKSMITH_INCLUDED - used))
    return 0
  fi

  echo "Blacksmith usage JSON did not contain a known minutes field." >&2
  return 1
}

if bs_remaining="$(blacksmith_remaining)"; then
  echo "Blacksmith remaining: ${bs_remaining} x64 2vCPU-min (included ${BLACKSMITH_INCLUDED})."
else
  bs_remaining=""
  echo "Could not read Blacksmith usage; auto falls back to Blacksmith."
fi

chosen="blacksmith"
if [ -n "$bs_remaining" ] && [ "$bs_remaining" -le "$RESERVE" ]; then
  if [ -z "$gh_remaining" ] || [ "$gh_remaining" -gt "$RESERVE" ]; then
    chosen="github"
  elif [ -n "$gh_remaining" ] && [ "$gh_remaining" -gt "$bs_remaining" ]; then
    chosen="github"
  fi
fi

{
  echo "### Runner selection (auto)"
  echo "- Provider chosen: \`${chosen}\`"
  echo "- Blacksmith remaining: \`${bs_remaining:-unknown}\` / ${BLACKSMITH_INCLUDED} 2vCPU-min"
  echo "- GitHub remaining: \`${gh_remaining:-unknown}\` / ${GITHUB_INCLUDED} min"
  echo "- Reserve: ${RESERVE} min"
} >> "${GITHUB_STEP_SUMMARY:-/dev/null}"

if [ "$chosen" = "github" ]; then
  emit_github
else
  emit_blacksmith
fi
