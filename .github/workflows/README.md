# CI/CD Workflows

## Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci-main.yml** | PR to `main`, push to `main`, manual | Path-filtered quality gates + cached stack + e2e; after successful `main` gates, deploys dev from the same immutable image |
| **release.yml** | Called after successful push CI on `main` | Conventional Commits → optional SemVer release → one immutable image build; attaches a manifest when a release is created |
| **build-image.yml** | Manual, reusable | Build and push image to GHCR, output immutable `image_ref` |
| **deploy-dev.yml** | Manual | Build and deploy a selected workflow ref for preview/recovery |
| **deploy-staging.yml** | Manual, tag `v*.*.*-rc*` | Promote immutable image to `stg` (auto on pre-release tags) |
| **deploy-prod.yml** | Manual, release tag ref or `release_tag` input | Promote that release's recorded digest to `prod`. Does not rebuild. Runs forward migrations, as other normal deploys do |
| **deploy.yml** | Reusable | Deploy a provided immutable image via Portainer |
| **resolve-release.yml** | Reusable | Validate release manifest against its tag, commit and GHCR repository |
| **rollback-prod.yml** | Manual | Restore a release or emergency digest without running migrations |

## Runner switch (GitHub vs Blacksmith)

The default policy is hybrid: GitHub-hosted runners for light jobs, Blacksmith for heavy jobs. Set the workload-specific repository variables to change one side without affecting the other.

| Variable | Values | Default |
|----------|--------|---------|
| `CI_RUNNER_PROVIDER_LIGHT` | `github`, `blacksmith` | `github` |
| `CI_RUNNER_PROVIDER_HEAVY` | `github`, `blacksmith` | `blacksmith` |
| `CI_RUNNER_PROVIDER` | Legacy global override: `github`, `blacksmith`, `hybrid`, `auto` | unset |

```bash
# Workload-specific provider policy:
gh variable set CI_RUNNER_PROVIDER_LIGHT --body github
gh variable set CI_RUNNER_PROVIDER_HEAVY --body blacksmith
# Optional legacy override for every workload:
gh variable set CI_RUNNER_PROVIDER --body github
gh variable set CI_RUNNER_PROVIDER --body blacksmith
```

Or **Actions → CI → Run workflow** and select `runner_provider`: `hybrid`, `github`, `blacksmith`, or `auto`. The small provider-selection job always runs on GitHub-hosted; heavy CI jobs use 4 vCPU on Blacksmith and image builds use 8 vCPU. Lightweight jobs and deploys use GitHub-hosted by default.

## What CI actually runs

On a **pull request**, `Detect changes` decides which gates are worth the minutes (no repo checkout; uses the GitHub API):

| Job | PR | Push to `main` |
|-----|----|----------------|
| Backend (Mago, PHPStan, Pest) | Only if PHP/config/docker changed | Always, **with** coverage |
| Frontend (lint, tsc, Vitest) | Only if `assets/` / Yarn files changed; no coverage | Always, with coverage |
| E2E (3 Playwright flows) | Only if app/UI/e2e changed; skipped for Dependabot | Always |
| Dev deploy | Never | After backend, frontend, E2E and deployment-tooling gates; reuses the built digest |

Docs-only PRs still get a green check: the named jobs start on a 2 vCPU runner and exit immediately so required status checks do not block.

E2E stays on an isolated compose stack (not `dev.efact.app`). Setup is cached:

- Composer `vendor/` via Actions cache
- Yarn via `setup-node` cache
- Web image via Blacksmith BuildKit or GitHub `type=gha` cache, selected for the heavy runner
- PCOV is baked into the `dev` image
- Frontend for e2e is built on the runner while the stack boots

## Notes

- Frontend assets for production images are built inside the Docker image (`Dockerfile` stage `frontend-builder`).
- Successful push CI builds one immutable image after quality gates; dev deploys that same digest. When commits warrant a release, the workflow also publishes a version and records the digest in `deployment.json`.
- `deploy-dev.yml` is a manual preview/recovery path and builds the selected workflow ref.
- Staging resolves a commit's `sha-<short_sha>` image, or accepts an explicit digest.
- Production promotion and rollback resolve the exact image from the release's `deployment.json`. Neither rebuilds an image. A published GitHub Release does not promote by itself.

## Automatic SemVer releases

After backend, frontend and E2E pass on a push to `main`, `release.yml` runs
semantic-release with the locked dependencies in `scripts/release/`. It uses
the existing `.releaserc.json` rules: `feat` → minor; `fix`, `perf`, `refactor`,
`docs`, `revert` → patch; breaking changes (`feat!` or `BREAKING CHANGE:`) →
major. Other commit types do not create a release unless they are breaking.
Use Conventional Commit titles when squash-merging PRs.

Every successful push to `main` builds the exact commit once. If Conventional
Commits warrant a release, the workflow creates `vMAJOR.MINOR.PATCH` and a
GitHub Release with generated notes, sets `APP_VERSION` to that tag, and attaches
`deployment.json` (schema version, tag, commit, immutable GHCR image reference).
Otherwise the image is still published by SHA for dev. CI deploys that same
digest to dev after the reusable release/build workflow succeeds. Promoting a
stable release to production remains a separate manual action; a
`release: published` event does not deploy production. Release builds stay
serialized on `semver-release-main`. Production deployments and rollbacks have
separate workflows and do not cancel an active deploy. Push CI does not cancel
an in-progress run; PR CI does.

A manual production promotion uses the same reusable deploy as dev and
staging, so forward migrations stay on unless a caller passes
`run_migrations: false`. Rollback is that caller: it restores an image and
does not run migrations, and it does not undo DDL already applied. Take a
backup and keep schema changes compatible with the previous image before
promoting one that migrates. This workflow does not add a backup step or a
new approval gate.

Configure Actions to allow the release workflow's `contents`, `packages`,
`issues`, and `pull-requests` write permissions. Tag rules must permit the
Actions identity to create `v*` tags. Keep existing SemVer tags: they define
the release baseline (semantic-release starts at `1.0.0` if there is no prior
release tag). These workflow files do not configure GitHub Environment
reviewers. Promotion is the manual dispatch itself.

If the build or manifest upload fails after publication, rerun failed jobs on
that CI run before promoting. A release without a manifest cannot be deployed
through `Promote to Production`. For production promotion, open **Actions →
Promote to Production**, select the stable `vMAJOR.MINOR.PATCH` tag in the
workflow ref picker and leave `release_tag` blank. Alternatively, keep the ref
on `main` and type the release tag. Rollback supports the same ref-picker path,
an explicit `release_tag`, or an immutable `image_ref`:

```bash
gh workflow run deploy-prod.yml -f release_tag=v1.2.3
gh workflow run rollback-prod.yml -f release_tag=v1.2.2
```

Rollback requires exactly one of `release_tag` or `image_ref`. Release selection
restores the release Compose and digest; for an emergency `image_ref`, select
`main` as the workflow ref so a tag is not also inferred. Both skip forward
migrations and still run the smoke gate. Choose a previously healthy production
version, not simply the previous published tag. See
`docs/operations/rollback-runbook.md`.

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
