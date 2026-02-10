# CI/CD Workflows (Minimal)

This directory contains the lightweight GitHub Actions setup for eFact.

## Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci-main.yml** | PR to `main`, push to `main` | PHP quality checks + backend tests + frontend lint |
| **deploy-dev.yml** | Push to `main`, manual | Build and deploy to dev via Portainer |
| **deploy-staging.yml** | Push to `release/*`, manual | Build and deploy to staging via Portainer |
| **deploy-prod.yml** | Tag push `v*.*.*`, manual | Build and deploy to production via Portainer |
| **deploy.yml** | Reusable | Shared deploy logic used by dev/stg/prod |

## Notes

- Frontend assets are built inside the Docker image (`Dockerfile` stage `frontend-builder`).
- CI intentionally does **not** build frontend assets to keep the pipeline fast.
- Deploys are automatic via Portainer; production is tag-driven.

## Required Secrets

```
PORTAINER_URL
PORTAINER_API_TOKEN
PORTAINER_STACK_NAME
PORTAINER_ENDPOINT_ID
APP_URL
```
