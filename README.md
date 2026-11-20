# Nubit ERP

SaaS multi-tenant de mini ERP para Perú: POS, inventario, compras, tesorería, contabilidad, planilla y facturación electrónica SUNAT. Construido con Symfony + API Platform + React.

## Inicio rápido (<10 min)

### 1) Requisitos

- Docker + Docker Compose plugin (`docker compose`)
- Git
- (Para frontend local y el hook pre-push) Node compatible con `package.json` (`^22.18.0`, `^24.11.0` o `>=26`) y Yarn 4.12.0 vía Corepack

### 2) Levantar entorno local

```bash
git clone https://github.com/nubitio/nubit-erp.git
cd nubit-erp
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
- Vite dev server: http://localhost:5174 (puerto del host; Vite escucha en 5173 dentro del contenedor)
- Health: http://localhost:8080/health
- Mailpit (correo de desarrollo): http://localhost:8026 (`MAILPIT_UI_PORT` para cambiarlo). El correo sale por la cola `async`: levanta `docker compose up -d messenger_worker` para que se entregue.

## Integración local del Design System

El ERP consume los paquetes `@nubitio/*` publicados en npm. No necesitas clonar
`nubit-react` para instalarlo. Para probar cambios aún no publicados, consulta
[la guía de desarrollo local de `@nubitio`](docs/nubit-local-dev.md).

El tema Nubit se activa en `AppProviders`; los componentes, tokens y layouts se
mantienen en las librerías base. La búsqueda del header busca secciones del menú
autorizado, no documentos ni clientes. El catálogo reutiliza el grid y el
formulario compartidos, sin cambiar reglas de stock, precios o facturación.

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
make up            # levanta database, redis, otel-collector, web, vite, mercure y object-storage
make down          # baja el stack
make restart       # reinicia contenedores
make reset         # baja, borra volúmenes y reconstruye entorno dev
make bootstrap     # up + install + migrate + cache:clear
make install       # composer install + yarn install
make migrate       # migraciones control plane + tenant
make cache-clear   # limpia cache Symfony
make dev           # reinicia Vite en Docker y sigue sus logs
make lint          # eslint
make lint-php      # mago lint + mago analyze + mago fmt --check
make format        # prettier
make format-php    # mago fmt
make test          # Pest + Vitest
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

El panel admin está bajo `/admin` y usa `form_login` con CSRF, limitación de 5 intentos por minuto, remember-me y el provider `control_plane_admin_provider`.

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
docker compose exec -T -e APP_ENV=test -e DATABASE_URL="$TEST_DATABASE_URL" web php -d pcov.enabled=1 -d pcov.directory=/app/src ./vendor/bin/pest -c phpunit.coverage.xml
```

Define `TEST_DATABASE_URL` con la conexión a una base de pruebas ya migrada,
no con la conexión a la base de desarrollo.

### Frontend unit

```bash
make test-front
```

Vitest coverage se recopila para el conjunto de pruebas frontend (`assets/**/*.test.ts[x]`); actualmente no hay umbral frontend global configurado. CI aplica un piso de 40% de cobertura de líneas para PHP (`COVERAGE_MIN_PHP`).

El type-check TypeScript (`tsc --noEmit`) se ejecuta en CI como paso previo a Vitest.

### E2E (Playwright)

Para reproducir el gate de CI en un stack aislado, con migraciones, sembrado y
assets de producción:

```bash
make e2e-ci
```

Specs E2E seleccionadas como gate de release en CI (el conjunto es intencionalmente menor que todos los specs del directorio):

| Archivo | Descripción |
|---|---|
| `e2e/happy-path.spec.ts` | Venta completa: login → producto → caja → cliente → POS → venta módulo → movimiento → cierre. |
| `e2e/golden-path-fiscal.spec.ts` | **Golden path fiscal:** login → caja → venta POS → factura → envío SUNAT (stub) → CDR → PDF. |
| `e2e/purchases-stock.spec.ts` | Compras: login → módulo compras → `POST /api/purchases` → stock incrementado. |
| `e2e/auth-refresh-regression.spec.ts`, `e2e/auth-expiry-concurrency.spec.ts` | Renovación de sesión y concurrencia de autenticación. |
| `e2e/cash-rounding.spec.ts`, `e2e/pos-credit-sale.spec.ts`, `e2e/pos-offline.spec.ts` | Redondeo, venta al crédito y operación offline de POS. |
| `e2e/accounts-receivable.spec.ts`, `e2e/catalog-create-permissions.spec.ts` | Cuentas por cobrar y control de permisos en catálogo. |
| `e2e/nrus-voucher-type.spec.ts` | Tipo de comprobante NRUS. |
| `e2e/treasury.spec.ts` | Activación de Tesorería y cuentas de dinero. |

Solo el golden path fiscal:

```bash
bash scripts/ci/e2e-local.sh e2e/golden-path-fiscal.spec.ts
```

## Calidad de código

```bash
make lint-php      # mago lint + mago analyze + mago fmt --check
make format-php    # mago fmt (reescribe archivos)
make lint          # eslint (frontend)
```

### Pre-push hook

`.husky/pre-push` ejecuta `scripts/pre-push-check.sh` con herramientas locales:
Prettier sobre los archivos compatibles del push, ESLint y TypeScript, más
`mago lint` y `mago fmt --check` si Mago está instalado. Requiere dependencias
frontend instaladas con `corepack yarn install --immutable`, pero no Docker.

Para ejecutar manualmente análisis y pruebas contra Docker, usa
`sh scripts/quality-check.sh` con el stack levantado. Este script recrea
`demo_test`; no lo uses si necesitas conservar esa base. PHPStan usa nivel 8
sin baseline; CI comprueba además `src/BankReconciliation` en nivel 9.

El script manual y CI ejecutan `mago analyze` con `--fail-on-out-of-sync-baseline`.
Si falla este paso:

- **Issue nuevo real**: corrígelo en el código, no lo agregues al baseline.
- **Baseline desactualizado** (el mensaje dice "N entries not seen in this run" o similar): regeneralo con `docker exec nubit-erp-web mago analyze --generate-baseline` y commitea `analyzer-baseline.toml` junto con tu cambio — no es deuda tuya, pero bloquea el push hasta que se corrija.

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

- `ci-main.yml`: calidad, pruebas unitarias y cobertura según los archivos del PR, más checks de tooling de despliegue. En `main` ejecuta E2E (incluida la compatibilidad OpenAPI) sobre el commit integrado. Si pasa, `release.yml` construye **una imagen inmutable** en GHCR y publica la GitHub Release cuando corresponde. Dev reutiliza el mismo digest, sin otro build.
- `build-image.yml`: build reutilizable de imágenes GHCR; 8 vCPU por defecto para el build.
- `deploy-dev.yml`: deploy manual de preview/recuperación. El deploy normal de `main` está en `ci-main.yml`, detrás de CI.
- `deploy-staging.yml`: promoción por tags `v*.*.*-rc*` o manual con un digest inmutable.
- `deploy-prod.yml`: promoción manual de una release estable. En **Actions → Promote to Production**, selecciona el tag `vX.Y.Z` en el selector de ref y deja `release_tag` vacío; también puedes ejecutar desde `main` y escribir el tag como input. El workflow valida la release y usa el digest de `deployment.json`; no reconstruye.
- `rollback-prod.yml`: rollback manual seleccionando el ref de un release estable, indicando `release_tag`, o proporcionando directamente un digest de GHCR. Requiere exactamente un destino y omite migraciones.
- `deploy.yml`: despliegue reusable vía Portainer con smoke test posterior.

### Selección de runners

El comportamiento híbrido predeterminado usa GitHub-hosted para trabajos livianos y Blacksmith para backend/E2E pesados y build de imagen. Se puede configurar en **Settings → Secrets and variables → Actions → Variables**:

- `CI_RUNNER_PROVIDER_LIGHT`: `github` (default) o `blacksmith`.
- `CI_RUNNER_PROVIDER_HEAVY`: `blacksmith` (default) o `github`.
- `CI_RUNNER_PROVIDER`: override legado para forzar los workflows de deploy/build a GitHub (`github`) o Blacksmith (`blacksmith`). Déjalo sin definir para usar las variables por carga. `auto` se admite como override manual de `ci-main.yml`.

Al ejecutar manualmente `ci-main.yml`, `runner_provider` permite elegir `hybrid`, `github`, `blacksmith` o `auto` para esa ejecución.

Deploy manual por compose prod:

```bash
docker compose -f docker-compose.prod.yml up -d
```

## Stack IA

El sistema integra un asistente in-app sobre la misma base Symfony. No hay endpoint MCP en este repositorio.

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
