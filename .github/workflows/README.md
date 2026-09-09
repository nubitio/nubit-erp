# CI/CD Workflows

## Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci-main.yml** | PR to `main`, push to `main`, manual | Path-filtered quality gates + cached stack + e2e |
| **build-image.yml** | Manual, reusable | Build and push image to GHCR, output immutable `image_ref` |
| **deploy-dev.yml** | Push to `main`, manual | Build image and deploy to `dev` |
| **deploy-staging.yml** | Manual, tag `v*.*.*-rc*` | Promote immutable image to `stg` (auto on pre-release tags) |
| **deploy-prod.yml** | Manual, tag `v*.*.*` | Promote immutable image to `prod` (auto on release tags) |
| **deploy.yml** | Reusable | Deploy a provided immutable image via Portainer |

## Runner switch (GitHub vs Blacksmith)

All workflows read the repository variable `CI_RUNNER_PROVIDER`. Change it when one quota runs out — no YAML edit, no extra secrets.

| Value | Effect |
|-------|--------|
| *(unset)* / `blacksmith` | Blacksmith (default) |
| `github` | GitHub-hosted `ubuntu-latest` |

```bash
gh variable set CI_RUNNER_PROVIDER --body github
gh variable set CI_RUNNER_PROVIDER --body blacksmith
```

Or **Actions → CI → Run workflow** and set `runner_provider`.

Heavy jobs (backend, e2e, image build) use 4/8 vCPU on Blacksmith. Light jobs (path filter, frontend, deploy, skipped gates) use 2 vCPU so they burn half the Blacksmith quota.

## What CI actually runs

On a **pull request**, `Detect changes` decides which gates are worth the minutes (no repo checkout; uses the GitHub API):

| Job | PR | Push to `main` |
|-----|----|----------------|
| Backend (Mago, PHPStan, Pest) | Only if PHP/config/docker changed | Always, **with** coverage |
| Frontend (lint, tsc, Vitest) | Only if `assets/` / Yarn files changed; no coverage | Always, with coverage |
| E2E (3 Playwright flows) | Only if app/UI/e2e changed; skipped for Dependabot | Always |

Docs-only PRs still get a green check: the named jobs start on a 2 vCPU runner and exit immediately so required status checks do not block.

E2E stays on an isolated compose stack (not `dev.efact.app`). Setup is cached:

- Composer `vendor/` via Actions cache
- Yarn via `setup-node` cache
- Web image via Blacksmith BuildKit or GitHub `type=gha` cache
- PCOV is baked into the `dev` image
- Frontend for e2e is built on the runner while the stack boots

## Notes

- Frontend assets for production images are built inside the Docker image (`Dockerfile` stage `frontend-builder`).
- Promotion model uses the same image digest across environments (no rebuild for `stg`/`prod`).
- `dev` is the only environment that builds images automatically from `main`.
- `stg` and `prod` can auto-resolve the image digest from the tagged commit using `ghcr.io/<repo>:sha-<short_sha>`.

## Required secrets (deploy)

```
PORTAINER_URL
PORTAINER_API_TOKEN
PORTAINER_STACK_NAME
PORTAINER_ENDPOINT_ID
APP_URL
```

## Optional: automatic runner failover

Not used day to day. If you later set `CI_RUNNER_PROVIDER=auto` plus `BLACKSMITH_ORG_TOKEN` and `CI_BILLING_TOKEN`, `scripts/ci/select-runner.sh` will prefer Blacksmith and flip to GitHub when remaining minutes drop below `CI_RUNNER_RESERVE_MINUTES` (default 200). Without those secrets, `auto` is the same as `blacksmith`.
