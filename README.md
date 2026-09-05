# eFact — Mini ERP

SaaS multi-tenant de mini ERP para Perú: POS, inventario, compras, tesorería, contabilidad, planilla y facturación electrónica SUNAT. Construido con Symfony + API Platform + React.

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
- Demo tenant: http://demo.localhost:8080
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
- `XAI_API_KEY` (AI assistant; falls back to `OPENAI_API_KEY` + `AI_MODEL_CHAT=gpt-4o-mini`)

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
make up            # levanta database, redis, otel-collector, web, vite y mercure
make down          # baja el stack
make restart       # reinicia contenedores
make reset         # baja, borra volúmenes y reconstruye entorno dev
make bootstrap     # up + install + migrate + cache:clear
make install       # composer install + yarn install
make migrate       # migraciones control plane + tenant
make cache-clear   # limpia cache Symfony
make dev           # levanta Vite dentro de Docker
make lint          # eslint
make lint-php      # mago lint + mago analyze
make format        # prettier
make format-php    # mago fmt
make test          # phpunit + vitest
make e2e           # playwright end-to-end
make ps            # estado de los contenedores
make logs          # logs del stack
make shell-web     # shell en web
make shell-vite    # shell en vite
```

Mercure es crítico: los recursos con `mercure: true` publican tras cada flush, así que un hub caído convierte cualquier `create` en un 500.

El linting de PHP usa [Mago](https://mago.carlosgraca.com/) (`mago.toml` en la raíz), no GrumPHP.

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

### Operaciones tenant existentes

Para aplicar el menú MVP a un tenant ya existente sin resembrar configuración sensible:

```bash
docker compose exec -T web php bin/console app:menu:sync-mvp --tenant=demo --no-interaction
```

No uses `app:seed:initial-data` sobre tenants existentes salvo en un reset controlado: además del menú puede tocar `tenant_settings`, parámetros SUNAT, impresión y flags operativos.

### Preparar una presentación comercial

Usa un tenant nuevo y dedicado para cada prospecto. Tras completar el setup inicial, este comando crea el acceso de dueño y una muestra concisa de mini ERP (catálogo, ventas de 12 meses, compras, CxC/CxP, cotizaciones, planilla, bancos, activos y asientos); no mezcla datos sintéticos en un tenant que ya opera:

```bash
docker compose exec -T web php bin/console app:demo:seed --tenant=demo --no-interaction
```

Acceso creado: `demo@empresa.pe` / `Demo.2026!`. Para una presentación real, cambia la contraseña:

```bash
docker compose exec -T web php bin/console app:demo:seed --tenant=demo --password='UnaClaveDemoSegura' --no-interaction
```

El comando se detiene si detecta productos o ventas existentes. Esto es intencional: una demo debe ser reproducible y nunca contaminar información operativa. La barra de depuración Symfony está desactivada por defecto para que no aparezca frente a un prospecto.

Guión de 20 minutos para el comprador: [`docs/presentation-mini-erp.md`](docs/presentation-mini-erp.md).

Si cargaste una base tenant desde dump o baseline schema y necesitas registrar migraciones como ya aplicadas sin ejecutar SQL:

```bash
docker compose exec -T web php bin/console app:tenants:migrate --baseline-existing-schema --tenant=demo --no-interaction
```

También está disponible durante provisioning:

```bash
docker compose exec -T web php bin/console app:tenant:provision demo --baseline-existing-schema --domain=demo.localhost
```

`--baseline-existing-schema` no crea tablas ni modifica esquema; solo marca las migraciones disponibles como ejecutadas. Úsalo únicamente después de cargar un esquema compatible.

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

Specs E2E (solo flujos críticos — CI y `make e2e`):

| Archivo | Descripción |
|---|---|
| `e2e/happy-path.spec.ts` | Venta completa: login → producto → caja → cliente → POS → venta módulo → movimiento → cierre. |
| `e2e/golden-path-fiscal.spec.ts` | **Golden path fiscal:** login → caja → venta POS → factura → envío SUNAT (stub) → CDR → PDF. |
| `e2e/purchases-stock.spec.ts` | Compras: login → módulo compras → `POST /api/purchases` → stock incrementado. |

Solo el golden path fiscal:

```bash
docker compose --profile e2e run --rm e2e yarn test:e2e e2e/golden-path-fiscal.spec.ts
```

## Calidad de código

```bash
make lint-php      # mago lint + mago analyze
make format-php    # mago fmt --check
make lint          # eslint (frontend)
```

### Pre-push hook

`.husky/pre-push` corre `scripts/quality-check.sh`, que espeja los checks del job "Backend Quality & Coverage" de CI (mago, phpstan, pest) más eslint/tsc/vitest del frontend, contra el stack de Docker local. Requiere `docker compose up -d` corriendo antes de pushear.

`mago analyze` corre con `--fail-on-out-of-sync-baseline`: falla tanto si aparece un issue nuevo no cubierto por `analyzer-baseline.toml` como si el baseline tiene entradas obsoletas (issues que ya no existen). Si el hook falla en este paso:

- **Issue nuevo real**: corrígelo en el código, no lo agregues al baseline.
- **Baseline desactualizado** (el mensaje dice "N entries not seen in this run" o similar): regeneralo con `docker exec efact-web mago analyze --generate-baseline` y commitea `analyzer-baseline.toml` junto con tu cambio — no es deuda tuya, pero bloquea el push hasta que se corrija.

Si necesitas pushear sin correr el stack local (no recomendado salvo emergencia), la verificación real ocurre igual en CI al abrir el PR.

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

## Stack IA

El sistema integra un asistente in-app sobre la misma base Symfony. No hay MCP.

| Componente | Detalle |
|---|---|
| `symfony/ai-bundle` | Agente IA, invocado en `POST /api/assistant` y `/api/assistant/stream` |
| Modelo LLM | xAI `grok-4.3` por defecto (`XAI_API_KEY`). Fallback OpenAI `gpt-4o-mini` |
| RAG | Pipeline TF-IDF (`KnowledgeIndex` + `KnowledgeRetriever`) sobre `docs/cashier-user-manual.md` |
| Herramientas | `#[AsTool]` en `src/AI/Tool/*.php` — llaman servicios de dominio in-process |
| Frontend AI | Drawer en el admin shell (gated by `ai_assistant`) + Markdown con `marked` |

## Plan de producto

- `docs/PRD.md` — PRD operacional actualizado.
- `PRD.md` — north-star para agentes y contributors.

## Inconsistencias / pendientes detectados (código actual)

1. No existe `assets/admin` como raíz; lo real está en `assets/features/admin`.
2. `docs/PRD.md` es el PRD operacional actualizado; `PRD.md` sigue siendo el north-star para agentes y contributors.

## Licencia

Proyecto propietario.
