# eFact SaaS POS

SaaS multi-tenant para POS, inventario y facturación electrónica (SUNAT), construido con Symfony + API Platform + React.

## Inicio rápido (<10 min)

### 1) Requisitos

- Docker + Docker Compose plugin (`docker compose`)
- Git
- (Opcional local) Node 20.19+ y Yarn 4.9.1

### 2) Levantar entorno local

```bash
git clone https://github.com/CompuExpress/efact.git
cd efact
cp .env .env.local

make up
```

Si es tu primera vez en el proyecto, puedes hacer el setup completo con un solo comando:

```bash
make bootstrap
```

### 3) Instalar dependencias

```bash
make install
```

### 4) Migraciones (obligatorio)

```bash
make migrate
```

### 5) Abrir aplicación

- App: http://localhost:8080
- API docs: http://localhost:8080/api/docs
- Vite dev server: http://localhost:5173
- Health: http://localhost:8080/health

## Variables de entorno críticas

Configurar al menos estas variables en `.env.local` para desarrollo real:

- `APP_SECRET`
- `DATABASE_URL` (tenant DB activa)
- `CONTROL_PLANE_DATABASE_URL`
- `TENANTS_SOURCE` (`db` recomendado)
- `TENANTS_CONFIG_PATH` (fallback YAML)
- `REDIS_URL`
- `MESSENGER_TRANSPORT_DSN`
- `MAILER_DSN`
- `ONBOARDING_DOMAIN` (para URLs de subdominio en onboarding)
- `PROVISIONING_DATABASE_URL` (requerido para provisioning automatizado de nuevos tenants)
- `BACKUP_DATABASE_URL` (para backup/restore con `mariadb-dump`)
- `TENANT_DB_PASS` (password de DB user por tenant en provisioning)

Opcionales según features:

- `S3_*` (uploads/backups S3)
- `BILL_API_*` (servicio de facturación)
- `OPENAI_API_KEY` (AI assistant)

## Flujo recomendado local

### Frontend (HMR)

```bash
make dev
```

### Ver logs

```bash
make logs
```

## Comandos rápidos con Make

```bash
make help          # lista comandos disponibles
make up            # levanta database, redis, otel-collector, web y vite
make down          # baja el stack
make restart       # reinicia contenedores
make reset         # baja, borra volúmenes y reconstruye entorno dev
make bootstrap     # up + install + migrate + cache:clear
make install       # composer install + yarn install
make migrate       # migraciones control plane + tenant
make cache-clear   # limpia cache Symfony
make dev           # levanta Vite dentro de Docker
make lint          # eslint
make format        # prettier
make test          # phpunit + vitest
make shell-web     # shell en web
make shell-vite    # shell en vite
```

Los flujos largos viven en `scripts/dev/bootstrap.sh`, `scripts/dev/reset.sh` y `scripts/dev/migrate.sh`. Así mantienes `Makefile` simple y los procesos reutilizables.

Nota para Podman: el compose desactiva el label confinement (`security_opt: label=disable`) en `web`, `vite` y `e2e` para evitar bloqueos SELinux con bind mounts del proyecto. Los comandos de frontend además se ejecutan como `root` dentro de `vite` para evitar errores de permisos sobre el bind mount.

## Crear un tenant

Hay 2 caminos reales:

### A) UI de onboarding (self-service)

1. Abrir `http://localhost:8080/register`
2. Registrar cuenta y verificar código
3. Crear tenant desde wizard (`/register/new-tenant`)

### B) CLI (operativo/admin)

```bash
docker compose exec -T web php bin/console app:tenant:provision demo \
  --domain=demo.localhost \
  --create-user \
  --setup \
  --admin-user=admin \
  --admin-password='Admin.3000' \
  --company-ruc=20000000001 \
  --company-name='Demo SAC' \
  --company-address='Av. Demo 123' \
  --branch-name='Principal'
```

Notas:

- Este comando crea DB tenant, registra tenant/domain en control plane, ejecuta migraciones y setup inicial.
- Requiere `PROVISIONING_DATABASE_URL` y `TENANT_DB_PASS` cuando se usa `--create-user`.

## Acceso al admin (control plane)

El panel admin está bajo `/admin` y usa `http_basic` con provider `control_plane_admin_provider`.

Crear usuario admin de control plane:

```bash
docker compose exec -T web php bin/console app:control-plane:user:create admin \
  --password='Admin.Control.123' \
  --roles='ROLE_ADMIN'
```

Luego abrir:

- http://localhost:8080/admin

## Setup inicial funcional (tenant DB)

El frontend expone asistente de setup en `/initial-setup` cuando no hay datos base.

También puede ejecutarse vía provisioning (`--setup`) o comando de setup tenant (si aplica en tu flujo operativo).

## Testing

### Backend

```bash
make test-back
```

### Backend con cobertura

```bash
docker compose exec -T web php -d pcov.enabled=1 -d pcov.directory=/app/src ./vendor/bin/phpunit -c phpunit.coverage.xml
```

### Frontend unit

```bash
make test-front
```

Umbrales de cobertura (archivos objetivo: `posHelpers.ts`, `offline/api.ts`):
- **85%** líneas / funciones / sentencias
- **80%** ramas

El type-check TypeScript (`tsc --noEmit`) se ejecuta en CI como paso previo a Vitest.

### E2E (Playwright)

```bash
# Seed E2E (obligatorio para tests de flujo)
docker compose exec -T -e E2E_SEED_ENABLED=1 web php bin/console app:test:seed-e2e

# Ejecutar e2e
make e2e

# Cleanup
docker compose exec -T -e E2E_SEED_ENABLED=1 web php bin/console app:test:reset-e2e
```

Hay **dos** archivos de spec E2E:

| Archivo | Descripción |
|---|---|
| `e2e/happy-path.spec.ts` | Flujo POS completo en 9 pasos: login → producto → precio → caja → cliente → venta → movimiento → edición → cierre. Graba video a `test-results/happy-path-full-run.webm`. |
| `e2e/auth-invoice-workflow.spec.ts` | Tests a nivel API: rechazo con credenciales vacías, endpoints de factura sin autenticación, workflow autenticado de creación y listado de facturas (usa `E2E_SALE_ID` sembrado). |

## Calidad de código

```bash
docker compose exec -T web ./vendor/bin/grumphp run --no-interaction
make lint
```

## Docker comandos útiles

```bash
# levantar stack
make up

# bajar stack
make down

# bajar stack + volúmenes
make reset

# entrar a shell web
make shell-web
```

## Deploy

Pipeline CI/CD en GitHub Actions:

- `ci-main.yml`: quality + tests + coverage
- `build-image.yml`: build imagen inmutable GHCR
- `deploy-dev.yml`: deploy automático desde `main`
- `deploy-staging.yml`: promote por tags `v*.*.*-rc*`
- `deploy-prod.yml`: promote por tags `v*.*.*`
- `deploy.yml`: despliegue reusable vía Portainer

Deploy manual por compose prod:

```bash
docker compose -f docker-compose.prod.yml up -d
```

## Stack IA / MCP

El sistema integra un asistente IA y herramientas MCP sobre la misma base Symfony:

| Componente | Detalle |
|---|---|
| `symfony/ai-bundle` (dev-main) | Agente IA, invocado en `POST /api/assistant` |
| Modelo LLM | OpenAI `gpt-4o-mini` (requiere `OPENAI_API_KEY`) |
| RAG | Pipeline TF-IDF (`KnowledgeIndex` + `KnowledgeRetriever`) — búsqueda por similitud de coseno |
| `symfony/mcp-bundle` (dev-main) | Expone herramientas MCP: `SaleTools`, `ProductTools`, `CashRegisterTools`, `DocumentationTools` |
| Frontend AI | `openai ^5.12.0` + pipeline de renderizado Markdown (`marked` + `remark`/`rehype`) |

Las herramientas MCP se registran mediante atributos `#[AsTool]` en `src/AI/Tool/*.php`.
**Nota:** No existe `config/packages/mcp.yaml`; el bundle usa configuración por defecto.

## Arquitectura técnica detallada

Ver documento técnico estratégico:

- `docs/ai/EFACT_TECHNICAL_ARCHITECTURE.md`

## Inconsistencias detectadas (código actual)

1. `config/services.yaml` referencia `config/tenant_plans.php`, pero ese archivo no existe.
2. No existe `assets/admin` como raíz; lo real está en `assets/features/admin`.
3. No existe `src/Billing`; la estructura real de facturación está en `src/EInvoicing/*`.
4. Existe módulo mock legacy `assets/api/auth.tsx` coexistiendo con login real en `assets/features/auth/services/authService.ts`.

## Licencia

Proyecto propietario.
