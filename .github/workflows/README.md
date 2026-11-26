# CI/CD Workflows

## Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci-main.yml** | PR to `main`, push to `main`, manual | Path-filtered quality on PRs; release-critical E2E on `main` before release and dev deploy; full checks on manual dispatch |
| **release.yml** | Called after successful push CI on `main` | Conventional Commits → optional SemVer release → one immutable image build; attaches a manifest when a release is created |
| **build-image.yml** | Manual, reusable | Build and push image to GHCR, output immutable `image_ref` |
| **deploy-dev.yml** | Manual | Build and deploy a selected workflow ref for preview/recovery |
| **deploy-staging.yml** | Manual, tag `v*.*.*-rc*` | Promote immutable image to `stg` (auto on pre-release tags) |
| **deploy-prod.yml** | Manual, release tag ref or `release_tag` input | Promote that release's recorded digest to `prod`. Does not rebuild. Runs forward migrations, as other normal deploys do |
| **deploy.yml** | Reusable | Deploy a provided immutable image via Portainer |
| **resolve-release.yml** | Reusable | Validate release manifest against its tag, commit and GHCR repository |
| **rollback-prod.yml** | Manual | Restore a release or emergency digest without running migrations |

## GitHub-hosted runners

All jobs use GitHub-hosted x64 runners on `ubuntu-24.04`. No runner-selection variables are required. The deploy jobs need network access from the hosted runner to the configured Portainer endpoint and smoke URL for each environment.

## What CI actually runs

On a **pull request**, `Detect changes` decides which gates are worth the minutes (no repo checkout; uses the GitHub API):

| Job | PR | Push to `main` | Manual |
| --- | --- | --- | --- |
| Backend (Mago, PHPStan, PHPUnit/Pest) | Backend path filter; with coverage | Skipped | With coverage |
| Frontend (ESLint, TypeScript, Vitest) | Frontend path filter; with coverage | Skipped | With coverage |
| Deployment tooling | Runs | Skipped | Runs |
| E2E (Playwright + OpenAPI compatibility) | Skipped | Runs | Runs |
| Release/build and dev deploy | Skipped | After E2E passes; dev reuses the release job's digest | Skipped |

On docs-only PRs, the backend and frontend jobs run their no-op steps so required status checks do not block. Deployment tooling checks still run.
Backend CI enforces 40% PHP line coverage and runs PHPStan at level 8 overall and level 9 for `src/BankReconciliation`. Frontend CI collects coverage without a global threshold.
The release-critical Playwright spec list lives in [`docker/ci-e2e-runner.sh`](../../docker/ci-e2e-runner.sh).

E2E stays on an isolated compose stack (not `dev.efact.app`). Setup is cached:

- Composer `vendor/` via Actions cache
- Yarn package downloads via Actions cache
- Web image via Docker Buildx with GitHub `type=gha` cache
- PCOV is baked into the `dev` image
- Frontend for e2e is built on the runner after MariaDB is ready

## Notes

- Frontend assets for production images are built inside the Docker image (`Dockerfile` stage `frontend-builder`).
- Successful push CI builds one immutable image after quality gates; dev deploys that same digest. When commits warrant a release, the workflow also publishes a version and records the digest in `deployment.json`.
- `deploy-dev.yml` is a manual preview/recovery path and builds the selected workflow ref.
- Staging resolves a commit's `sha-<short_sha>` image, or accepts an explicit digest.
- Production promotion and rollback resolve the exact image from the release's `deployment.json`. Neither rebuilds an image. A published GitHub Release does not promote by itself.

## Automatic SemVer releases

The repository ruleset requires the PR checks before merge. After E2E passes
on the integrated commit on `main`, `release.yml` runs semantic-release with
the locked dependencies in `scripts/release/`. It uses
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
