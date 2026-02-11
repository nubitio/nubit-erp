# CI/CD Workflows

## Workflows

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| **ci-main.yml** | PR to `main`, push to `main` | PHP quality checks + backend tests + frontend lint |
| **build-image.yml** | Manual, reusable | Build and push image to GHCR, output immutable `image_ref` |
| **deploy-dev.yml** | Push to `main`, manual | Build image and deploy to `dev` |
| **deploy-staging.yml** | Manual, tag `v*.*.*-rc*` | Promote immutable image to `stg` (auto on pre-release tags) |
| **deploy-prod.yml** | Manual, tag `v*.*.*` | Promote immutable image to `prod` (auto on release tags) |
| **deploy.yml** | Reusable | Deploy a provided immutable image via Portainer |

## Notes

- Frontend assets are built inside the Docker image (`Dockerfile` stage `frontend-builder`).
- Promotion model uses the same image digest across environments (no rebuild for `stg`/`prod`).
- `dev` is the only environment that builds images automatically from `main`.
- `stg` and `prod` can auto-resolve the image digest from the tagged commit using `ghcr.io/<repo>:sha-<short_sha>`.

## Required Secrets

```
PORTAINER_URL
PORTAINER_API_TOKEN
PORTAINER_STACK_NAME
PORTAINER_ENDPOINT_ID
APP_URL
```

For tenant provisioning workflow (`tenant-provision.yml`) you also need:

```
PROVISIONING_DATABASE_URL
TENANT_DB_PASS
TENANT_ADMIN_PASS
```
