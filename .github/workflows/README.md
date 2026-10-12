# CI/CD Workflows

## Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci-main.yml** | PR to `main`, push to `main`, manual | Path-filtered quality gates + cached stack + e2e |
| **release.yml** | Called by successful push CI on `main` | Conventional Commits → SemVer release → build → manifest → production |
| **build-image.yml** | Manual, reusable | Build and push image to GHCR, output immutable `image_ref` |
| **deploy-dev.yml** | Push to `main`, manual | Build image and deploy to `dev` |
| **deploy-staging.yml** | Manual, tag `v*.*.*-rc*` | Promote immutable image to `stg` (auto on pre-release tags) |
| **deploy-prod.yml** | Manual release selection, published GitHub Release | Promote the release's existing digest to `prod` |
| **deploy.yml** | Reusable | Deploy a provided immutable image via Portainer |
| **resolve-release.yml** | Reusable | Validate release manifest against its tag, commit and GHCR repository |
| **rollback-prod.yml** | Manual | Restore a release or emergency digest without running migrations |

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
- `dev` builds on pushes to `main`; successful push CI also builds a versioned production image when commits warrant a release.
- Staging resolves a commit's `sha-<short_sha>` image, or accepts an explicit digest.
- Production and rollback resolve the exact image from the release's `deployment.json`; neither rebuilds an image during promotion.

## Automatic SemVer releases

After backend, frontend and E2E pass on a push to `main`, `release.yml` runs
semantic-release with the locked dependencies in `scripts/release/`. It uses
the existing `.releaserc.json` rules: `feat` → minor; `fix`, `perf`, `refactor`,
`docs`, `revert` → patch; breaking changes (`feat!` or `BREAKING CHANGE:`) →
major. Other commit types do not create a release unless they are breaking.
Use Conventional Commit titles when squash-merging PRs.

The job creates `vMAJOR.MINOR.PATCH` and a GitHub Release with generated notes,
builds the exact release commit with `APP_VERSION` set to that tag, and attaches
`deployment.json` (schema version, tag, commit, immutable GHCR image reference).
Production deployment then uses that image and the Compose definition from the
same commit. Release jobs are serialized; production deployments and rollbacks
share `deploy-prod` concurrency and do not cancel an active deployment.
Push CI also avoids cancellation by a newer push, so a release cannot be
interrupted midway through production deployment; PR CI remains cancellable.

The workflow calls build/deploy directly because events created with
`GITHUB_TOKEN` do not start another workflow. No PAT is needed. Published
releases created by a human or external integration can also trigger promotion,
but must already contain a valid `deployment.json`; a bare tag does not deploy.

Configure Actions to allow the workflow's `contents`, `packages`, `issues`, and
`pull-requests` write permissions. Tag rules must permit the Actions identity to
create `v*` tags. Keep existing SemVer tags: they define the release baseline
(semantic-release starts at `1.0.0` if there is no prior release tag).
Existing `prod` Environment reviewers and branch/tag restrictions still apply;
allow `main` for automatic releases and the selected workflow refs for recovery.

If the build or manifest upload fails after publication, rerun failed jobs on
that CI run before promoting. A release without a manifest cannot be deployed
through `Promote to Production`. If deployment fails after manifest publication,
retry by selecting its `release_tag`:

```bash
gh workflow run deploy-prod.yml -f release_tag=v1.2.3
gh workflow run rollback-prod.yml -f release_tag=v1.2.2
```

Rollback requires exactly one of `release_tag` or `image_ref`. Release selection
restores the release Compose and digest; emergency `image_ref` selection uses
the workflow's selected source ref. Both skip forward migrations and still run
the smoke gate. Choose a previously healthy production version, not simply the
previous published tag. See `docs/operations/rollback-runbook.md`.

## Required secrets (deploy)

The reusable deploy workflow always runs the post-deploy smoke gate. Configure
`APP_URL` as a non-empty secret in **each** GitHub Environment (`dev`, `stg`,
and `prod`); it is the explicit smoke target for that environment. A missing
secret fails the deployment before Portainer changes the stack and cannot promote an
unverified release. There is no flag to disable this gate.

```
PORTAINER_URL
PORTAINER_API_TOKEN
PORTAINER_STACK_NAME
PORTAINER_ENDPOINT_ID
APP_URL
```

## Optional: automatic runner failover

Not used day to day. If you later set `CI_RUNNER_PROVIDER=auto` plus `BLACKSMITH_ORG_TOKEN` and `CI_BILLING_TOKEN`, `scripts/ci/select-runner.sh` will prefer Blacksmith and flip to GitHub when remaining minutes drop below `CI_RUNNER_RESERVE_MINUTES` (default 200). Without those secrets, `auto` is the same as `blacksmith`.
